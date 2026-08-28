<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use BelongsToHospital;

    public const STATUSES = ['available', 'occupied', 'unavailable', 'maintenance', 'reserved', 'cleaning'];

    protected $fillable = [
        'hospital_id',
        'facility_type_id',
        'parent_id',
        'department_id',
        'name',
        'code',
        'status',
        'capacity',
        'current_utilization',
        'resource_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'current_utilization' => 'integer',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(FacilityType::class, 'facility_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function remainingCapacity(): int
    {
        return max(0, $this->capacity - $this->current_utilization);
    }

    public function isAvailableFor(int $requiredCapacity = 1): bool
    {
        return $this->status === 'available' && $this->remainingCapacity() >= $requiredCapacity;
    }

    public function adjustUtilization(int $delta): void
    {
        $this->current_utilization = max(0, $this->current_utilization + $delta);

        if ($this->current_utilization > $this->capacity) {
            $this->current_utilization = $this->capacity;
        }

        if ($this->current_utilization >= $this->capacity && $this->status === 'available') {
            $this->status = 'occupied';
        }

        if ($this->current_utilization < $this->capacity && $this->status === 'occupied') {
            $this->status = 'available';
        }

        $this->save();
    }
}
