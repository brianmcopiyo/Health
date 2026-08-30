<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryCategory;
use App\Models\InventoryCount;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryReceipt;
use App\Models\InventoryRequest;
use App\Models\InventoryReturn;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\InventoryTransfer;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitConversion;
use App\Support\Audit;
use App\Support\InventoryPoster;
use App\Support\InventoryStatus;
use App\Support\QueryList;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class InventoryController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->authorizeInventory($request, 'read');
        $warning = now()->addDays(90)->toDateString();

        $balances = InventoryBalance::query()->with(['item.unit', 'store'])->where('quantity', '>', 0)->get();
        $value = InventoryBatch::query()->selectRaw('coalesce(sum(quantity * unit_cost), 0) as value')->value('value');
        $low = InventoryItem::query()->withSum('balances as stock_quantity', 'quantity')->where('is_active', true)->where('reorder_level', '>', 0)->get()
            ->filter(fn (InventoryItem $item) => (float) ($item->stock_quantity ?? 0) <= $item->reorder_level);

        $expiring = InventoryBatch::query()->with(['item', 'store'])->where('quantity', '>', 0)->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now()->toDateString())->whereDate('expiry_date', '<=', $warning);
        $expired = InventoryBatch::query()->with(['item', 'store'])->where('quantity', '>', 0)->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString());

        return [
            'stock_value' => (int) $value,
            'items_in_stock' => $balances->count(),
            'low_stock' => ['count' => $low->count(), 'items' => $low->take(8)->values()],
            'expiring' => ['count' => (clone $expiring)->count(), 'items' => (clone $expiring)->orderBy('expiry_date')->limit(8)->get()],
            'expired' => ['count' => (clone $expired)->count(), 'items' => (clone $expired)->orderBy('expiry_date')->limit(8)->get()],
            'recent_receipts' => InventoryReceipt::query()->with(['store', 'supplier'])->latest('received_at')->limit(8)->get(),
            'recent_movements' => InventoryMovement::query()->with(['item', 'store'])->latest('occurred_at')->limit(8)->get(),
            'recent_transfers' => InventoryTransfer::query()->with(['fromStore', 'toStore'])->latest('occurred_at')->limit(8)->get(),
            'attention' => [
                'low_stock' => $low->count(),
                'expiring' => (clone $expiring)->count(),
                'expired' => (clone $expired)->count(),
                'requests' => InventoryRequest::query()->where('status', 'requested')->count(),
            ],
        ];
    }

    public function items(Request $request)
    {
        $this->authorizeInventory($request, 'read');
        $query = InventoryItem::query()->with(['category', 'unit', 'medication', 'batches' => fn ($builder) => $builder->where('quantity', '>', 0)])->withSum('balances as stock_quantity', 'quantity')->orderBy('name');
        if ($search = $request->string('q')->toString()) {
            $term = '%'.addcslashes($search, '%_').'%';
            $query->where(fn ($builder) => $builder->where('name', 'like', $term)->orWhere('sku', 'like', $term));
        }
        if ($request->filled('kind')) {
            $query->where('kind', $request->string('kind'));
        }
        $paginator = QueryList::paginate($query, $request);
        $paginator->getCollection()->transform(fn (InventoryItem $item) => $this->serializeItem($item));

        return $paginator;
    }

    public function showItem(InventoryItem $item)
    {
        $this->authorizeInventory(request(), 'read');
        $item->load(['category', 'unit', 'medication', 'batches' => fn ($builder) => $builder->where('quantity', '>', 0)])->loadSum('balances as stock_quantity', 'quantity');

        return [
            ...$this->serializeItem($item),
            'balances' => $item->balances()->with('store')->get(),
            'batches' => $item->batches()->with('store')->where('quantity', '>', 0)->orderBy('expiry_date')->get(),
            'movements' => $item->movements()->with('store')->latest('occurred_at')->limit(20)->get(),
        ];
    }

    public function storeItem(Request $request)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:80', Rule::unique('inventory_items', 'sku')->where('hospital_id', $request->user()->hospital_id)],
            'kind' => ['required', Rule::in(['medicine', 'supply', 'consumable', 'equipment'])],
            'category_id' => ['nullable', TenantRules::inHospital('inventory_categories')],
            'unit_id' => ['required', TenantRules::inHospital('inventory_units')],
            'medication_id' => ['nullable', TenantRules::inHospital('medications')],
            'form' => ['nullable', 'string', 'max:80'],
            'strength' => ['nullable', 'string', 'max:80'],
            'unit_price' => ['nullable', 'integer', 'min:0'],
            'cost_price' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'tracks_batch' => ['boolean'],
            'tracks_expiry' => ['boolean'],
            'is_controlled' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $data['hospital_id'] = $request->user()->hospital_id;
        $data['tracks_batch'] = $request->boolean('tracks_batch', true);
        $data['tracks_expiry'] = $request->boolean('tracks_expiry', true);
        $data['is_controlled'] = $request->boolean('is_controlled');
        $data['is_active'] = $request->boolean('is_active', true);
        $item = InventoryItem::query()->create($data);
        Audit::record('created', $item);

        return response()->json($this->serializeItem($item->load(['category', 'unit'])), 201);
    }

    public function categories()
    {
        $this->authorizeInventory(request(), 'read');

        return InventoryCategory::query()->withCount('items')->orderBy('sort_order')->orderBy('name')->get();
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', TenantRules::inHospital('inventory_categories')],
        ]);
        $slug = Str::slug($data['name']);
        if (InventoryCategory::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }
        $category = InventoryCategory::query()->create([
            'hospital_id' => $request->user()->hospital_id,
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'slug' => $slug,
            'sort_order' => InventoryCategory::query()->max('sort_order') + 1,
            'is_active' => true,
        ]);
        Audit::record('created', $category);

        return response()->json($category, 201);
    }

    public function units()
    {
        $this->authorizeInventory(request(), 'read');

        return InventoryUnit::query()->with(['conversionsFrom.toUnit'])->orderBy('name')->get();
    }

    public function storeUnit(Request $request)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'symbol' => ['required', 'string', 'max:20', Rule::unique('inventory_units', 'symbol')->where('hospital_id', $request->user()->hospital_id)],
        ]);
        $data['hospital_id'] = $request->user()->hospital_id;
        $data['is_active'] = true;
        $unit = InventoryUnit::query()->create($data);
        Audit::record('created', $unit);

        return response()->json($unit, 201);
    }

    public function storeConversion(Request $request)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'from_unit_id' => ['required', TenantRules::inHospital('inventory_units')],
            'to_unit_id' => ['required', TenantRules::inHospital('inventory_units'), 'different:from_unit_id'],
            'factor' => ['required', 'numeric', 'gt:0'],
        ]);
        $data['hospital_id'] = $request->user()->hospital_id;
        $conversion = InventoryUnitConversion::query()->create($data);
        Audit::record('created', $conversion);

        return response()->json($conversion->load(['fromUnit', 'toUnit']), 201);
    }

    public function suppliers()
    {
        $this->authorizeInventory(request(), 'read');

        return InventorySupplier::query()->orderBy('name')->get();
    }

    public function storeSupplier(Request $request)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
        ]);
        $data['hospital_id'] = $request->user()->hospital_id;
        $data['is_active'] = true;
        $supplier = InventorySupplier::query()->create($data);
        Audit::record('created', $supplier);

        return response()->json($supplier, 201);
    }

    public function stores()
    {
        $this->authorizeInventory(request(), 'read');

        return InventoryStore::query()->with(['department', 'facility'])->orderBy('name')->get();
    }

    public function showStore(InventoryStore $store)
    {
        $this->authorizeInventory(request(), 'read');

        return [
            ...$store->toArray(),
            'department' => $store->department,
            'locations' => $store->locations,
            'stock' => $store->balances()->with('item.unit')->where('quantity', '>', 0)->orderByDesc('quantity')->limit(30)->get(),
            'batches' => $store->batches()->with('item')->where('quantity', '>', 0)->orderBy('expiry_date')->limit(20)->get(),
            'movements' => $store->movements()->with('item')->latest('occurred_at')->limit(20)->get(),
        ];
    }

    public function storeStore(Request $request)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['warehouse', 'pharmacy', 'department', 'ward'])],
            'department_id' => ['nullable', TenantRules::inHospital('departments')],
            'facility_id' => ['nullable', TenantRules::inHospital('facilities')],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $data['hospital_id'] = $request->user()->hospital_id;
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active', true);
        $store = InventoryStore::query()->create($data);
        if ($store->is_default) {
            InventoryStore::query()->where('hospital_id', $store->hospital_id)->where('id', '!=', $store->id)->update(['is_default' => false]);
        }
        Audit::record('created', $store);

        return response()->json($store, 201);
    }

    public function locations(Request $request)
    {
        $this->authorizeInventory($request, 'read');
        $query = InventoryLocation::query()->with('store')->orderBy('name');
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->string('store_id'));
        }

        return QueryList::paginate($query, $request);
    }

    public function storeLocation(Request $request)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'store_id' => ['required', TenantRules::inHospital('inventory_stores')],
            'name' => ['required', 'string', 'max:120'],
        ]);
        $data['hospital_id'] = $request->user()->hospital_id;
        $data['is_active'] = true;
        $location = InventoryLocation::query()->create($data);
        Audit::record('created', $location);

        return response()->json($location->load('store'), 201);
    }

    public function stock(Request $request)
    {
        $this->authorizeInventory($request, 'read');
        $query = InventoryBalance::query()->with(['item.unit', 'item.batches' => fn ($builder) => $builder->where('quantity', '>', 0), 'store'])->orderByDesc('quantity');
        if ($search = $request->string('q')->toString()) {
            $term = '%'.addcslashes($search, '%_').'%';
            $query->whereHas('item', fn ($builder) => $builder->where('name', 'like', $term)->orWhere('sku', 'like', $term));
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->string('store_id'));
        }
        if ($request->boolean('low_stock')) {
            $query->whereHas('item', fn ($builder) => $builder->whereColumn('inventory_balances.quantity', '<=', 'inventory_items.reorder_level')->where('reorder_level', '>', 0));
        }
        $paginator = QueryList::paginate($query, $request);
        $paginator->getCollection()->transform(function (InventoryBalance $balance) {
            return [
                ...$balance->toArray(),
                'status' => InventoryStatus::forBalance($balance),
                'value' => InventoryStatus::valuation($balance),
            ];
        });

        return $paginator;
    }

    public function batches(Request $request)
    {
        $this->authorizeInventory($request, 'read');
        $query = InventoryBatch::query()->with(['item', 'store'])->orderBy('expiry_date');
        if ($request->boolean('expiring')) {
            $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', now()->addDays(90))->where('quantity', '>', 0);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return QueryList::paginate($query, $request);
    }

    public function showBatch(InventoryBatch $batch)
    {
        $this->authorizeInventory(request(), 'read');

        return $batch->load(['item', 'store', 'location', 'movements']);
    }

    public function updateBatch(Request $request, InventoryBatch $batch)
    {
        $this->authorizeInventory($request, 'update');
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['available', 'reserved', 'expired', 'depleted', 'quarantined'])],
            'expiry_date' => ['nullable', 'date'],
        ]);
        $batch->fill($data)->save();
        Audit::record('updated', $batch);

        return $batch->refresh()->load(['item', 'store']);
    }

    public function movements(Request $request)
    {
        $this->authorizeInventory($request, 'read');
        $query = InventoryMovement::query()->with(['item', 'store', 'batch'])->latest('occurred_at');
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->string('store_id'));
        }

        return QueryList::paginate($query, $request);
    }

    public function receipts(Request $request)
    {
        $this->authorizeInventory($request, 'read');

        return QueryList::paginate(InventoryReceipt::query()->with(['store', 'supplier'])->latest('received_at'), $request);
    }

    public function showReceipt(InventoryReceipt $receipt)
    {
        $this->authorizeInventory(request(), 'read');

        return $receipt->load(['store', 'supplier', 'items.item', 'items.batch']);
    }

    public function storeReceipt(Request $request, InventoryPoster $poster)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'store_id' => ['required', TenantRules::inHospital('inventory_stores')],
            'supplier_id' => ['nullable', TenantRules::inHospital('inventory_suppliers')],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', TenantRules::inHospital('inventory_items')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'integer', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:80'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.location_id' => ['nullable', TenantRules::inHospital('inventory_locations')],
        ]);
        $data['created_by'] = $request->user()->id;
        $data['allow_controlled'] = $this->canControl($request);

        return $this->posted(fn () => $poster->receive($data), 201);
    }

    public function transfers(Request $request)
    {
        $this->authorizeInventory($request, 'read');

        return QueryList::paginate(InventoryTransfer::query()->with(['fromStore', 'toStore'])->latest('occurred_at'), $request);
    }

    public function showTransfer(InventoryTransfer $transfer)
    {
        $this->authorizeInventory(request(), 'read');

        return $transfer->load(['fromStore', 'toStore', 'items.item', 'items.batch']);
    }

    public function storeTransfer(Request $request, InventoryPoster $poster)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'from_store_id' => ['required', TenantRules::inHospital('inventory_stores')],
            'to_store_id' => ['required', TenantRules::inHospital('inventory_stores')],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', TenantRules::inHospital('inventory_items')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.batch_id' => ['nullable', TenantRules::inHospital('inventory_batches')],
        ]);
        $data['created_by'] = $request->user()->id;
        $data['allow_controlled'] = $this->canControl($request);

        return $this->posted(fn () => $poster->transfer($data), 201);
    }

    public function requests(Request $request)
    {
        $this->authorizeInventory($request, 'read');

        return QueryList::paginate(InventoryRequest::query()->with(['toStore', 'department'])->latest('requested_at'), $request);
    }

    public function showRequest(InventoryRequest $inventoryRequest)
    {
        $this->authorizeInventory(request(), 'read');

        return $inventoryRequest->load(['toStore', 'fromStore', 'department', 'items.item']);
    }

    public function storeRequest(Request $request, InventoryPoster $poster)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'to_store_id' => ['required', TenantRules::inHospital('inventory_stores')],
            'from_store_id' => ['nullable', TenantRules::inHospital('inventory_stores')],
            'department_id' => ['nullable', TenantRules::inHospital('departments')],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', TenantRules::inHospital('inventory_items')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $data['requested_by'] = $request->user()->id;

        return $this->posted(fn () => $poster->request($data), 201);
    }

    public function issues(Request $request)
    {
        $this->authorizeInventory($request, 'read');

        return QueryList::paginate(InventoryIssue::query()->with(['store', 'department', 'patient'])->latest('occurred_at'), $request);
    }

    public function showIssue(InventoryIssue $issue)
    {
        $this->authorizeInventory(request(), 'read');

        return $issue->load(['store', 'toStore', 'department', 'patient', 'encounter', 'prescription', 'items.item', 'items.batch']);
    }

    public function storeIssue(Request $request, InventoryPoster $poster)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'store_id' => ['required', TenantRules::inHospital('inventory_stores')],
            'to_store_id' => ['nullable', TenantRules::inHospital('inventory_stores')],
            'department_id' => ['nullable', TenantRules::inHospital('departments')],
            'patient_id' => ['nullable', TenantRules::inHospital('patients')],
            'encounter_id' => ['nullable', TenantRules::inHospital('encounters')],
            'prescription_id' => ['nullable', TenantRules::inHospital('prescriptions')],
            'request_id' => ['nullable', TenantRules::inHospital('inventory_requests')],
            'kind' => ['nullable', Rule::in(['department', 'ward', 'dispense'])],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', TenantRules::inHospital('inventory_items')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.batch_id' => ['nullable', TenantRules::inHospital('inventory_batches')],
        ]);
        $data['created_by'] = $request->user()->id;
        $data['allow_controlled'] = $this->canControl($request);

        return $this->posted(fn () => $poster->issue($data), 201);
    }

    public function returns(Request $request)
    {
        $this->authorizeInventory($request, 'read');

        return QueryList::paginate(InventoryReturn::query()->with(['fromStore', 'toStore'])->latest('occurred_at'), $request);
    }

    public function showReturn(InventoryReturn $return)
    {
        $this->authorizeInventory(request(), 'read');

        return $return->load(['fromStore', 'toStore', 'items.item', 'items.batch']);
    }

    public function storeReturn(Request $request, InventoryPoster $poster)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'from_store_id' => ['required', TenantRules::inHospital('inventory_stores')],
            'to_store_id' => ['required', TenantRules::inHospital('inventory_stores')],
            'issue_id' => ['nullable', TenantRules::inHospital('inventory_issues')],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', TenantRules::inHospital('inventory_items')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.batch_id' => ['nullable', TenantRules::inHospital('inventory_batches')],
        ]);
        $data['created_by'] = $request->user()->id;
        $data['allow_controlled'] = $this->canControl($request);

        return $this->posted(fn () => $poster->stockReturn($data), 201);
    }

    public function adjustments(Request $request)
    {
        $this->authorizeInventory($request, 'read');

        return QueryList::paginate(InventoryAdjustment::query()->with('store')->latest('occurred_at'), $request);
    }

    public function showAdjustment(InventoryAdjustment $adjustment)
    {
        $this->authorizeInventory(request(), 'read');

        return $adjustment->load(['store', 'items.item', 'items.batch']);
    }

    public function storeAdjustment(Request $request, InventoryPoster $poster)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'store_id' => ['required', TenantRules::inHospital('inventory_stores')],
            'reason' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', TenantRules::inHospital('inventory_items')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.direction' => ['required', 'in:in,out'],
            'items.*.batch_id' => ['nullable', TenantRules::inHospital('inventory_batches')],
        ]);
        $data['created_by'] = $request->user()->id;
        $data['allow_controlled'] = $this->canControl($request);

        return $this->posted(fn () => $poster->adjustment($data), 201);
    }

    public function counts(Request $request)
    {
        $this->authorizeInventory($request, 'read');

        return QueryList::paginate(InventoryCount::query()->with('store')->latest('counted_at'), $request);
    }

    public function showCount(InventoryCount $count)
    {
        $this->authorizeInventory(request(), 'read');

        return $count->load(['store', 'items.item', 'items.batch']);
    }

    public function storeCount(Request $request, InventoryPoster $poster)
    {
        $this->authorizeInventory($request, 'create');
        $data = $request->validate([
            'store_id' => ['required', TenantRules::inHospital('inventory_stores')],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', TenantRules::inHospital('inventory_items')],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.batch_id' => ['nullable', TenantRules::inHospital('inventory_batches')],
        ]);
        $data['created_by'] = $request->user()->id;
        $data['allow_controlled'] = $this->canControl($request);

        return $this->posted(fn () => $poster->count($data), 201);
    }

    private function serializeItem(InventoryItem $item): array
    {
        $qty = (float) ($item->stock_quantity ?? $item->onHand());

        return [
            ...$item->toArray(),
            'stock_quantity' => $qty,
            'status' => InventoryStatus::forItem($item, $qty),
        ];
    }

    private function authorizeInventory(Request $request, string $action): void
    {
        abort_unless(
            $request->user()->hasPermission($action, 'Inventory')
            || $request->user()->hasPermission('manage', 'Inventory')
            || $request->user()->hasPermission('manage', 'all')
            || ($action === 'read' && $request->user()->hasPermission('read', 'Pharmacy')),
            403,
            'This action is unauthorized.'
        );
    }

    private function canControl(Request $request): bool
    {
        $user = $request->user();

        return $user->hasPermission('approve', 'Inventory')
            || $user->hasPermission('manage', 'Inventory')
            || $user->hasPermission('manage', 'all');
    }

    private function posted(callable $callback, int $status = 200)
    {
        try {
            return response()->json($callback(), $status);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
    }
}
