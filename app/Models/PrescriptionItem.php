<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id', 'medication_id', 'dose', 'frequency', 'duration', 'quantity', 'instructions',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function dispensings(): HasMany
    {
        return $this->hasMany(Dispensing::class);
    }
}
