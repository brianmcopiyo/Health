<?php

namespace App\Models;

use App\Casts\Encrypted;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AmbulanceTrip extends BaseModel
{
    use BelongsToHospital;

    public const STATUSES = ['dispatched', 'en_route', 'arrived', 'completed', 'cancelled'];

    protected $fillable = [
        'hospital_id',
        'ambulance_id',
        'patient_id',
        'encounter_id',
        'referral_id',
        'driver_user_id',
        'origin',
        'pickup_location',
        'destination',
        'destination_hospital_id',
        'destination_facility_id',
        'receiving_encounter_id',
        'status',
        'dispatched_at',
        'arrived_at',
        'completed_at',
        'handover_at',
        'handover_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'handover_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
            'handover_notes' => Encrypted::class,
            'notes' => Encrypted::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function linkedReferral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function destinationFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'destination_facility_id');
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
