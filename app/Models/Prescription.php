<?php

namespace App\Models;

use App\Casts\Encrypted;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends BaseModel
{
    use BelongsToHospital;

    public const STATUSES = ['pending', 'verified', 'dispensed', 'cancelled'];

    protected $fillable = [
        'hospital_id', 'patient_id', 'encounter_id', 'prescribed_by', 'verified_by',
        'status', 'notes', 'prescribed_at', 'verified_at', 'dispensed_at',
    ];

    protected function casts(): array
    {
        return [
            'prescribed_at' => 'datetime',
            'verified_at' => 'datetime',
            'dispensed_at' => 'datetime',
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

    public function prescribedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function dispensings(): HasMany
    {
        return $this->hasMany(Dispensing::class);
    }
}
