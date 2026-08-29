<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends BaseModel
{
    protected $fillable = [
        'invoice_id',
        'source_type',
        'source_id',
        'service_id',
        'billable_type',
        'billable_id',
        'description',
        'quantity',
        'unit_amount',
        'list_price',
        'discount_amount',
        'discount_percent',
        'tax_amount',
        'tax_rate',
        'amount',
        'price_list_id',
        'pricing_rule_id',
        'is_override',
        'original_unit_price',
        'override_reason',
        'overridden_by',
        'overridden_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'list_price' => 'integer',
            'original_unit_price' => 'integer',
            'overridden_at' => 'datetime',
            'discount_amount' => 'integer',
            'discount_percent' => 'integer',
            'tax_amount' => 'integer',
            'tax_rate' => 'integer',
            'amount' => 'integer',
            'is_override' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ClinicalService::class, 'service_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
