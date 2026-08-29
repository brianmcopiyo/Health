<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalService;
use App\Models\InventoryItem;
use App\Models\Medication;
use App\Models\PriceHistory;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\PricingRule;
use App\Models\ServicePackage;
use App\Models\TaxRate;
use App\Support\Audit;
use App\Support\PriceResolver;
use App\Support\QueryList;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PricingController extends Controller
{
    public function quote(Request $request, PriceResolver $resolver)
    {
        $data = $request->validate([
            'service_id' => ['nullable', TenantRules::inHospital('clinical_services')],
            'medication_id' => ['nullable', TenantRules::inHospital('medications')],
            'inventory_item_id' => ['nullable', TenantRules::inHospital('inventory_items')],
            'package_id' => ['nullable', TenantRules::inHospital('service_packages')],
            'quantity' => ['required', 'integer', 'min:1'],
            'patient_id' => ['nullable', TenantRules::inHospital('patients')],
            'payer_type' => ['nullable', 'in:self_pay,insurance'],
            'price_list_id' => ['nullable', TenantRules::inHospital('price_lists')],
            'override' => ['boolean'],
            'override_reason' => ['required_if:override,true', 'string', 'min:3'],
            'unit_amount' => ['required_if:override,true', 'nullable', 'integer', 'min:0'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'integer', 'min:0'],
        ]);
        $user = $request->user();
        $data['override'] = $request->boolean('override');
        $data['allow_override'] = $data['override'] && $user->hasPermission('override', 'Invoice');
        $data['allow_discount'] = $user->hasPermission('discount', 'Invoice');
        $data['allow_exception'] = $user->hasPermission('approve', 'Invoice');

        try {
            return $resolver->quote($data);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }
    }

    public function history(Request $request)
    {
        return QueryList::paginate(PriceHistory::query()->with('changer')->latest(), $request);
    }

    public function lists(Request $request)
    {
        $query = PriceList::query()->withCount('items')->orderBy('name');
        if ($search = $request->string('q')->toString()) {
            $query->where('name', 'like', '%'.addcslashes($search, '%_').'%');
        }

        return QueryList::paginate($query, $request);
    }

    public function showList(PriceList $priceList)
    {
        return $priceList->load(['patient', 'department', 'items']);
    }

    public function storeList(Request $request)
    {
        $list = PriceList::query()->create($this->listData($request));
        $this->syncDefault($list);
        Audit::record('created', $list);

        return response()->json($list, 201);
    }

    public function updateList(Request $request, PriceList $priceList)
    {
        $priceList->fill($this->listData($request, $priceList))->save();
        $this->syncDefault($priceList);

        return $priceList;
    }

    public function storeItem(Request $request, PriceList $priceList)
    {
        $data = $request->validate([
            'billable_type' => ['required', 'in:service,medication,inventory,package'],
            'billable_id' => ['required', 'uuid'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'max_quantity' => ['nullable', 'integer', 'min:1'],
            'unit_price' => ['required', 'integer', 'min:0'],
        ]);
        $data['min_quantity'] = $data['min_quantity'] ?? 1;
        $item = $priceList->items()->create($data);

        return response()->json($item, 201);
    }

    public function rules(Request $request)
    {
        return QueryList::paginate(PricingRule::query()->orderBy('priority')->orderBy('name'), $request);
    }

    public function storeRule(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:discount_percent,discount_fixed,override,promotional'],
            'scope' => ['required', 'in:all,service,category,patient,product'],
            'billable_type' => ['nullable', 'in:service,medication,inventory,package'],
            'billable_id' => ['nullable', 'uuid'],
            'service_category' => ['nullable', 'string', 'max:40'],
            'patient_id' => ['nullable', TenantRules::inHospital('patients')],
            'value' => ['required', 'integer', 'min:0'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);
        $data['priority'] = $data['priority'] ?? 100;
        $data['is_active'] = $request->boolean('is_active', true);
        $rule = PricingRule::query()->create($data);

        return response()->json($rule, 201);
    }

    public function taxes(Request $request)
    {
        return QueryList::paginate(TaxRate::query()->orderBy('name'), $request);
    }

    public function storeTax(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'rate' => ['required', 'integer', 'min:0'],
            'is_inclusive' => ['boolean'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $data['is_inclusive'] = $request->boolean('is_inclusive', false);
        $data['is_default'] = $request->boolean('is_default', false);
        $data['is_active'] = $request->boolean('is_active', true);
        $tax = TaxRate::query()->create($data);
        if ($tax->is_default) {
            TaxRate::query()->where('id', '!=', $tax->id)->update(['is_default' => false]);
        }

        return response()->json($tax, 201);
    }

    public function packages(Request $request)
    {
        return QueryList::paginate(ServicePackage::query()->with('items.service')->withCount('items')->orderBy('name'), $request);
    }

    public function storePackage(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.service_id' => ['required', TenantRules::inHospital('clinical_services')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        $package = ServicePackage::query()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? Str::upper(Str::slug($data['name'], '-')),
            'unit_price' => $data['unit_price'],
            'is_active' => true,
        ]);
        foreach ($data['items'] ?? [] as $item) {
            $package->items()->create($item);
        }

        return response()->json($package->load('items.service'), 201);
    }

    public function services(Request $request)
    {
        return ClinicalService::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'category', 'unit_price']);
    }

    public function catalog()
    {
        return [
            'services' => ClinicalService::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'category', 'unit_price']),
            'medications' => Medication::query()->orderBy('name')->get(['id', 'name', 'form', 'strength', 'sku', 'unit_price']),
            'inventory' => InventoryItem::query()->with('unit:id,name,symbol')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'kind', 'unit_id', 'unit_price', 'form', 'strength']),
            'packages' => ServicePackage::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'unit_price']),
        ];
    }

    private function listData(Request $request, ?PriceList $list = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'kind' => ['required', 'in:self_pay,insurance,customer,promotional,department'],
            'patient_id' => ['nullable', TenantRules::inHospital('patients')],
            'department_id' => ['nullable', TenantRules::inHospital('departments')],
            'tax_inclusive' => ['boolean'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);
        $data['code'] = $data['code'] ?? Str::upper(Str::slug($data['name'], '-'));
        $data['tax_inclusive'] = $request->boolean('tax_inclusive', $list?->tax_inclusive ?? false);
        $data['is_default'] = $request->boolean('is_default', $list?->is_default ?? false);
        $data['is_active'] = $request->boolean('is_active', $list?->is_active ?? true);

        return $data;
    }

    private function syncDefault(PriceList $list): void
    {
        if ($list->is_default) {
            PriceList::query()->where('id', '!=', $list->id)->update(['is_default' => false]);
        }
    }
}
