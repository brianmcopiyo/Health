<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends BaseModel
{
    use BelongsToHospital;

    public const STATUSES = ['draft', 'issued', 'paid', 'cancelled'];

    protected $fillable = [
        'hospital_id',
        'patient_id',
        'encounter_id',
        'price_list_id',
        'payer_type',
        'number',
        'status',
        'total',
        'discount_total',
        'tax_total',
        'tax_inclusive',
        'issued_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'tax_inclusive' => 'boolean',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function paidAmount(): int
    {
        $paid = $this->relationLoaded('payments')
            ? (int) $this->payments->sum('amount')
            : (int) $this->payments()->sum('amount');
        $refunded = $this->relationLoaded('refunds')
            ? (int) $this->refunds->sum('amount')
            : (int) $this->refunds()->sum('amount');

        return max(0, $paid - $refunded);
    }

    public function outstanding(): int
    {
        return max(0, (int) $this->total - $this->paidAmount());
    }

    public function recalculateTotal(): void
    {
        $this->total = (int) $this->items()->sum('amount');
        $this->discount_total = (int) $this->items()->sum('discount_amount');
        $this->tax_total = (int) $this->items()->sum('tax_amount');
        $this->save();
    }
}
