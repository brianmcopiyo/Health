<?php

namespace App\Models;

use App\Models\Concerns\VisibleOnHospitalNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use VisibleOnHospitalNetwork;

    public const STATUSES = ['pending', 'accepted', 'declined', 'in_transit', 'completed', 'cancelled'];

    protected $fillable = [
        'from_hospital_id',
        'to_hospital_id',
        'patient_id',
        'patient_name',
        'patient_reference',
        'reason',
        'required_facility_type_id',
        'required_capacity',
        'destination_facility_id',
        'ambulance_trip_id',
        'status',
        'created_by',
        'reviewed_by',
        'response_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'required_capacity' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function fromHospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'from_hospital_id');
    }

    public function toHospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'to_hospital_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function requiredFacilityType(): BelongsTo
    {
        return $this->belongsTo(FacilityType::class, 'required_facility_type_id');
    }

    public function destinationFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'destination_facility_id');
    }

    public function ambulanceTrip(): BelongsTo
    {
        return $this->belongsTo(AmbulanceTrip::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
