<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use App\Support\FacilityOccupancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Facility extends BaseModel
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

    public function beds(): HasMany
    {
        return $this->children()->whereHas('type', fn ($query) => $query->where('slug', 'bed'));
    }

    public function bedAssignments(): HasMany
    {
        return $this->hasMany(BedAssignment::class, 'facility_id');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(BedAssignment::class, 'facility_id')->where('status', 'active');
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class);
    }

    public function remainingCapacity(): int
    {
        return max(0, $this->capacity - $this->current_utilization);
    }

    public function isAvailableFor(int $requiredCapacity = 1): bool
    {
        return $this->status === 'available' && $this->remainingCapacity() >= $requiredCapacity;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $prefix = addcslashes($term, '%_').'%';

        return $query->where(function (Builder $builder) use ($prefix) {
            $builder->where('name', 'like', $prefix)
                ->orWhere('code', 'like', $prefix);
        });
    }

    public function scopeHasRemainingCapacity(Builder $query, int $required = 1): Builder
    {
        return $query->where('status', 'available')
            ->whereRaw('(capacity - current_utilization) >= ?', [$required]);
    }

    public function adjustUtilization(int $delta): void
    {
        DB::transaction(function () use ($delta) {
            $row = static::withoutGlobalScope('hospital')->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $row->current_utilization = max(0, $row->current_utilization + $delta);

            if ($row->current_utilization > $row->capacity) {
                $row->current_utilization = $row->capacity;
            }

            if ($row->current_utilization >= $row->capacity && $row->status === 'available') {
                $row->status = 'occupied';
            }

            if ($row->current_utilization < $row->capacity && $row->status === 'occupied') {
                $row->status = 'available';
            }

            $row->save();
            $this->setRawAttributes($row->getAttributes());
            $this->syncOriginal();
            $row->loadMissing(['type', 'parent.type']);

            if ($row->type?->slug === 'bed') {
                FacilityOccupancy::syncForBed($row);
            }
        });
    }

    public static function platformBypassesTenant(): bool
    {
        return true;
    }
}
