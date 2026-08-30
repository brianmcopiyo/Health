<?php

namespace App\Support\Exports;

use App\Models\Ambulance;
use App\Models\AssistanceRequest;
use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryReceipt;
use App\Models\InventoryRequest;
use App\Models\InventoryReturn;
use App\Models\InventoryAdjustment;
use App\Models\InventoryCount;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\InventoryTransfer;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Models\InventoryLocation;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\User;
use App\Support\Access;
use App\Support\Access\UserQuery;
use App\Support\InvoiceReport;
use Illuminate\Http\Request;

class ExportCatalog
{
    public static function definition(string $key): ?array
    {
        $all = self::all();

        return $all[$key] ?? null;
    }

    public static function all(): array
    {
        return [
            'patients' => [
                'key' => 'patients',
                'title' => 'Patients',
                'label' => 'Patient register',
                'subject' => 'Patient',
                'kind' => 'list',
                'filter_labels' => ['q' => 'Search', 'status' => 'Status'],
                'columns' => [
                    ['title' => 'MRN', 'key' => 'mrn'],
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Sex', 'key' => 'sex', 'format' => 'status'],
                    ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ['title' => 'Registered', 'key' => 'registered', 'format' => 'date'],
                ],
                'query' => function (Request $request, User $user) {
                    $query = Access::patientQuery($user, Patient::query()->latest());
                    if ($q = $request->string('q')->toString()) {
                        $query->search($q);
                    }
                    if ($status = $request->string('status')->toString()) {
                        $query->where('status', $status);
                    }

                    return $query;
                },
                'map' => fn (Patient $patient) => [
                    'mrn' => $patient->mrn,
                    'name' => trim($patient->first_name.' '.$patient->last_name),
                    'sex' => $patient->sex,
                    'status' => $patient->status,
                    'registered' => optional($patient->created_at)?->toDateString(),
                ],
            ],
            'encounters' => [
                'key' => 'encounters',
                'title' => 'Encounters',
                'label' => 'Visit register',
                'subject' => 'Patient',
                'kind' => 'list',
                'authorize' => fn (User $user) => $user->hasPermission('read', 'Opd')
                    || $user->hasPermission('read', 'Emergency')
                    || $user->hasPermission('read', 'Ward')
                    || $user->hasPermission('read', 'Patient'),
                'filter_labels' => ['q' => 'Search', 'type' => 'Type', 'status' => 'Status', 'open' => 'Open only'],
                'columns' => [
                    ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                    ['title' => 'Patient', 'key' => 'patient'],
                    ['title' => 'Clinician', 'key' => 'clinician'],
                    ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ['title' => 'Opened', 'key' => 'opened', 'format' => 'datetime'],
                ],
                'query' => function (Request $request, User $user) {
                    $type = $request->string('type')->toString() ?: null;
                    $query = Access::encounterQuery($user, Encounter::query()
                        ->with(['patient:id,first_name,last_name,mrn', 'clinician:id,name'])
                        ->latest(), $type);
                    if ($type) {
                        $query->where('type', $type);
                    }
                    if ($request->boolean('open')) {
                        $query->whereIn('status', ['waiting', 'in_progress']);
                    }
                    if ($status = $request->string('status')->toString()) {
                        $query->where('status', $status);
                    }

                    return $query;
                },
                'map' => fn (Encounter $encounter) => [
                    'type' => $encounter->type,
                    'patient' => $encounter->patient ? trim($encounter->patient->first_name.' '.$encounter->patient->last_name) : '—',
                    'clinician' => $encounter->clinician?->name,
                    'status' => $encounter->status,
                    'opened' => optional($encounter->created_at)?->toIso8601String(),
                ],
            ],
            'invoices' => [
                'key' => 'invoices',
                'title' => 'Invoices',
                'label' => 'Billing register',
                'subject' => 'Invoice',
                'kind' => 'list',
                'filter_labels' => ['status' => 'Status', 'patient_id' => 'Patient'],
                'columns' => [
                    ['title' => 'Number', 'key' => 'number'],
                    ['title' => 'Patient', 'key' => 'patient'],
                    ['title' => 'Total', 'key' => 'total', 'format' => 'currency'],
                    ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ['title' => 'Issued', 'key' => 'issued', 'format' => 'date'],
                ],
                'query' => function (Request $request) {
                    $query = Invoice::query()->with('patient:id,first_name,last_name,mrn')->latest();
                    if ($status = $request->string('status')->toString()) {
                        $query->where('status', $status);
                    }
                    if ($request->filled('patient_id')) {
                        $query->where('patient_id', $request->string('patient_id'));
                    }

                    return $query;
                },
                'map' => fn (Invoice $invoice) => [
                    'number' => $invoice->number,
                    'patient' => $invoice->patient ? trim($invoice->patient->first_name.' '.$invoice->patient->last_name) : '—',
                    'total' => $invoice->total,
                    'status' => $invoice->status,
                    'issued' => optional($invoice->issued_at ?? $invoice->created_at)?->toDateString(),
                ],
            ],
            'invoice-reports' => [
                'key' => 'invoice-reports',
                'title' => 'Sales reports',
                'label' => 'Billing report',
                'subject' => 'Invoice',
                'kind' => 'report',
                'filter_labels' => ['from' => 'From', 'to' => 'To'],
                'report' => function (Request $request) {
                    $payload = app(InvoiceReport::class)->build($request);
                    $tables = [];
                    foreach ([
                        'by_date' => 'By date',
                        'by_service' => 'By service',
                        'by_category' => 'By category',
                        'by_customer' => 'By patient',
                        'by_branch' => 'By hospital',
                        'by_user' => 'By user',
                        'by_payment_method' => 'By payment method',
                        'discounts' => 'Discounts',
                        'refunds' => 'Refunds',
                    ] as $key => $title) {
                        $tables[] = [
                            'title' => $title,
                            'columns' => [
                                ['title' => 'Label', 'key' => 'label'],
                                ['title' => 'Count', 'key' => 'count', 'format' => 'number'],
                                ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                                ['title' => 'Amount', 'key' => 'amount', 'format' => 'currency'],
                            ],
                            'rows' => $payload[$key] ?? [],
                        ];
                    }

                    return [...$payload, 'tables' => $tables];
                },
            ],
            'referrals' => [
                'key' => 'referrals',
                'title' => 'Referrals',
                'label' => 'Referral register',
                'subject' => 'Referral',
                'kind' => 'list',
                'filter_labels' => ['status' => 'Status', 'direction' => 'Direction'],
                'columns' => [
                    ['title' => 'Patient', 'key' => 'patient'],
                    ['title' => 'Destination', 'key' => 'destination'],
                    ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ['title' => 'Requested', 'key' => 'requested', 'format' => 'datetime'],
                ],
                'query' => function (Request $request) {
                    $query = Referral::query()->with(['patient:id,first_name,last_name', 'toHospital:id,name'])->latest();
                    if ($status = $request->string('status')->toString()) {
                        $query->where('status', $status);
                    }
                    if ($direction = $request->string('direction')->toString()) {
                        $query->where('direction', $direction);
                    }

                    return $query;
                },
                'map' => fn (Referral $referral) => [
                    'patient' => $referral->patient ? trim($referral->patient->first_name.' '.$referral->patient->last_name) : '—',
                    'destination' => $referral->toHospital?->name,
                    'status' => $referral->status,
                    'requested' => optional($referral->created_at)?->toIso8601String(),
                ],
            ],
            'inventory-items' => self::inventoryList(
                'inventory-items',
                'Inventory items',
                fn (Request $request) => tap(InventoryItem::query()->with(['category', 'unit'])->withSum('balances as stock_quantity', 'quantity')->orderBy('name'), function ($query) use ($request) {
                    if ($q = $request->string('q')->toString()) {
                        $term = '%'.addcslashes($q, '%_').'%';
                        $query->where(fn ($builder) => $builder->where('name', 'like', $term)->orWhere('sku', 'like', $term));
                    }
                    if ($request->filled('kind')) {
                        $query->where('kind', $request->string('kind'));
                    }
                }),
                fn (InventoryItem $item) => [
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'kind' => $item->kind,
                    'stock' => $item->stock_quantity,
                    'price' => $item->unit_price,
                ],
                [
                    ['title' => 'SKU', 'key' => 'sku'],
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Kind', 'key' => 'kind', 'format' => 'status'],
                    ['title' => 'Stock', 'key' => 'stock', 'format' => 'number'],
                    ['title' => 'Price', 'key' => 'price', 'format' => 'currency'],
                ],
                ['q' => 'Search', 'kind' => 'Kind']
            ),
            'inventory-stock' => self::inventoryList(
                'inventory-stock',
                'Stock on hand',
                fn (Request $request) => tap(InventoryBalance::query()->with(['item', 'store'])->orderByDesc('quantity'), function ($query) use ($request) {
                    if ($q = $request->string('q')->toString()) {
                        $term = '%'.addcslashes($q, '%_').'%';
                        $query->whereHas('item', fn ($builder) => $builder->where('name', 'like', $term)->orWhere('sku', 'like', $term));
                    }
                    if ($request->filled('store_id')) {
                        $query->where('store_id', $request->string('store_id'));
                    }
                }),
                fn (InventoryBalance $balance) => [
                    'item' => $balance->item?->name,
                    'store' => $balance->store?->name,
                    'quantity' => $balance->quantity,
                ],
                [
                    ['title' => 'Item', 'key' => 'item'],
                    ['title' => 'Store', 'key' => 'store'],
                    ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                ],
                ['q' => 'Search', 'store_id' => 'Store']
            ),
            'inventory-movements' => self::inventoryList(
                'inventory-movements',
                'Stock movements',
                fn (Request $request) => tap(InventoryMovement::query()->with(['item', 'store'])->latest('occurred_at'), function ($query) use ($request) {
                    if ($request->filled('type')) {
                        $query->where('type', $request->string('type'));
                    }
                    if ($request->filled('store_id')) {
                        $query->where('store_id', $request->string('store_id'));
                    }
                }),
                fn (InventoryMovement $movement) => [
                    'item' => $movement->item?->name,
                    'store' => $movement->store?->name,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'when' => optional($movement->occurred_at)?->toIso8601String(),
                ],
                [
                    ['title' => 'Item', 'key' => 'item'],
                    ['title' => 'Store', 'key' => 'store'],
                    ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                    ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                    ['title' => 'When', 'key' => 'when', 'format' => 'datetime'],
                ],
                ['type' => 'Type', 'store_id' => 'Store']
            ),
            'inventory-batches' => self::inventoryList(
                'inventory-batches',
                'Batches',
                fn (Request $request) => tap(InventoryBatch::query()->with(['item', 'store'])->orderBy('expiry_date'), function ($query) use ($request) {
                    if ($request->boolean('expiring')) {
                        $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', now()->addDays(90))->where('quantity', '>', 0);
                    }
                    if ($request->filled('status')) {
                        $query->where('status', $request->string('status'));
                    }
                }),
                fn (InventoryBatch $batch) => [
                    'item' => $batch->item?->name,
                    'batch' => $batch->batch_number,
                    'store' => $batch->store?->name,
                    'quantity' => $batch->quantity,
                    'expiry' => optional($batch->expiry_date)?->toDateString(),
                    'status' => $batch->status,
                ],
                [
                    ['title' => 'Item', 'key' => 'item'],
                    ['title' => 'Batch', 'key' => 'batch'],
                    ['title' => 'Store', 'key' => 'store'],
                    ['title' => 'Quantity', 'key' => 'quantity', 'format' => 'number'],
                    ['title' => 'Expiry', 'key' => 'expiry', 'format' => 'date'],
                    ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                ],
                ['status' => 'Status', 'expiring' => 'Expiring']
            ),
            'inventory-receipts' => self::inventoryNamed('inventory-receipts', 'Receipts', InventoryReceipt::class, 'received_at'),
            'inventory-transfers' => self::inventoryNamed('inventory-transfers', 'Transfers', InventoryTransfer::class, 'occurred_at'),
            'inventory-issues' => self::inventoryNamed('inventory-issues', 'Issues', InventoryIssue::class, 'occurred_at'),
            'inventory-requests' => self::inventoryNamed('inventory-requests', 'Requests', InventoryRequest::class, 'requested_at'),
            'inventory-returns' => self::inventoryNamed('inventory-returns', 'Returns', InventoryReturn::class, 'occurred_at'),
            'inventory-adjustments' => self::inventoryNamed('inventory-adjustments', 'Adjustments', InventoryAdjustment::class, 'occurred_at'),
            'inventory-counts' => self::inventoryNamed('inventory-counts', 'Stock counts', InventoryCount::class, 'counted_at'),
            'inventory-suppliers' => self::inventoryList(
                'inventory-suppliers',
                'Suppliers',
                fn () => InventorySupplier::query()->orderBy('name'),
                fn (InventorySupplier $supplier) => [
                    'name' => $supplier->name,
                    'phone' => $supplier->phone,
                ],
                [
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Phone', 'key' => 'phone'],
                ],
                ['q' => 'Search']
            ),
            'inventory-stores' => self::inventoryList(
                'inventory-stores',
                'Stores',
                fn () => InventoryStore::query()->orderBy('name'),
                fn (InventoryStore $store) => [
                    'name' => $store->name,
                    'type' => $store->type,
                ],
                [
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                ],
                []
            ),
            'pharmacy' => [
                'key' => 'pharmacy',
                'title' => 'Prescriptions',
                'label' => 'Pharmacy register',
                'subject' => 'Pharmacy',
                'kind' => 'list',
                'filter_labels' => ['status' => 'Status', 'queue' => 'Queue'],
                'columns' => [
                    ['title' => 'Patient', 'key' => 'patient'],
                    ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ['title' => 'Prescribed', 'key' => 'prescribed', 'format' => 'datetime'],
                ],
                'query' => function (Request $request) {
                    $query = Prescription::query()->with('patient:id,first_name,last_name')->latest();
                    if ($status = $request->string('status')->toString()) {
                        $query->where('status', $status);
                    }
                    if ($request->boolean('queue')) {
                        $query->whereIn('status', ['pending', 'verified']);
                    }

                    return $query;
                },
                'map' => fn (Prescription $rx) => [
                    'patient' => $rx->patient ? trim($rx->patient->first_name.' '.$rx->patient->last_name) : '—',
                    'status' => $rx->status,
                    'prescribed' => optional($rx->created_at)?->toIso8601String(),
                ],
            ],
            'facilities' => [
                'key' => 'facilities',
                'title' => 'Facilities',
                'label' => 'Facility register',
                'subject' => 'Facility',
                'kind' => 'list',
                'filter_labels' => ['q' => 'Search', 'status' => 'Status', 'facility_type_id' => 'Type', 'department_id' => 'Department'],
                'columns' => [
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ['title' => 'Capacity', 'key' => 'capacity', 'format' => 'number'],
                ],
                'query' => function (Request $request) {
                    $query = Facility::query()->orderBy('name');
                    if ($q = $request->string('q')->toString()) {
                        $term = '%'.addcslashes($q, '%_').'%';
                        $query->where(fn ($builder) => $builder->where('name', 'like', $term));
                    }
                    if ($status = $request->string('status')->toString()) {
                        $query->where('status', $status);
                    }
                    if ($request->filled('facility_type_id')) {
                        $query->where('facility_type_id', $request->string('facility_type_id'));
                    }
                    if ($request->filled('department_id')) {
                        $query->where('department_id', $request->string('department_id'));
                    }

                    return $query;
                },
                'map' => fn (Facility $facility) => [
                    'name' => $facility->name,
                    'status' => $facility->status,
                    'capacity' => $facility->capacity,
                ],
            ],
            'departments' => [
                'key' => 'departments',
                'title' => 'Departments',
                'label' => 'Department register',
                'subject' => 'Department',
                'kind' => 'list',
                'filter_labels' => [],
                'columns' => [
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Slug', 'key' => 'slug'],
                ],
                'query' => fn () => Department::query()->orderBy('name'),
                'map' => fn (Department $department) => [
                    'name' => $department->name,
                    'slug' => $department->slug,
                ],
            ],
            'users' => [
                'key' => 'users',
                'title' => 'Users',
                'label' => 'Staff directory',
                'subject' => 'User',
                'kind' => 'list',
                'filter_labels' => ['q' => 'Search', 'status' => 'Status', 'role_id' => 'Role', 'sort' => 'Sort'],
                'columns' => [
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Role', 'key' => 'role'],
                    ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                ],
                'query' => function (Request $request, User $user) {
                    $query = User::query()->with(['role:id,name', 'hospital:id,name']);
                    if (! $user->isPlatformAdmin()) {
                        $query->where(function ($builder) use ($user) {
                            $builder->where('hospital_id', $user->hospital_id)
                                ->orWhereHas('memberships', fn ($memberships) => $memberships->where('hospital_id', $user->hospital_id));
                        })->whereDoesntHave('role', fn ($role) => $role->where('slug', 'platform-admin'));
                    }
                    UserQuery::apply($query, $request);

                    return $query;
                },
                'map' => fn (User $user) => [
                    'name' => $user->name,
                    'role' => $user->role?->name,
                    'status' => $user->status,
                ],
            ],
            'hospitals' => [
                'key' => 'hospitals',
                'title' => 'Hospitals',
                'label' => 'Hospital registry',
                'subject' => 'Hospital',
                'kind' => 'list',
                'authorize' => fn (User $user) => $user->isPlatformAdmin(),
                'filter_labels' => [],
                'columns' => [
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'City', 'key' => 'city'],
                    ['title' => 'Region', 'key' => 'region'],
                    ['title' => 'Active', 'key' => 'active', 'format' => 'status'],
                ],
                'query' => fn () => Hospital::query()->orderBy('name'),
                'map' => fn (Hospital $hospital) => [
                    'name' => $hospital->name,
                    'city' => $hospital->city,
                    'region' => $hospital->region,
                    'active' => $hospital->is_active ? 'Yes' : 'No',
                ],
            ],
            'ambulances' => [
                'key' => 'ambulances',
                'title' => 'Ambulances',
                'label' => 'Ambulance fleet',
                'subject' => 'Ambulance',
                'kind' => 'list',
                'filter_labels' => ['status' => 'Status'],
                'columns' => [
                    ['title' => 'Vehicle', 'key' => 'vehicle'],
                    ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                    ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                    ['title' => 'Capacity', 'key' => 'capacity', 'format' => 'number'],
                ],
                'query' => function (Request $request) {
                    $query = Ambulance::query()->orderBy('vehicle_code');
                    if ($status = $request->string('status')->toString()) {
                        $query->where('status', $status);
                    }

                    return $query;
                },
                'map' => fn (Ambulance $ambulance) => [
                    'vehicle' => $ambulance->vehicle_code,
                    'type' => $ambulance->vehicle_type,
                    'status' => $ambulance->status,
                    'capacity' => $ambulance->capacity,
                ],
            ],
            'assistance' => [
                'key' => 'assistance',
                'title' => 'Assistance requests',
                'label' => 'Assistance register',
                'subject' => 'AssistanceRequest',
                'kind' => 'list',
                'filter_labels' => ['status' => 'Status', 'direction' => 'Direction'],
                'columns' => [
                    ['title' => 'Title', 'key' => 'title'],
                    ['title' => 'Type', 'key' => 'type', 'format' => 'status'],
                    ['title' => 'From', 'key' => 'from'],
                    ['title' => 'To', 'key' => 'to'],
                    ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                ],
                'query' => function (Request $request, User $user) {
                    $query = AssistanceRequest::query()->with(['fromHospital:id,name', 'toHospital:id,name'])->latest();
                    if ($status = $request->string('status')->toString()) {
                        $query->where('status', $status);
                    }
                    $direction = $request->string('direction')->toString();
                    if ($direction === 'incoming' && $user->hospital_id) {
                        $query->where('to_hospital_id', $user->hospital_id);
                    }
                    if ($direction === 'outgoing' && $user->hospital_id) {
                        $query->where('from_hospital_id', $user->hospital_id);
                    }

                    return $query;
                },
                'map' => fn (AssistanceRequest $request) => [
                    'title' => $request->title,
                    'type' => $request->type,
                    'from' => $request->fromHospital?->name,
                    'to' => $request->toHospital?->name,
                    'status' => $request->status,
                ],
            ],
            'inventory-categories' => self::inventoryList(
                'inventory-categories',
                'Inventory categories',
                fn (Request $request) => tap(InventoryCategory::query()->orderBy('name'), function ($query) use ($request) {
                    if ($q = $request->string('q')->toString()) {
                        $term = '%'.addcslashes($q, '%_').'%';
                        $query->where(fn ($builder) => $builder->where('name', 'like', $term)->orWhere('slug', 'like', $term));
                    }
                }),
                fn (InventoryCategory $category) => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
                [
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Slug', 'key' => 'slug'],
                ],
                ['q' => 'Search']
            ),
            'inventory-units' => self::inventoryList(
                'inventory-units',
                'Inventory units',
                fn () => InventoryUnit::query()->orderBy('name'),
                fn (InventoryUnit $unit) => [
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                ],
                [
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Symbol', 'key' => 'symbol'],
                ],
                []
            ),
            'inventory-locations' => self::inventoryList(
                'inventory-locations',
                'Stock locations',
                fn (Request $request) => tap(InventoryLocation::query()->with('store')->orderBy('name'), function ($query) use ($request) {
                    if ($q = $request->string('q')->toString()) {
                        $term = '%'.addcslashes($q, '%_').'%';
                        $query->where(fn ($builder) => $builder->where('name', 'like', $term));
                    }
                    if ($request->filled('store_id')) {
                        $query->where('store_id', $request->string('store_id'));
                    }
                }),
                fn (InventoryLocation $location) => [
                    'name' => $location->name,
                    'store' => $location->store?->name,
                ],
                [
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Store', 'key' => 'store'],
                ],
                ['q' => 'Search', 'store_id' => 'Store']
            ),
        ];
    }

    private static function inventoryList(string $key, string $title, callable $query, callable $map, array $columns, array $filters): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'label' => 'Inventory export',
            'subject' => 'Inventory',
            'kind' => 'list',
            'authorize' => fn (User $user) => $user->hasPermission('read', 'Inventory') || $user->hasPermission('read', 'Pharmacy'),
            'filter_labels' => $filters,
            'columns' => $columns,
            'query' => fn (Request $request) => $query($request),
            'map' => $map,
        ];
    }

    private static function inventoryNamed(string $key, string $title, string $model, string $when): array
    {
        return self::inventoryList(
            $key,
            $title,
            fn (Request $request) => tap($model::query()->latest($when), function ($query) use ($request) {
                if ($q = $request->string('q')->toString()) {
                    $term = '%'.addcslashes($q, '%_').'%';
                    $query->where(fn ($builder) => $builder->where('reference', 'like', $term)->orWhere('notes', 'like', $term));
                }
                if ($request->filled('status')) {
                    $query->where('status', $request->string('status'));
                }
            }),
            fn ($row) => [
                'reference' => $row->reference ?? $row->number ?? $row->id,
                'status' => $row->status ?? 'posted',
                'when' => optional($row->{$when} ?? $row->created_at)?->toIso8601String(),
            ],
            [
                ['title' => 'Reference', 'key' => 'reference'],
                ['title' => 'Status', 'key' => 'status', 'format' => 'status'],
                ['title' => 'When', 'key' => 'when', 'format' => 'datetime'],
            ],
            ['q' => 'Search', 'status' => 'Status']
        );
    }
}
