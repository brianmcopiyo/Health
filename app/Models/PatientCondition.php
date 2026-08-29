<?php

namespace App\Models;

use App\Casts\Encrypted;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientCondition extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'patient_id', 'name', 'status', 'diagnosed_on', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'diagnosed_on' => 'date',
            'name' => Encrypted::class,
            'notes' => Encrypted::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
