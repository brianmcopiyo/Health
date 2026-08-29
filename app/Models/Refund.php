<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'invoice_id', 'payment_id', 'amount', 'method', 'reason',
        'created_by', 'authorized_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
