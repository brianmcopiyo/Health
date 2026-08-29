<?php

namespace App\Support;

use App\Models\ClinicalService;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

class ChargeLedger
{
    public static function post(Encounter $encounter, string $sourceType, string $sourceId, string $description, int $unitAmount, int $quantity = 1, ?string $serviceId = null, array $snapshot = []): InvoiceItem
    {
        return DB::transaction(function () use ($encounter, $sourceType, $sourceId, $description, $unitAmount, $quantity, $serviceId, $snapshot) {
            $invoice = self::openInvoice($encounter);

            $existing = InvoiceItem::query()
                ->where('invoice_id', $invoice->id)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $item = InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'service_id' => $serviceId ?? $snapshot['service_id'] ?? null,
                'billable_type' => $snapshot['billable_type'] ?? ($serviceId ? 'service' : null),
                'billable_id' => $snapshot['billable_id'] ?? $serviceId,
                'description' => $description,
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'list_price' => $snapshot['list_price'] ?? $unitAmount,
                'original_unit_price' => $snapshot['original_unit_price'] ?? $unitAmount,
                'discount_amount' => $snapshot['discount_amount'] ?? 0,
                'discount_percent' => $snapshot['discount_percent'] ?? 0,
                'tax_amount' => $snapshot['tax_amount'] ?? 0,
                'tax_rate' => $snapshot['tax_rate'] ?? 0,
                'amount' => $snapshot['line_total'] ?? ($quantity * $unitAmount),
                'price_list_id' => $snapshot['price_list_id'] ?? null,
                'pricing_rule_id' => $snapshot['pricing_rule_id'] ?? null,
                'is_override' => $snapshot['is_override'] ?? false,
            ]);

            $invoice->recalculateTotal();

            return $item;
        });
    }

    public static function forService(Encounter $encounter, string $code, string $sourceType, string $sourceId, ?string $fallbackName = null, int $fallbackPrice = 0): ?InvoiceItem
    {
        $service = ClinicalService::query()
            ->where('hospital_id', $encounter->hospital_id)
            ->where('code', $code)
            ->first(['id', 'name', 'code', 'unit_price', 'hospital_id', 'department_id', 'category']);

        if ($service) {
            $quote = app(PriceResolver::class)->quote([
                'service_id' => $service->id,
                'patient_id' => $encounter->patient_id,
                'department_id' => $service->department_id,
                'quantity' => 1,
            ]);

            return self::post($encounter, $sourceType, $sourceId, $quote['description'], $quote['unit_price'], 1, $service->id, $quote);
        }

        $name = $fallbackName;
        $price = $fallbackPrice;

        if (! $name || $price < 0) {
            return null;
        }

        return self::post($encounter, $sourceType, $sourceId, $name, $price, 1);
    }

    public static function openInvoice(Encounter $encounter): Invoice
    {
        return DB::transaction(function () use ($encounter) {
            $invoice = Invoice::query()
                ->where('encounter_id', $encounter->id)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->first();

            if ($invoice) {
                return $invoice;
            }

            $hospital = $encounter->hospital()->first() ?? $encounter->hospital;

            return Invoice::query()->create([
                'hospital_id' => $encounter->hospital_id,
                'patient_id' => $encounter->patient_id,
                'encounter_id' => $encounter->id,
                'number' => HospitalSequence::nextInvoiceNumber($hospital),
                'status' => 'draft',
                'total' => 0,
                'payer_type' => 'self_pay',
            ]);
        });
    }
}
