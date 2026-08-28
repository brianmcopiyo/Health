<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAllergy extends Model
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'patient_id', 'allergen', 'reaction', 'severity', 'noted_by', 'noted_at',
    ];

    protected function casts(): array
    {
        return ['noted_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function notedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'noted_by');
    }
}
