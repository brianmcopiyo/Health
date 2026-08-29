<?php

namespace App\Support;

use App\Models\ClinicalService;
use App\Models\InventoryItem;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\PricingRule;
use App\Models\ServicePackage;
use App\Models\TaxRate;
use InvalidArgumentException;

class PriceResolver
{
    public function quote(array $context): array
    {
        $billable = $this->billable($context);
        $quantity = max(1, (int) ($context['quantity'] ?? 1));
        $at = isset($context['at']) ? \Carbon\Carbon::parse($context['at']) : now();
        $patient = $this->patient($context);
        $payer = $context['payer_type'] ?? 'self_pay';
        $departmentId = $context['department_id'] ?? $billable['department_id'] ?? null;
        $tax = $this->tax($context);
        $list = $this->selectList($billable, $patient, $payer, $departmentId, $at, $context['price_list_id'] ?? null);
        $match = $list ? $this->matchItem($list, $billable, $quantity, $at) : null;
        $source = 'base';
        $listPrice = $billable['unit_price'];

        if ($match) {
            $listPrice = (int) $match->unit_price;
            $source = $list->kind === 'promotional' ? 'promo' : 'list';
        }

        $rule = $this->bestRule($billable, $patient, $departmentId, $list?->id, $quantity, $at);
        $discountAmount = 0;
        $discountPercent = 0;
        $unitPrice = $listPrice;

        if ($rule) {
            if ($rule->type === 'override') {
                $unitPrice = (int) $rule->value;
                $source = 'rule';
            } elseif ($rule->type === 'discount_percent') {
                $discountPercent = (int) $rule->value;
                $discountAmount = (int) round($listPrice * $quantity * ($discountPercent / 100));
                $unitPrice = $quantity > 0 ? (int) round((($listPrice * $quantity) - $discountAmount) / $quantity) : $listPrice;
            } elseif (in_array($rule->type, ['discount_fixed', 'promotional'], true)) {
                $discountAmount = min((int) $rule->value, $listPrice * $quantity);
                $unitPrice = $quantity > 0 ? (int) round((($listPrice * $quantity) - $discountAmount) / $quantity) : $listPrice;
            }
            if ($rule->min_price !== null && $unitPrice < (int) $rule->min_price) {
                $unitPrice = (int) $rule->min_price;
                $discountAmount = max(0, ($listPrice * $quantity) - ($unitPrice * $quantity));
            }
        }

        if (($context['discount_amount'] ?? 0) > 0 || ($context['discount_percent'] ?? 0) > 0) {
            if (! ($context['allow_discount'] ?? false)) {
                throw new InvalidArgumentException('Applying a discount requires authorization.');
            }
            $manualPercent = (int) ($context['discount_percent'] ?? 0);
            $manualAmount = (int) ($context['discount_amount'] ?? 0);
            if ($manualPercent > 0) {
                $manualAmount = (int) round($listPrice * $quantity * ($manualPercent / 100));
            }
            $discountAmount += $manualAmount;
            $discountPercent = $listPrice > 0 ? (int) round(($discountAmount / ($listPrice * $quantity)) * 100) : 0;
            $unitPrice = $quantity > 0 ? (int) round((($listPrice * $quantity) - $discountAmount) / $quantity) : $listPrice;
        }

        $originalUnitPrice = $unitPrice;
        $overrideReason = null;
        if (! empty($context['override'])) {
            if (! ($context['allow_override'] ?? false)) {
                throw new InvalidArgumentException('A price override requires authorization.');
            }
            $overrideReason = trim((string) ($context['override_reason'] ?? ''));
            if ($overrideReason === '') {
                throw new InvalidArgumentException('A reason is required to override the price.');
            }
            if (! array_key_exists('unit_amount', $context) || $context['unit_amount'] === null) {
                throw new InvalidArgumentException('An override price is required.');
            }
            $requested = (int) $context['unit_amount'];
            if ($rule?->min_price !== null && $requested < (int) $rule->min_price && ! ($context['allow_exception'] ?? false)) {
                throw new InvalidArgumentException('This price is below the approved minimum.');
            }
            $unitPrice = $requested;
            $discountAmount = max(0, ($listPrice * $quantity) - ($unitPrice * $quantity));
            $discountPercent = $listPrice > 0 ? (int) round(($discountAmount / ($listPrice * $quantity)) * 100) : 0;
            $source = 'override';
        }

        $net = $unitPrice * $quantity;
        $taxAmount = 0;
        $taxInclusive = (bool) ($list?->tax_inclusive ?? $tax?->is_inclusive ?? false);
        $taxRate = (int) ($tax?->rate ?? 0);
        if ($taxRate > 0) {
            if ($taxInclusive) {
                $taxAmount = (int) round($net - ($net / (1 + ($taxRate / 100))));
            } else {
                $taxAmount = (int) round($net * ($taxRate / 100));
                $net += $taxAmount;
            }
        }

        return [
            'billable_type' => $billable['type'],
            'billable_id' => $billable['id'],
            'description' => $billable['name'],
            'unit' => $billable['unit'] ?? 'each',
            'quantity' => $quantity,
            'list_price' => $listPrice,
            'original_unit_price' => $originalUnitPrice,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'tax_inclusive' => $taxInclusive,
            'line_total' => $net,
            'price_list_id' => $list?->id,
            'pricing_rule_id' => $rule?->id,
            'source' => $source,
            'is_override' => $source === 'override',
            'override_reason' => $overrideReason,
            'service_id' => $billable['type'] === 'service' ? $billable['id'] : null,
        ];
    }

    private function billable(array $context): array
    {
        if (! empty($context['service_id']) || ($context['billable_type'] ?? null) === 'service') {
            $service = ClinicalService::query()->findOrFail($context['service_id'] ?? $context['billable_id']);

            return [
                'type' => 'service',
                'id' => $service->id,
                'name' => $service->name,
                'unit_price' => (int) $service->unit_price,
                'unit' => 'each',
                'category' => $service->category,
                'department_id' => $service->department_id,
            ];
        }
        if (! empty($context['medication_id']) || ($context['billable_type'] ?? null) === 'medication') {
            $medication = Medication::query()->findOrFail($context['medication_id'] ?? $context['billable_id']);

            return [
                'type' => 'medication',
                'id' => $medication->id,
                'name' => $medication->label(),
                'unit_price' => (int) $medication->unit_price,
                'unit' => 'each',
                'category' => 'pharmacy',
                'department_id' => null,
            ];
        }
        if (! empty($context['inventory_item_id']) || ($context['billable_type'] ?? null) === 'inventory') {
            $item = InventoryItem::query()->with('unit')->findOrFail($context['inventory_item_id'] ?? $context['billable_id']);

            return [
                'type' => 'inventory',
                'id' => $item->id,
                'name' => $item->label(),
                'unit_price' => (int) $item->unit_price,
                'unit' => $item->unit?->symbol ?: $item->unit?->name ?: 'each',
                'category' => $item->kind,
                'department_id' => null,
            ];
        }
        if (! empty($context['package_id']) || ($context['billable_type'] ?? null) === 'package') {
            $package = ServicePackage::query()->findOrFail($context['package_id'] ?? $context['billable_id']);

            return [
                'type' => 'package',
                'id' => $package->id,
                'name' => $package->name,
                'unit_price' => (int) $package->unit_price,
                'unit' => 'each',
                'category' => 'package',
                'department_id' => null,
            ];
        }

        throw new InvalidArgumentException('A billable service or product is required.');
    }

    private function selectList(array $billable, ?Patient $patient, string $payer, ?string $departmentId, $at, ?string $forcedId): ?PriceList
    {
        if ($forcedId) {
            $list = PriceList::query()->with('items')->find($forcedId);
            if ($list && $list->isCurrent($at)) {
                return $list;
            }
        }

        $candidates = PriceList::query()
            ->with('items')
            ->where('is_active', true)
            ->orderByRaw("case kind when 'customer' then 1 when 'insurance' then 2 when 'promotional' then 3 when 'department' then 4 else 5 end")
            ->get()
            ->filter(fn (PriceList $list) => $list->isCurrent($at));

        $ranked = $candidates->first(function (PriceList $list) use ($patient, $payer, $departmentId, $billable, $at) {
            if ($list->patient_id) {
                return $patient && $list->patient_id === $patient->id && $this->hasBillable($list, $billable);
            }
            if ($list->kind === 'insurance') {
                return $payer === 'insurance' && $this->hasBillable($list, $billable);
            }
            if ($list->kind === 'promotional') {
                return $this->hasBillable($list, $billable);
            }
            if ($list->department_id) {
                return $departmentId && $list->department_id === $departmentId && $this->hasBillable($list, $billable);
            }

            return false;
        });

        return $ranked ?: $candidates->first(fn (PriceList $list) => $list->is_default && $this->hasBillable($list, $billable));
    }

    private function hasBillable(PriceList $list, array $billable): bool
    {
        return $list->items->contains(fn (PriceListItem $item) => $item->billable_type === $billable['type'] && $item->billable_id === $billable['id']);
    }

    private function matchItem(PriceList $list, array $billable, int $quantity, $at): ?PriceListItem
    {
        return $list->items
            ->filter(fn (PriceListItem $item) => $item->billable_type === $billable['type'] && $item->billable_id === $billable['id'] && $item->matches($quantity, $at))
            ->sortByDesc(fn (PriceListItem $item) => (int) $item->min_quantity)
            ->first();
    }

    private function bestRule(array $billable, ?Patient $patient, ?string $departmentId, ?string $listId, int $quantity, $at): ?PricingRule
    {
        return PricingRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->first(function (PricingRule $rule) use ($billable, $patient, $departmentId, $listId, $quantity, $at) {
                if ($rule->starts_at && $rule->starts_at->gt($at)) {
                    return false;
                }
                if ($rule->ends_at && $rule->ends_at->lt($at)) {
                    return false;
                }
                if ($rule->billable_type && $rule->billable_id && ($rule->billable_type !== $billable['type'] || $rule->billable_id !== $billable['id'])) {
                    return false;
                }
                if ($rule->service_category && $rule->service_category !== ($billable['category'] ?? null)) {
                    return false;
                }
                if ($rule->patient_id && (! $patient || $rule->patient_id !== $patient->id)) {
                    return false;
                }
                if ($rule->department_id && $rule->department_id !== $departmentId) {
                    return false;
                }
                if ($rule->price_list_id && $rule->price_list_id !== $listId) {
                    return false;
                }
                if ($rule->min_quantity !== null && $quantity < (int) $rule->min_quantity) {
                    return false;
                }
                if ($rule->max_quantity !== null && $quantity > (int) $rule->max_quantity) {
                    return false;
                }

                return in_array($rule->scope, ['all', 'service', 'category', 'patient', 'product'], true);
            });
    }

    private function patient(array $context): ?Patient
    {
        if (! empty($context['patient'])) {
            return $context['patient'];
        }
        if (! empty($context['patient_id'])) {
            return Patient::query()->find($context['patient_id']);
        }

        return null;
    }

    private function tax(array $context): ?TaxRate
    {
        if (! empty($context['tax_rate_id'])) {
            return TaxRate::query()->find($context['tax_rate_id']);
        }

        return TaxRate::query()->where('is_default', true)->where('is_active', true)->first();
    }
}
