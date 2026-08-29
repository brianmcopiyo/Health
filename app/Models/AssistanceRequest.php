<?php

namespace App\Models;

use App\Models\Concerns\VisibleOnHospitalNetwork;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistanceRequest extends BaseModel
{
    use VisibleOnHospitalNetwork;

    public const STATUSES = ['pending', 'accepted', 'declined', 'fulfilled', 'cancelled'];

    public const TYPES = ['staff', 'equipment', 'supplies', 'beds', 'pharmacy', 'other'];

    protected $fillable = [
        'from_hospital_id',
        'to_hospital_id',
        'patient_id',
        'encounter_id',
        'facility_type_id',
        'facility_id',
        'type',
        'quantity',
        'title',
        'description',
        'status',
        'created_by',
        'responded_by',
        'response_notes',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
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

    public function facilityType(): BelongsTo
    {
        return $this->belongsTo(FacilityType::class, 'facility_type_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function fromHospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'from_hospital_id');
    }

    public function toHospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'to_hospital_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
