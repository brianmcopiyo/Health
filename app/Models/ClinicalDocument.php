<?php

namespace App\Models;

use App\Casts\Encrypted;
use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClinicalDocument extends Model
{
    use BelongsToHospital;

    protected $fillable = [
        'uuid',
        'hospital_id',
        'patient_id',
        'encounter_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'checksum',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'original_name' => Encrypted::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document) {
            $document->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
