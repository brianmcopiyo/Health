<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryIssue extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'reference', 'store_id', 'to_store_id', 'department_id', 'patient_id',
        'encounter_id', 'prescription_id', 'request_id', 'kind', 'created_by', 'occurred_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'store_id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'to_store_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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

    public function items(): HasMany
    {
        return $this->hasMany(InventoryIssueItem::class, 'issue_id');
    }
}
