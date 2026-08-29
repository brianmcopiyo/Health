<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ambulance extends Model
{
    use BelongsToHospital;

    public const STATUSES = ['available', 'on_trip', 'maintenance', 'unavailable'];

    protected $fillable = [
        'hospital_id',
        'vehicle_code',
        'vehicle_type',
        'status',
        'capacity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(AmbulanceStaff::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(AmbulanceTrip::class);
    }

    public function activeTrip(): ?AmbulanceTrip
    {
        return $this->trips()
            ->whereIn('status', ['dispatched', 'en_route', 'arrived'])
            ->latest('id')
            ->first();
    }

    public static function platformBypassesTenant(): bool
    {
        return true;
    }
}
