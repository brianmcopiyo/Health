<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispensing extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'patient_id', 'encounter_id', 'prescription_id', 'prescription_item_id',
        'medication_id', 'dispensed_by', 'quantity', 'dispensed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'dispensed_at' => 'datetime',
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

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }
}
