<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id',
        'name',
        'slug',
        'module_key',
        'kind',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ClinicalService::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public static function platformBypassesTenant(): bool
    {
        return true;
    }
}
