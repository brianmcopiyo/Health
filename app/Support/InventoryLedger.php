<?php

namespace App\Support;

use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryLedger
{
    public function post(array $input): InventoryMovement
    {
        $type = $input['type'] ?? '';
        $quantity = $this->qty($input['quantity'] ?? 0);

        if (! in_array($type, [...InventoryMovement::INBOUND, ...InventoryMovement::OUTBOUND], true)) {
            throw new InvalidArgumentException('Unknown inventory movement type.');
        }

        if ($this->cmp($quantity, '0') <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($input, $type, $quantity) {
            $item = InventoryItem::query()->withoutGlobalScope('hospital')->lockForUpdate()->findOrFail($input['item_id']);
            $storeId = $input['store_id'];
            $inbound = in_array($type, InventoryMovement::INBOUND, true);
            $batch = null;

            if ($item->tracks_batch) {
                if (empty($input['batch_id'])) {
                    throw new InvalidArgumentException('A batch is required for this item.');
                }

                $batch = InventoryBatch::query()->withoutGlobalScope('hospital')->lockForUpdate()->findOrFail($input['batch_id']);
                if ($batch->item_id !== $item->id || $batch->store_id !== $storeId) {
                    throw new InvalidArgumentException('Batch does not match the item and store.');
                }

                if (! $inbound) {
                    $this->assertIssuable($batch, $type);
                }

                $next = $inbound ? $this->add($batch->quantity, $quantity) : $this->sub($batch->quantity, $quantity);
                if ($this->cmp($next, '0') < 0) {
                    throw new InvalidArgumentException('Insufficient batch quantity.');
                }

                $batch->quantity = $next;
                $batch->status = $this->batchStatus($batch, $next);
                $batch->save();
            } elseif (! empty($input['batch_id'])) {
                throw new InvalidArgumentException('This item does not track batches.');
            }

            $balance = InventoryBalance::query()->withoutGlobalScope('hospital')->lockForUpdate()->firstOrCreate(
                ['item_id' => $item->id, 'store_id' => $storeId],
                ['hospital_id' => $item->hospital_id, 'quantity' => '0'],
            );

            $nextBalance = $inbound ? $this->add($balance->quantity, $quantity) : $this->sub($balance->quantity, $quantity);
            if ($this->cmp($nextBalance, '0') < 0) {
                throw new InvalidArgumentException('Insufficient stock.');
            }

            $balance->quantity = $nextBalance;
            $balance->save();

            $this->syncMedication($item);

            return InventoryMovement::query()->create([
                'hospital_id' => $item->hospital_id,
                'item_id' => $item->id,
                'store_id' => $storeId,
                'location_id' => $input['location_id'] ?? $batch?->location_id,
                'batch_id' => $batch?->id,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $input['unit_cost'] ?? $item->cost_price,
                'balance_after' => $nextBalance,
                'reference_type' => $input['reference_type'] ?? null,
                'reference_id' => $input['reference_id'] ?? null,
                'patient_id' => $input['patient_id'] ?? null,
                'encounter_id' => $input['encounter_id'] ?? null,
                'prescription_id' => $input['prescription_id'] ?? null,
                'department_id' => $input['department_id'] ?? null,
                'notes' => $input['notes'] ?? null,
                'created_by' => $input['created_by'] ?? null,
                'occurred_at' => $input['occurred_at'] ?? now(),
            ]);
        });
    }

    public function assertIssuable(InventoryBatch $batch, string $type = 'issue'): void
    {
        if (in_array($type, InventoryMovement::WRITE_OFF, true)) {
            return;
        }

        if (in_array($batch->status, ['quarantined', 'reserved'], true)) {
            throw new InvalidArgumentException('This batch is '.$batch->status.' and cannot be issued.');
        }

        if ($batch->expiry_date && $batch->expiry_date->endOfDay()->isPast()) {
            throw new InvalidArgumentException('Expired stock cannot be dispensed, transferred or issued.');
        }
    }

    public function syncMedication(InventoryItem $item): void
    {
        if (! $item->medication_id) {
            return;
        }

        $qty = (int) round((float) InventoryBalance::query()->withoutGlobalScope('hospital')->where('item_id', $item->id)->sum('quantity'));
        $item->medication()->withoutGlobalScope('hospital')->update(['stock_qty' => max(0, $qty)]);
    }

    protected function batchStatus(InventoryBatch $batch, string $quantity): string
    {
        if ($this->cmp($quantity, '0') <= 0) {
            return 'depleted';
        }

        if (in_array($batch->status, ['quarantined', 'reserved'], true)) {
            return $batch->status;
        }

        if ($batch->expiry_date && $batch->expiry_date->endOfDay()->isPast()) {
            return 'expired';
        }

        return 'available';
    }

    protected function qty(mixed $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }

    protected function add(mixed $left, mixed $right): string
    {
        return $this->qty((float) $left + (float) $right);
    }

    protected function sub(mixed $left, mixed $right): string
    {
        return $this->qty((float) $left - (float) $right);
    }

    protected function cmp(mixed $left, mixed $right): int
    {
        return $this->qty($left) <=> $this->qty($right);
    }
}
