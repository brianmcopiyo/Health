<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrder extends Model
{
    use BelongsToHospital;

    public const STATUSES = ['requested', 'collected', 'scheduled', 'processing', 'completed', 'cancelled'];

    protected $fillable = [
        'hospital_id',
        'patient_id',
        'encounter_id',
        'facility_id',
        'service_id',
        'ordered_by',
        'completed_by',
        'module_key',
        'order_type',
        'item_name',
        'status',
        'result',
        'notes',
        'requested_at',
        'collected_at',
        'scheduled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'collected_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ClinicalService::class, 'service_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
