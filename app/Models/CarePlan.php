<?php

namespace App\Models;

use App\Casts\Encrypted;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarePlan extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'patient_id', 'encounter_id', 'title', 'body', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['body' => Encrypted::class];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
