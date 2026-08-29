<?php

namespace App\Support\Exports;

use App\Models\Ambulance;
use App\Models\AmbulanceTrip;
use App\Models\Department;
use App\Models\Dispensing;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryCategory;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\InventoryIssue;
use App\Models\InventoryIssueItem;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptItem;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestItem;
use App\Models\InventoryReturn;
use App\Models\InventoryReturnItem;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\InventoryUnit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Referral;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Model;

class ExportRelations
{
    public static function supports(object|string $model): bool
    {
        $class = is_object($model) ? $model::class : $model;

        return isset(self::all()[$class]);
    }

    public static function schema(Model $model): array
    {
        return self::all()[$model::class] ?? [
            'title' => fn (Model $row) => (string) $row->getKey(),
            'facts' => fn () => [],
            'children' => [],
        ];
    }

    private static function all(): array
    {
        return [
            Patient::class => [
                'title' => fn (Patient $patient) => trim($patient->first_name.' '.$patient->last_name),
                'facts' => fn (Patient $patient) => self::facts([
                    ['label' => 'MRN', 'value' => $patient->mrn],
                    ['label' => 'Sex', 'value' => $patient->sex],
                    ['label' => 'Status', 'value' => $patient->status],
                    ['label' => 'Blood group', 'value' => $patient->blood_group],
                ]),
                'children' => [
                    self::child('encounters', 'Encounters', 'Patient', [
                        ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                        ['title' => 'Opened', 'key' => 'opened', 'format' => 'datetime'],
                    ], fn (Encounter $encounter) => [
                        'type' => $encounter->type,
                        'status' => $encounter->status,
                        'opened' => optional($encounter->created_at)?->toIso8601String(),
                    ], null, 'desc', true),
                    self::child('invoices', 'Invoices', 'Invoice', [
                        ['title' => 'Number', 'key' => 'number'],
                        ['title' => 'Total', 'key' => 'total', 'format' => 'currency'],
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ], fn (Invoice $invoice) => [
                        'number' => $invoice->number,
                        'total' => $invoice->total,
                        'status' => $invoice->status,
                    ], null, 'desc', true),
                    self::child('prescriptions', 'Prescriptions', 'Pharmacy', [
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                        ['title' => 'Prescribed', 'key' => 'prescribed', 'format' => 'datetime'],
                    ], fn (Prescription $rx) => [
                        'status' => $rx->status,
                        'prescribed' => optional($rx->prescribed_at ?? $rx->created_at)?->toIso8601String(),
                    ], null, 'desc', true),
                    self::child('referrals', 'Referrals', 'Referral', [
                        ['title' => 'Destination', 'key' => 'destination'],
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ], fn (Referral $referral) => [
                        'destination' => $referral->toHospital?->name,
                        'status' => $referral->status,
                    ]),
                    self::child('currentAllergies', 'Allergies', 'Patient', [
                        ['title' => 'Allergen', 'key' => 'allergen'],
                        ['title' => 'Severity', 'key' => 'severity', 'format' => 'status'],
                    ], fn ($allergy) => [
                        'allergen' => $allergy->allergen,
                        'severity' => $allergy->severity,
                    ]),
                ],
            ],
            Encounter::class => [
                'title' => fn (Encounter $encounter) => trim(($encounter->patient ? trim($encounter->patient->first_name.' '.$encounter->patient->last_name) : 'Encounter').' · '.($encounter->type ?? '')),
                'with' => ['patient', 'clinician'],
                'facts' => fn (Encounter $encounter) => self::facts([
                    ['label' => 'Type', 'value' => $encounter->type],
                    ['label' => 'Status', 'value' => $encounter->status],
                    ['label' => 'Clinician', 'value' => $encounter->clinician?->name],
                ]),
                'children' => [
                    self::child('invoices', 'Invoices', 'Invoice', [
                        ['title' => 'Number', 'key' => 'number'],
                        ['title' => 'Total', 'key' => 'total', 'format' => 'currency'],
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ], fn (Invoice $invoice) => [
                        'number' => $invoice->number,
                        'total' => $invoice->total,
                        'status' => $invoice->status,
                    ]),
                    self::child('prescriptions', 'Prescriptions', 'Pharmacy', [
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ], fn (Prescription $rx) => [
                        'status' => $rx->status,
                    ]),
                ],
            ],
            Invoice::class => [
                'title' => fn (Invoice $invoice) => $invoice->number,
                'with' => ['patient'],
                'facts' => fn (Invoice $invoice) => self::facts([
                    ['label' => 'Patient', 'value' => $invoice->patient ? trim($invoice->patient->first_name.' '.$invoice->patient->last_name) : null],
                    ['label' => 'Status', 'value' => $invoice->status],
                    ['label' => 'Total', 'value' => $invoice->total],
                    ['label' => 'Outstanding', 'value' => $invoice->outstanding()],
                ]),
                'children' => [
                    self::child('items', 'Line items', 'Invoice', [
                        ['title' => 'Description', 'key' => 'description'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                        ['title' => 'Amount', 'key' => 'amount', 'format' => 'currency'],
                    ], fn (InvoiceItem $item) => [
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'amount' => $item->amount,
                    ]),
                    self::child('payments', 'Payments', 'Invoice', [
                        ['title' => 'Method', 'key' => 'method', 'format' => 'status'],
                        ['title' => 'Amount', 'key' => 'amount', 'format' => 'currency'],
                    ], fn (Payment $payment) => [
                        'method' => $payment->method,
                        'amount' => $payment->amount,
                    ]),
                    self::child('refunds', 'Refunds', 'Invoice', [
                        ['title' => 'Amount', 'key' => 'amount', 'format' => 'currency'],
                    ], fn (Refund $refund) => [
                        'amount' => $refund->amount,
                    ], 'occurred_at'),
                ],
            ],
            Prescription::class => [
                'title' => fn (Prescription $rx) => $rx->patient ? trim($rx->patient->first_name.' '.$rx->patient->last_name) : 'Prescription',
                'with' => ['patient'],
                'facts' => fn (Prescription $rx) => self::facts([
                    ['label' => 'Status', 'value' => $rx->status],
                ]),
                'children' => [
                    self::child('items', 'Medications', 'Pharmacy', [
                        ['title' => 'Medication', 'key' => 'medication'],
                        ['title' => 'Dose', 'key' => 'dose'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                    ], fn (PrescriptionItem $item) => [
                        'medication' => $item->medication?->name,
                        'dose' => $item->dose,
                        'quantity' => $item->quantity,
                    ]),
                    self::child('dispensings', 'Dispensing', 'Pharmacy', [
                        ['title' => 'Medication', 'key' => 'medication'],
                        ['title' => 'When', 'key' => 'when', 'format' => 'datetime'],
                    ], fn (Dispensing $row) => [
                        'medication' => $row->medication?->name,
                        'when' => optional($row->dispensed_at ?? $row->created_at)?->toIso8601String(),
                    ]),
                ],
            ],
            InventoryItem::class => [
                'title' => fn (InventoryItem $item) => $item->name,
                'with' => ['category', 'unit'],
                'facts' => fn (InventoryItem $item) => self::facts([
                    ['label' => 'SKU', 'value' => $item->sku],
                    ['label' => 'Kind', 'value' => $item->kind],
                    ['label' => 'Category', 'value' => $item->category?->name],
                    ['label' => 'Unit price', 'value' => $item->unit_price],
                    ['label' => 'Active', 'value' => $item->is_active ? 'Yes' : 'No'],
                ]),
                'children' => [
                    self::child('balances', 'Stock by store', 'Inventory', [
                        ['title' => 'Store', 'key' => 'store'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                    ], fn (InventoryBalance $balance) => [
                        'store' => $balance->store?->name,
                        'quantity' => $balance->quantity,
                    ], 'quantity'),
                    self::child('batches', 'Batches', 'Inventory', [
                        ['title' => 'Batch', 'key' => 'batch'],
                        ['title' => 'Store', 'key' => 'store'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                        ['title' => 'Expiry', 'key' => 'expiry', 'format' => 'date'],
                    ], fn (InventoryBatch $batch) => [
                        'batch' => $batch->batch_number,
                        'store' => $batch->store?->name,
                        'quantity' => $batch->quantity,
                        'expiry' => optional($batch->expiry_date)?->toDateString(),
                    ], 'received_at', 'desc', true),
                    self::child('movements', 'Stock movements', 'Inventory', [
                        ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                        ['title' => 'Store', 'key' => 'store'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                        ['title' => 'When', 'key' => 'when', 'format' => 'datetime'],
                    ], fn (InventoryMovement $movement) => [
                        'type' => $movement->type,
                        'store' => $movement->store?->name,
                        'quantity' => $movement->quantity,
                        'when' => optional($movement->occurred_at)?->toIso8601String(),
                    ], 'occurred_at'),
                ],
            ],
            InventoryBatch::class => [
                'title' => fn (InventoryBatch $batch) => $batch->batch_number,
                'facts' => fn (InventoryBatch $batch) => self::facts([
                    ['label' => 'Item', 'value' => $batch->item?->name],
                    ['label' => 'Store', 'value' => $batch->store?->name],
                    ['label' => 'Quantity', 'value' => $batch->quantity],
                ]),
                'children' => [
                    self::child('movements', 'Stock movements', 'Inventory', [
                        ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                    ], fn (InventoryMovement $movement) => [
                        'type' => $movement->type,
                        'quantity' => $movement->quantity,
                    ], 'occurred_at'),
                ],
            ],
            InventoryCategory::class => [
                'title' => fn (InventoryCategory $category) => $category->name,
                'facts' => fn (InventoryCategory $category) => self::facts([
                    ['label' => 'Slug', 'value' => $category->slug],
                ]),
                'children' => [
                    self::child('children', 'Subcategories', 'Inventory', [
                        ['title' => 'Name', 'key' => 'name'],
                    ], fn (InventoryCategory $category) => [
                        'name' => $category->name,
                    ], 'name', 'asc'),
                    self::child('items', 'Inventory items', 'Inventory', [
                        ['title' => 'SKU', 'key' => 'sku'],
                        ['title' => 'Name', 'key' => 'name'],
                    ], fn (InventoryItem $item) => [
                        'sku' => $item->sku,
                        'name' => $item->name,
                    ], 'name', 'asc'),
                ],
            ],
            InventoryUnit::class => [
                'title' => fn (InventoryUnit $unit) => $unit->name,
                'facts' => fn (InventoryUnit $unit) => self::facts([
                    ['label' => 'Symbol', 'value' => $unit->symbol],
                ]),
                'children' => [
                    self::child('items', 'Inventory items', 'Inventory', [
                        ['title' => 'SKU', 'key' => 'sku'],
                        ['title' => 'Name', 'key' => 'name'],
                    ], fn (InventoryItem $item) => [
                        'sku' => $item->sku,
                        'name' => $item->name,
                    ], 'name', 'asc'),
                ],
            ],
            InventoryStore::class => [
                'title' => fn (InventoryStore $store) => $store->name,
                'facts' => fn (InventoryStore $store) => self::facts([
                    ['label' => 'Code', 'value' => $store->code],
                    ['label' => 'Type', 'value' => $store->type],
                ]),
                'children' => [
                    self::child('balances', 'Stock', 'Inventory', [
                        ['title' => 'Item', 'key' => 'item'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                    ], fn (InventoryBalance $balance) => [
                        'item' => $balance->item?->name,
                        'quantity' => $balance->quantity,
                    ], 'quantity'),
                    self::child('batches', 'Batches', 'Inventory', [
                        ['title' => 'Batch', 'key' => 'batch'],
                        ['title' => 'Item', 'key' => 'item'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                    ], fn (InventoryBatch $batch) => [
                        'batch' => $batch->batch_number,
                        'item' => $batch->item?->name,
                        'quantity' => $batch->quantity,
                    ], 'received_at', 'desc', true),
                    self::child('movements', 'Stock movements', 'Inventory', [
                        ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                        ['title' => 'Item', 'key' => 'item'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                    ], fn (InventoryMovement $movement) => [
                        'type' => $movement->type,
                        'item' => $movement->item?->name,
                        'quantity' => $movement->quantity,
                    ], 'occurred_at'),
                    self::child('locations', 'Locations', 'Inventory', [
                        ['title' => 'Name', 'key' => 'name'],
                        ['title' => 'Code', 'key' => 'code'],
                    ], fn (InventoryLocation $location) => [
                        'name' => $location->name,
                        'code' => $location->code,
                    ], 'name', 'asc'),
                ],
            ],
            InventoryLocation::class => [
                'title' => fn (InventoryLocation $location) => $location->name,
                'facts' => fn (InventoryLocation $location) => self::facts([
                    ['label' => 'Code', 'value' => $location->code],
                    ['label' => 'Store', 'value' => $location->store?->name],
                ]),
                'children' => [
                    self::child('batches', 'Batches', 'Inventory', [
                        ['title' => 'Batch', 'key' => 'batch'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                    ], fn (InventoryBatch $batch) => [
                        'batch' => $batch->batch_number,
                        'quantity' => $batch->quantity,
                    ]),
                ],
            ],
            InventorySupplier::class => [
                'title' => fn (InventorySupplier $supplier) => $supplier->name,
                'facts' => fn (InventorySupplier $supplier) => self::facts([
                    ['label' => 'Code', 'value' => $supplier->code],
                    ['label' => 'Phone', 'value' => $supplier->phone],
                ]),
                'children' => [
                    self::child('receipts', 'Goods received', 'Inventory', [
                        ['title' => 'Reference', 'key' => 'reference'],
                        ['title' => 'Store', 'key' => 'store'],
                    ], fn (InventoryReceipt $receipt) => [
                        'reference' => $receipt->reference,
                        'store' => $receipt->store?->name,
                    ], 'received_at', 'desc', true),
                ],
            ],
            InventoryReceipt::class => [
                'title' => fn (InventoryReceipt $receipt) => $receipt->reference,
                'facts' => fn (InventoryReceipt $receipt) => self::facts([
                    ['label' => 'Store', 'value' => $receipt->store?->name],
                ]),
                'children' => [
                    self::child('items', 'Line items', 'Inventory', [
                        ['title' => 'Item', 'key' => 'item'],
                        ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                    ], fn (InventoryReceiptItem $item) => [
                        'item' => $item->item?->name,
                        'quantity' => $item->quantity,
                    ]),
                ],
            ],
            InventoryTransfer::class => [
                'title' => fn (InventoryTransfer $transfer) => $transfer->reference,
                'facts' => fn (InventoryTransfer $transfer) => self::facts([
                    ['label' => 'From', 'value' => $transfer->fromStore?->name],
                    ['label' => 'To', 'value' => $transfer->toStore?->name],
                ]),
                'children' => [self::lines('items', fn (InventoryTransferItem $item) => ['item' => $item->item?->name, 'quantity' => $item->quantity])],
            ],
            InventoryIssue::class => [
                'title' => fn (InventoryIssue $issue) => $issue->reference,
                'facts' => fn (InventoryIssue $issue) => self::facts([
                    ['label' => 'Store', 'value' => $issue->store?->name],
                ]),
                'children' => [self::lines('items', fn (InventoryIssueItem $item) => ['item' => $item->item?->name, 'quantity' => $item->quantity])],
            ],
            InventoryRequest::class => [
                'title' => fn (InventoryRequest $request) => $request->reference,
                'facts' => fn (InventoryRequest $request) => self::facts([
                    ['label' => 'Status', 'value' => $request->status],
                ]),
                'children' => [self::lines('items', fn (InventoryRequestItem $item) => ['item' => $item->item?->name, 'quantity' => $item->quantity])],
            ],
            InventoryReturn::class => [
                'title' => fn (InventoryReturn $row) => $row->reference,
                'facts' => fn (InventoryReturn $row) => self::facts([
                    ['label' => 'From', 'value' => $row->fromStore?->name],
                ]),
                'children' => [self::lines('items', fn (InventoryReturnItem $item) => ['item' => $item->item?->name, 'quantity' => $item->quantity])],
            ],
            InventoryAdjustment::class => [
                'title' => fn (InventoryAdjustment $row) => $row->reference,
                'facts' => fn (InventoryAdjustment $row) => self::facts([
                    ['label' => 'Store', 'value' => $row->store?->name],
                ]),
                'children' => [self::lines('items', fn (InventoryAdjustmentItem $item) => ['item' => $item->item?->name, 'quantity' => $item->quantity])],
            ],
            InventoryCount::class => [
                'title' => fn (InventoryCount $count) => $count->reference,
                'facts' => fn (InventoryCount $count) => self::facts([
                    ['label' => 'Status', 'value' => $count->status],
                ]),
                'children' => [
                    self::child('items', 'Line items', 'Inventory', [
                        ['title' => 'Item', 'key' => 'item'],
                        ['title' => 'Counted', 'key' => 'quantity', 'format' => 'number'],
                    ], fn (InventoryCountItem $item) => [
                        'item' => $item->item?->name,
                        'quantity' => $item->counted_quantity,
                    ]),
                ],
            ],
            Facility::class => [
                'title' => fn (Facility $facility) => $facility->name,
                'facts' => fn (Facility $facility) => self::facts([
                    ['label' => 'Code', 'value' => $facility->code],
                    ['label' => 'Status', 'value' => $facility->status],
                    ['label' => 'Capacity', 'value' => $facility->capacity],
                ]),
                'children' => [
                    self::child('beds', 'Beds', 'Facility', [
                        ['title' => 'Name', 'key' => 'name'],
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ], fn (Facility $bed) => [
                        'name' => $bed->name,
                        'status' => $bed->status,
                    ], 'name', 'asc'),
                    self::child('encounters', 'Encounters', 'Patient', [
                        ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ], fn (Encounter $encounter) => [
                        'type' => $encounter->type,
                        'status' => $encounter->status,
                    ]),
                ],
            ],
            Department::class => [
                'title' => fn (Department $department) => $department->name,
                'facts' => fn (Department $department) => self::facts([
                    ['label' => 'Slug', 'value' => $department->slug],
                ]),
                'children' => [
                    self::child('facilities', 'Facilities', 'Facility', [
                        ['title' => 'Name', 'key' => 'name'],
                        ['title' => 'Code', 'key' => 'code'],
                    ], fn (Facility $facility) => [
                        'name' => $facility->name,
                        'code' => $facility->code,
                    ], 'name', 'asc'),
                    self::child('encounters', 'Encounters', 'Patient', [
                        ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ], fn (Encounter $encounter) => [
                        'type' => $encounter->type,
                        'status' => $encounter->status,
                    ]),
                ],
            ],
            Hospital::class => [
                'title' => fn (Hospital $hospital) => $hospital->name,
                'facts' => fn (Hospital $hospital) => self::facts([
                    ['label' => 'Code', 'value' => $hospital->code],
                    ['label' => 'City', 'value' => $hospital->city],
                ]),
                'children' => [
                    self::child('departments', 'Departments', 'Department', [
                        ['title' => 'Name', 'key' => 'name'],
                    ], fn (Department $department) => [
                        'name' => $department->name,
                    ], 'name', 'asc'),
                    self::child('facilities', 'Facilities', 'Facility', [
                        ['title' => 'Name', 'key' => 'name'],
                        ['title' => 'Code', 'key' => 'code'],
                    ], fn (Facility $facility) => [
                        'name' => $facility->name,
                        'code' => $facility->code,
                    ], 'name', 'asc'),
                    self::child('ambulances', 'Ambulances', 'Ambulance', [
                        ['title' => 'Vehicle', 'key' => 'vehicle'],
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ], fn (Ambulance $ambulance) => [
                        'vehicle' => $ambulance->vehicle_code,
                        'status' => $ambulance->status,
                    ]),
                ],
            ],
            Ambulance::class => [
                'title' => fn (Ambulance $ambulance) => $ambulance->vehicle_code,
                'facts' => fn (Ambulance $ambulance) => self::facts([
                    ['label' => 'Type', 'value' => $ambulance->vehicle_type],
                    ['label' => 'Status', 'value' => $ambulance->status],
                ]),
                'children' => [
                    self::child('trips', 'Trips', 'Ambulance', [
                        ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ], fn (AmbulanceTrip $trip) => [
                        'status' => $trip->status,
                    ]),
                ],
            ],
        ];
    }

    private static function lines(string $relation, callable $map): array
    {
        return self::child($relation, 'Line items', 'Inventory', [
            ['title' => 'Item', 'key' => 'item'],
            ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
        ], $map);
    }

    private static function child(
        string $relation,
        string $title,
        string $subject,
        array $columns,
        callable $map,
        ?string $order = null,
        string $direction = 'desc',
        bool $nest = false,
        ?string $through = null,
    ): array {
        return compact('relation', 'title', 'subject', 'columns', 'map', 'order', 'direction', 'nest', 'through');
    }

    private static function facts(array $items): array
    {
        return array_values(array_filter($items, fn ($item) => ($item['value'] ?? null) !== null && $item['value'] !== ''));
    }
}
