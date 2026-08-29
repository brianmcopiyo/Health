<?php

namespace App\Support;

use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\InventoryIssue;
use App\Models\InventoryIssueItem;
use App\Models\InventoryItem;
use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptItem;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestItem;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnItem;
use App\Models\InventoryStore;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\Medication;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InventoryPoster
{
    public function __construct(private InventoryLedger $ledger) {}

    public function receive(array $input): InventoryReceipt
    {
        $items = $input['items'] ?? [];
        if (! $items) {
            throw new InvalidArgumentException('Add at least one item to receive.');
        }

        return DB::transaction(function () use ($input, $items) {
            $store = InventoryStore::query()->findOrFail($input['store_id']);
            $receipt = InventoryReceipt::query()->create([
                'hospital_id' => $store->hospital_id,
                'reference' => $this->reference('GRN'),
                'store_id' => $store->id,
                'supplier_id' => $input['supplier_id'] ?? null,
                'created_by' => $input['created_by'] ?? null,
                'received_at' => $input['received_at'] ?? now(),
                'notes' => $input['notes'] ?? null,
            ]);

            foreach ($items as $row) {
                $item = InventoryItem::query()->findOrFail($row['item_id']);
                $this->guardControlled($item, $input['allow_controlled'] ?? false);
                $quantity = (float) ($row['quantity'] ?? 0);
                if ($quantity <= 0) {
                    throw new InvalidArgumentException('Receive quantity must be greater than zero.');
                }

                $batchId = null;
                if ($item->tracks_batch) {
                    $number = $row['batch_number'] ?? $receipt->reference;
                    if ($item->tracks_expiry && empty($row['expiry_date'])) {
                        throw new InvalidArgumentException('Expiry date is required for '.$item->name.'.');
                    }
                    $batch = InventoryBatch::query()->firstOrCreate(
                        ['item_id' => $item->id, 'store_id' => $store->id, 'batch_number' => $number],
                        [
                            'hospital_id' => $store->hospital_id,
                            'location_id' => $row['location_id'] ?? null,
                            'supplier_id' => $input['supplier_id'] ?? null,
                            'expiry_date' => $row['expiry_date'] ?? null,
                            'quantity' => 0,
                            'unit_cost' => $row['unit_cost'] ?? $item->cost_price,
                            'status' => 'available',
                            'received_at' => now(),
                        ]
                    );
                    $batchId = $batch->id;
                }

                $this->ledger->post([
                    'item_id' => $item->id,
                    'store_id' => $store->id,
                    'location_id' => $row['location_id'] ?? null,
                    'batch_id' => $batchId,
                    'type' => 'receive',
                    'quantity' => $quantity,
                    'unit_cost' => $row['unit_cost'] ?? $item->cost_price,
                    'reference_type' => InventoryReceipt::class,
                    'reference_id' => $receipt->id,
                    'created_by' => $input['created_by'] ?? null,
                    'occurred_at' => $receipt->received_at,
                ]);

                InventoryReceiptItem::query()->create([
                    'receipt_id' => $receipt->id,
                    'item_id' => $item->id,
                    'batch_id' => $batchId,
                    'quantity' => $quantity,
                    'unit_cost' => $row['unit_cost'] ?? $item->cost_price,
                    'batch_number' => $row['batch_number'] ?? null,
                    'expiry_date' => $row['expiry_date'] ?? null,
                ]);
            }

            Audit::record('created', $receipt);

            return $receipt->load(['store', 'supplier', 'items.item', 'items.batch']);
        });
    }

    public function transfer(array $input): InventoryTransfer
    {
        $from = InventoryStore::query()->findOrFail($input['from_store_id']);
        $to = InventoryStore::query()->findOrFail($input['to_store_id']);
        if ($from->id === $to->id) {
            throw new InvalidArgumentException('Choose two different stores.');
        }

        $items = $input['items'] ?? [];
        if (! $items) {
            throw new InvalidArgumentException('Add at least one item to transfer.');
        }

        return DB::transaction(function () use ($input, $from, $to, $items) {
            $transfer = InventoryTransfer::query()->create([
                'hospital_id' => $from->hospital_id,
                'reference' => $this->reference('TRF'),
                'from_store_id' => $from->id,
                'to_store_id' => $to->id,
                'status' => 'completed',
                'created_by' => $input['created_by'] ?? null,
                'occurred_at' => $input['occurred_at'] ?? now(),
                'notes' => $input['notes'] ?? null,
            ]);

            foreach ($items as $row) {
                $item = InventoryItem::query()->findOrFail($row['item_id']);
                $this->guardControlled($item, $input['allow_controlled'] ?? false);
                $quantity = (float) ($row['quantity'] ?? 0);
                if ($quantity <= 0) {
                    throw new InvalidArgumentException('Transfer quantity must be greater than zero.');
                }

                [$sourceBatchId, $destinationBatchId] = $this->moveBatch($item, $from->id, $to->id, $row['batch_id'] ?? null, $quantity);

                $this->ledger->post([
                    'item_id' => $item->id,
                    'store_id' => $from->id,
                    'batch_id' => $sourceBatchId,
                    'type' => 'transfer_out',
                    'quantity' => $quantity,
                    'reference_type' => InventoryTransfer::class,
                    'reference_id' => $transfer->id,
                    'created_by' => $input['created_by'] ?? null,
                    'occurred_at' => $transfer->occurred_at,
                ]);

                $this->ledger->post([
                    'item_id' => $item->id,
                    'store_id' => $to->id,
                    'batch_id' => $destinationBatchId,
                    'type' => 'transfer_in',
                    'quantity' => $quantity,
                    'reference_type' => InventoryTransfer::class,
                    'reference_id' => $transfer->id,
                    'created_by' => $input['created_by'] ?? null,
                    'occurred_at' => $transfer->occurred_at,
                ]);

                InventoryTransferItem::query()->create([
                    'transfer_id' => $transfer->id,
                    'item_id' => $item->id,
                    'batch_id' => $sourceBatchId,
                    'quantity' => $quantity,
                ]);
            }

            Audit::record('created', $transfer);

            return $transfer->load(['fromStore', 'toStore', 'items.item', 'items.batch']);
        });
    }

    public function request(array $input): InventoryRequest
    {
        $items = $input['items'] ?? [];
        if (! $items) {
            throw new InvalidArgumentException('Add at least one requested item.');
        }

        return DB::transaction(function () use ($input, $items) {
            $store = InventoryStore::query()->findOrFail($input['to_store_id']);
            $request = InventoryRequest::query()->create([
                'hospital_id' => $store->hospital_id,
                'reference' => $this->reference('REQ'),
                'from_store_id' => $input['from_store_id'] ?? null,
                'to_store_id' => $store->id,
                'department_id' => $input['department_id'] ?? null,
                'status' => 'requested',
                'requested_by' => $input['requested_by'] ?? null,
                'requested_at' => now(),
                'notes' => $input['notes'] ?? null,
            ]);

            foreach ($items as $row) {
                InventoryRequestItem::query()->create([
                    'request_id' => $request->id,
                    'item_id' => $row['item_id'],
                    'quantity' => $row['quantity'],
                ]);
            }

            Audit::record('created', $request);

            return $request->load(['toStore', 'fromStore', 'department', 'items.item']);
        });
    }

    public function issue(array $input): InventoryIssue
    {
        $items = $input['items'] ?? [];
        if (! $items) {
            throw new InvalidArgumentException('Add at least one item to issue.');
        }

        return DB::transaction(function () use ($input, $items) {
            $store = InventoryStore::query()->findOrFail($input['store_id']);
            $issue = InventoryIssue::query()->create([
                'hospital_id' => $store->hospital_id,
                'reference' => $this->reference('ISS'),
                'store_id' => $store->id,
                'to_store_id' => $input['to_store_id'] ?? null,
                'department_id' => $input['department_id'] ?? null,
                'patient_id' => $input['patient_id'] ?? null,
                'encounter_id' => $input['encounter_id'] ?? null,
                'prescription_id' => $input['prescription_id'] ?? null,
                'request_id' => $input['request_id'] ?? null,
                'kind' => $input['kind'] ?? 'department',
                'created_by' => $input['created_by'] ?? null,
                'occurred_at' => $input['occurred_at'] ?? now(),
                'notes' => $input['notes'] ?? null,
            ]);

            foreach ($items as $row) {
                $item = InventoryItem::query()->findOrFail($row['item_id']);
                $this->guardControlled($item, $input['allow_controlled'] ?? false);
                $this->issueLine($issue, $item, (float) $row['quantity'], $row['batch_id'] ?? null, $input);
            }

            if (! empty($input['request_id'])) {
                InventoryRequest::query()->whereKey($input['request_id'])->update(['status' => 'issued']);
            }

            Audit::record('created', $issue);

            return $issue->load(['store', 'toStore', 'department', 'patient', 'items.item', 'items.batch']);
        });
    }

    public function dispensePrescription(Prescription $prescription, string $userId, bool $allowControlled): void
    {
        $store = InventoryStore::query()
            ->where('hospital_id', $prescription->hospital_id)
            ->where('is_default', true)
            ->first()
            ?? InventoryStore::query()->where('hospital_id', $prescription->hospital_id)->where('type', 'pharmacy')->first();

        if (! $store) {
            return;
        }

        $lines = [];
        foreach ($prescription->items as $rxItem) {
            $item = InventoryItem::query()->where('medication_id', $rxItem->medication_id)->first();
            if (! $item) {
                continue;
            }
            $lines[] = [
                'item_id' => $item->id,
                'quantity' => $rxItem->quantity,
            ];
        }

        if (! $lines) {
            return;
        }

        $this->issue([
            'store_id' => $store->id,
            'patient_id' => $prescription->patient_id,
            'encounter_id' => $prescription->encounter_id,
            'prescription_id' => $prescription->id,
            'kind' => 'dispense',
            'created_by' => $userId,
            'allow_controlled' => $allowControlled,
            'items' => $lines,
        ]);
    }

    public function adjustMedication(Medication $medication, int $delta, string $userId, bool $allowControlled): void
    {
        $item = InventoryItem::query()->where('medication_id', $medication->id)->first();
        if (! $item || $delta === 0) {
            return;
        }

        $store = InventoryStore::query()
            ->where('hospital_id', $medication->hospital_id)
            ->where('is_default', true)
            ->first()
            ?? InventoryStore::query()->where('hospital_id', $medication->hospital_id)->first();

        if (! $store) {
            return;
        }

        $this->adjustment([
            'store_id' => $store->id,
            'reason' => 'correction',
            'created_by' => $userId,
            'allow_controlled' => $allowControlled,
            'items' => [[
                'item_id' => $item->id,
                'quantity' => abs($delta),
                'direction' => $delta > 0 ? 'in' : 'out',
                'batch_id' => $this->openBatchId($item, $store->id, $delta > 0),
            ]],
        ]);
    }

    public function stockReturn(array $input): InventoryReturn
    {
        $items = $input['items'] ?? [];
        if (! $items) {
            throw new InvalidArgumentException('Add at least one returned item.');
        }

        return DB::transaction(function () use ($input, $items) {
            $from = InventoryStore::query()->findOrFail($input['from_store_id']);
            $to = InventoryStore::query()->findOrFail($input['to_store_id']);
            $return = InventoryReturn::query()->create([
                'hospital_id' => $from->hospital_id,
                'reference' => $this->reference('RTN'),
                'from_store_id' => $from->id,
                'to_store_id' => $to->id,
                'issue_id' => $input['issue_id'] ?? null,
                'created_by' => $input['created_by'] ?? null,
                'occurred_at' => $input['occurred_at'] ?? now(),
                'notes' => $input['notes'] ?? null,
            ]);

            foreach ($items as $row) {
                $item = InventoryItem::query()->findOrFail($row['item_id']);
                $this->guardControlled($item, $input['allow_controlled'] ?? false);
                $quantity = (float) $row['quantity'];
                [$sourceBatchId, $destinationBatchId] = $this->moveBatch($item, $from->id, $to->id, $row['batch_id'] ?? null, $quantity);

                $this->ledger->post([
                    'item_id' => $item->id,
                    'store_id' => $from->id,
                    'batch_id' => $sourceBatchId,
                    'type' => 'return_out',
                    'quantity' => $quantity,
                    'reference_type' => InventoryReturn::class,
                    'reference_id' => $return->id,
                    'created_by' => $input['created_by'] ?? null,
                    'occurred_at' => $return->occurred_at,
                ]);

                $this->ledger->post([
                    'item_id' => $item->id,
                    'store_id' => $to->id,
                    'batch_id' => $destinationBatchId,
                    'type' => 'return_in',
                    'quantity' => $quantity,
                    'reference_type' => InventoryReturn::class,
                    'reference_id' => $return->id,
                    'created_by' => $input['created_by'] ?? null,
                    'occurred_at' => $return->occurred_at,
                ]);

                InventoryReturnItem::query()->create([
                    'return_id' => $return->id,
                    'item_id' => $item->id,
                    'batch_id' => $sourceBatchId,
                    'quantity' => $quantity,
                ]);
            }

            Audit::record('created', $return);

            return $return->load(['fromStore', 'toStore', 'items.item', 'items.batch']);
        });
    }

    public function adjustment(array $input): InventoryAdjustment
    {
        $items = $input['items'] ?? [];
        if (! $items) {
            throw new InvalidArgumentException('Add at least one adjustment line.');
        }

        return DB::transaction(function () use ($input, $items) {
            $store = InventoryStore::query()->findOrFail($input['store_id']);
            $adjustment = InventoryAdjustment::query()->create([
                'hospital_id' => $store->hospital_id,
                'reference' => $this->reference('ADJ'),
                'store_id' => $store->id,
                'reason' => $input['reason'] ?? 'correction',
                'created_by' => $input['created_by'] ?? null,
                'occurred_at' => $input['occurred_at'] ?? now(),
                'notes' => $input['notes'] ?? null,
            ]);

            foreach ($items as $row) {
                $item = InventoryItem::query()->findOrFail($row['item_id']);
                $this->guardControlled($item, $input['allow_controlled'] ?? false);
                $direction = $row['direction'] ?? 'out';
                $quantity = (float) $row['quantity'];
                $batchId = $row['batch_id'] ?? null;
                if ($item->tracks_batch && ! $batchId) {
                    $batchId = $direction === 'in'
                        ? $this->openBatchId($item, $store->id, true)
                        : $this->nextAvailable($item->id, $store->id)->id;
                }
                $this->ledger->post([
                    'item_id' => $item->id,
                    'store_id' => $store->id,
                    'batch_id' => $batchId,
                    'type' => $direction === 'in' ? 'adjustment_in' : 'adjustment_out',
                    'quantity' => $quantity,
                    'reference_type' => InventoryAdjustment::class,
                    'reference_id' => $adjustment->id,
                    'created_by' => $input['created_by'] ?? null,
                    'occurred_at' => $adjustment->occurred_at,
                    'notes' => $input['notes'] ?? null,
                ]);

                InventoryAdjustmentItem::query()->create([
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $item->id,
                    'batch_id' => $batchId,
                    'quantity' => $quantity,
                    'direction' => $direction,
                ]);
            }

            Audit::record('created', $adjustment);

            return $adjustment->load(['store', 'items.item', 'items.batch']);
        });
    }

    public function count(array $input): InventoryCount
    {
        $items = $input['items'] ?? [];
        if (! $items) {
            throw new InvalidArgumentException('Add at least one counted item.');
        }

        return DB::transaction(function () use ($input, $items) {
            $store = InventoryStore::query()->findOrFail($input['store_id']);
            $count = InventoryCount::query()->create([
                'hospital_id' => $store->hospital_id,
                'reference' => $this->reference('CNT'),
                'store_id' => $store->id,
                'status' => 'posted',
                'created_by' => $input['created_by'] ?? null,
                'counted_at' => $input['counted_at'] ?? now(),
                'notes' => $input['notes'] ?? null,
            ]);

            foreach ($items as $row) {
                $item = InventoryItem::query()->findOrFail($row['item_id']);
                $this->guardControlled($item, $input['allow_controlled'] ?? false);
                $counted = (float) $row['counted_quantity'];
                $batchId = $row['batch_id'] ?? null;
                $system = $batchId
                    ? (float) InventoryBatch::query()->whereKey($batchId)->value('quantity')
                    : (float) InventoryBalance::query()->where('item_id', $item->id)->where('store_id', $store->id)->value('quantity');
                $variance = round($counted - $system, 3);

                if ($variance != 0.0 && $item->tracks_batch && ! $batchId) {
                    $batchId = $variance > 0
                        ? $this->openBatchId($item, $store->id, true)
                        : $this->nextAvailable($item->id, $store->id)->id;
                }

                InventoryCountItem::query()->create([
                    'count_id' => $count->id,
                    'item_id' => $item->id,
                    'batch_id' => $batchId,
                    'system_quantity' => $system,
                    'counted_quantity' => $counted,
                    'variance' => $variance,
                ]);

                if ($variance == 0.0) {
                    continue;
                }

                $this->ledger->post([
                    'item_id' => $item->id,
                    'store_id' => $store->id,
                    'batch_id' => $batchId,
                    'type' => $variance > 0 ? 'count_in' : 'count_out',
                    'quantity' => abs($variance),
                    'reference_type' => InventoryCount::class,
                    'reference_id' => $count->id,
                    'created_by' => $input['created_by'] ?? null,
                    'occurred_at' => $count->counted_at,
                    'notes' => 'Stock count variance',
                ]);
            }

            Audit::record('created', $count);

            return $count->load(['store', 'items.item', 'items.batch']);
        });
    }

    private function issueLine(InventoryIssue $issue, InventoryItem $item, float $quantity, ?string $batchId, array $input): void
    {
        $remaining = $quantity;
        $type = ($input['kind'] ?? 'department') === 'dispense' ? 'dispense' : 'issue';

        if (! $item->tracks_batch) {
            $this->ledger->post([
                'item_id' => $item->id,
                'store_id' => $issue->store_id,
                'type' => $type,
                'quantity' => $remaining,
                'reference_type' => InventoryIssue::class,
                'reference_id' => $issue->id,
                'patient_id' => $issue->patient_id,
                'encounter_id' => $issue->encounter_id,
                'prescription_id' => $issue->prescription_id,
                'department_id' => $issue->department_id,
                'created_by' => $issue->created_by,
                'occurred_at' => $issue->occurred_at,
            ]);
            InventoryIssueItem::query()->create([
                'issue_id' => $issue->id,
                'item_id' => $item->id,
                'quantity' => $remaining,
            ]);

            return;
        }

        $batches = InventoryBatch::query()
            ->where('item_id', $item->id)
            ->where('store_id', $issue->store_id)
            ->where('quantity', '>', 0)
            ->where('status', 'available')
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now()->toDateString()))
            ->when($batchId, fn ($query, $id) => $query->where('id', $id))
            ->orderByRaw('case when expiry_date is null then 1 else 0 end')
            ->orderBy('expiry_date')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $this->ledger->assertIssuable($batch, $type);
            $take = min($remaining, (float) $batch->quantity);
            $this->ledger->post([
                'item_id' => $item->id,
                'store_id' => $issue->store_id,
                'batch_id' => $batch->id,
                'type' => $type,
                'quantity' => $take,
                'reference_type' => InventoryIssue::class,
                'reference_id' => $issue->id,
                'patient_id' => $issue->patient_id,
                'encounter_id' => $issue->encounter_id,
                'prescription_id' => $issue->prescription_id,
                'department_id' => $issue->department_id,
                'created_by' => $issue->created_by,
                'occurred_at' => $issue->occurred_at,
            ]);
            InventoryIssueItem::query()->create([
                'issue_id' => $issue->id,
                'item_id' => $item->id,
                'batch_id' => $batch->id,
                'quantity' => $take,
            ]);
            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > 0) {
            throw new InvalidArgumentException('Insufficient available stock for '.$item->name.'.');
        }
    }

    private function moveBatch(InventoryItem $item, string $fromStoreId, string $toStoreId, ?string $batchId, float $quantity): array
    {
        if (! $item->tracks_batch) {
            return [null, null];
        }

        $source = $batchId
            ? InventoryBatch::query()->lockForUpdate()->findOrFail($batchId)
            : $this->nextAvailable($item->id, $fromStoreId);

        if ($source->store_id !== $fromStoreId || $source->item_id !== $item->id) {
            throw new InvalidArgumentException('Batch does not belong to the source store.');
        }

        $destination = InventoryBatch::query()->firstOrCreate(
            ['item_id' => $item->id, 'store_id' => $toStoreId, 'batch_number' => $source->batch_number],
            [
                'hospital_id' => $item->hospital_id,
                'supplier_id' => $source->supplier_id,
                'expiry_date' => $source->expiry_date,
                'quantity' => 0,
                'unit_cost' => $source->unit_cost,
                'status' => 'available',
                'received_at' => now(),
            ]
        );

        return [$source->id, $destination->id];
    }

    private function nextAvailable(string $itemId, string $storeId): InventoryBatch
    {
        $batch = InventoryBatch::query()
            ->where('item_id', $itemId)
            ->where('store_id', $storeId)
            ->where('quantity', '>', 0)
            ->where('status', 'available')
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now()->toDateString()))
            ->orderByRaw('case when expiry_date is null then 1 else 0 end')
            ->orderBy('expiry_date')
            ->lockForUpdate()
            ->first();

        if (! $batch) {
            throw new InvalidArgumentException('No available batch can be moved.');
        }

        return $batch;
    }

    private function openBatchId(InventoryItem $item, string $storeId, bool $inbound): ?string
    {
        if (! $item->tracks_batch) {
            return null;
        }

        $existing = InventoryBatch::query()
            ->where('item_id', $item->id)
            ->where('store_id', $storeId)
            ->when(! $inbound, fn ($query) => $query->where('quantity', '>', 0))
            ->orderByDesc('received_at')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        if (! $inbound) {
            return null;
        }

        $batch = InventoryBatch::query()->create([
            'hospital_id' => $item->hospital_id,
            'item_id' => $item->id,
            'store_id' => $storeId,
            'batch_number' => 'ADJ-'.strtoupper(Str::random(4)),
            'expiry_date' => $item->tracks_expiry ? now()->addYear()->toDateString() : null,
            'quantity' => 0,
            'unit_cost' => $item->cost_price,
            'status' => 'available',
            'received_at' => now(),
        ]);

        return $batch->id;
    }

    private function guardControlled(InventoryItem $item, bool $allowed): void
    {
        if ($item->is_controlled && ! $allowed) {
            throw new InvalidArgumentException('Controlled inventory requires additional authorization.');
        }
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
    }
}
