<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'name', 'code', 'kind', 'patient_id', 'department_id',
        'tax_inclusive', 'is_default', 'is_active', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'tax_inclusive' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function isCurrent(?\DateTimeInterface $at = null): bool
    {
        $at = $at ?: now();
        if ($this->starts_at && $this->starts_at->gt($at)) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->lt($at)) {
            return false;
        }

        return $this->is_active;
    }
}
