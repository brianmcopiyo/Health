<?php

namespace App\Models;

use App\Casts\Encrypted;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vital extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'patient_id', 'encounter_id', 'recorded_by', 'recorded_at',
        'temperature', 'pulse', 'respiration', 'systolic', 'diastolic', 'spo2', 'weight', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'temperature' => 'float',
            'weight' => 'float',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function summary(): string
    {
        $parts = [];
        if ($this->temperature)
            $parts[] = $this->temperature.'°C';
        if ($this->pulse)
            $parts[] = $this->pulse.' bpm';
        if ($this->systolic && $this->diastolic)
            $parts[] = $this->systolic.'/'.$this->diastolic;
        if ($this->spo2)
            $parts[] = 'SpO2 '.$this->spo2.'%';

        return implode(' · ', $parts) ?: 'Vitals recorded';
    }
}
