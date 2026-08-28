<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encounter extends Model
{
    use BelongsToHospital;

    public const TYPES = ['reception', 'opd', 'emergency', 'admission', 'procedure', 'follow_up', 'referral'];

    public const STATUSES = ['waiting', 'in_progress', 'completed', 'cancelled', 'transferred'];

    protected $fillable = [
        'hospital_id',
        'patient_id',
        'department_id',
        'clinician_id',
        'facility_id',
        'parent_encounter_id',
        'referral_id',
        'ambulance_trip_id',
        'type',
        'status',
        'chief_complaint',
        'notes',
        'started_at',
        'completed_at',
        'admitted_at',
        'discharged_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'admitted_at' => 'datetime',
            'discharged_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function parentEncounter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_encounter_id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function ambulanceTrip(): BelongsTo
    {
        return $this->belongsTo(AmbulanceTrip::class);
    }

    public function careTeam(): HasMany
    {
        return $this->hasMany(EncounterClinician::class);
    }

    public function vitals(): HasMany
    {
        return $this->hasMany(Vital::class);
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function bedAssignments(): HasMany
    {
        return $this->hasMany(BedAssignment::class);
    }

    public function addClinician(?int $userId, string $role = 'attending'): void
    {
        if (! $userId)
            return;

        EncounterClinician::query()->firstOrCreate([
            'encounter_id' => $this->id,
            'user_id' => $userId,
            'care_role' => $role,
        ]);

        if (! $this->clinician_id && $role === 'attending')
            $this->update(['clinician_id' => $userId]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['waiting', 'in_progress'], true);
    }
}
