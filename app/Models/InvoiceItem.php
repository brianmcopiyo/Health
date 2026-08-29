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
        'description',
        'quantity',
        'unit_amount',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'amount' => 'integer',
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
}
