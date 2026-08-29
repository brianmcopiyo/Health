<?php

namespace App\Models;

use App\Casts\Encrypted;
use App\Models\Concerns\VisibleOnHospitalNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use VisibleOnHospitalNetwork;

    public const STATUSES = ['pending', 'more_info', 'accepted', 'declined', 'in_transit', 'completed', 'cancelled'];

    protected $fillable = [
        'from_hospital_id',
        'to_hospital_id',
        'patient_id',
        'encounter_id',
        'referring_clinician_id',
        'receiving_patient_id',
        'receiving_encounter_id',
        'patient_name',
        'patient_reference',
        'reason',
        'required_facility_type_id',
        'required_service_id',
        'required_capacity',
        'destination_facility_id',
        'ambulance_trip_id',
        'counter_referral_id',
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
            'patient_name' => Encrypted::class,
            'reason' => Encrypted::class,
            'response_notes' => Encrypted::class,
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

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function referringClinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referring_clinician_id');
    }

    public function receivingPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'receiving_patient_id');
    }

    public function receivingEncounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'receiving_encounter_id');
    }

    public function requiredService(): BelongsTo
    {
        return $this->belongsTo(ClinicalService::class, 'required_service_id');
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
