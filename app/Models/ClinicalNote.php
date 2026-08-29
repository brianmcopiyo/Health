<?php

namespace App\Models;

use App\Casts\Encrypted;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalNote extends BaseModel
{
    use BelongsToHospital;

    public const TYPES = ['progress', 'admission', 'discharge', 'procedure', 'nursing'];

    protected $fillable = [
        'hospital_id', 'patient_id', 'encounter_id', 'author_id', 'type', 'body', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'body' => Encrypted::class];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
