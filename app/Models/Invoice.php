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
        'number',
        'status',
        'total',
        'issued_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
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

    public function recalculateTotal(): void
    {
        $this->total = (int) $this->items()->sum('amount');
        $this->save();
    }
}
