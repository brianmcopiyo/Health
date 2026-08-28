<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AmbulanceTrip extends Model
{
    use BelongsToHospital;

    public const STATUSES = ['dispatched', 'en_route', 'arrived', 'completed', 'cancelled'];

    protected $fillable = [
        'hospital_id',
        'ambulance_id',
        'driver_user_id',
        'origin',
        'destination',
        'destination_hospital_id',
        'status',
        'dispatched_at',
        'arrived_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function ambulance(): BelongsTo
    {
        return $this->belongsTo(Ambulance::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function destinationHospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'destination_hospital_id');
    }

    public function referral(): HasOne
    {
        return $this->hasOne(Referral::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['dispatched', 'en_route', 'arrived'], true);
    }
}
