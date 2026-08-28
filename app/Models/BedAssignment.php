<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedAssignment extends Model
{
    use BelongsToHospital;

    public const STATUSES = ['active', 'discharged'];

    protected $fillable = [
        'hospital_id',
        'patient_id',
        'encounter_id',
        'facility_id',
        'ward_id',
        'assigned_by',
        'nurse_id',
        'status',
        'assigned_at',
        'discharged_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
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

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'ward_id');
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
