<?php

namespace App\Support;

use App\Models\ClinicalService;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\InvoiceItem;

class ChargeLedger
{
    public static function post(Encounter $encounter, string $sourceType, int $sourceId, string $description, int $unitAmount, int $quantity = 1, ?int $serviceId = null): InvoiceItem
    {
        $invoice = self::openInvoice($encounter);

        $existing = InvoiceItem::query()
            ->where('invoice_id', $invoice->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($existing)
            return $existing;

        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'service_id' => $serviceId,
            'description' => $description,
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'amount' => $quantity * $unitAmount,
        ]);

        $invoice->recalculateTotal();

        return $item;
    }

    public static function forService(Encounter $encounter, string $code, string $sourceType, int $sourceId, ?string $fallbackName = null, int $fallbackPrice = 0): ?InvoiceItem
    {
        $service = ClinicalService::query()
            ->where('hospital_id', $encounter->hospital_id)
            ->where('code', $code)
            ->first();

        $name = $service?->name ?: $fallbackName;
        $price = $service?->unit_price ?? $fallbackPrice;

        if (! $name || $price < 0)
            return null;

        return self::post($encounter, $sourceType, $sourceId, $name, $price, 1, $service?->id);
    }

    public static function openInvoice(Encounter $encounter): Invoice
    {
        $invoice = Invoice::query()
            ->where('encounter_id', $encounter->id)
            ->where('status', 'draft')
            ->first();

        if ($invoice)
            return $invoice;

        $hospital = $encounter->hospital;

        return Invoice::query()->create([
            'hospital_id' => $encounter->hospital_id,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'number' => sprintf('%s-INV-%04d', $hospital?->code ?: 'HMS', Invoice::query()->count() + 1),
            'status' => 'draft',
            'total' => 0,
        ]);
    }
}
