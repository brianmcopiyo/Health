<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'name', 'type', 'scope', 'billable_type', 'billable_id', 'service_category',
        'patient_id', 'price_list_id', 'department_id', 'min_quantity', 'max_quantity', 'value',
        'min_price', 'priority', 'requires_approval', 'is_active', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'value' => 'integer',
            'min_price' => 'integer',
            'priority' => 'integer',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
