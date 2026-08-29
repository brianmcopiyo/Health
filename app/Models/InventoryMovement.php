<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends BaseModel
{
    use BelongsToHospital;

    public const INBOUND = ['opening', 'receive', 'return_in', 'transfer_in', 'adjustment_in', 'count_in'];

    public const OUTBOUND = ['dispense', 'issue', 'return_out', 'transfer_out', 'adjustment_out', 'count_out'];

    public const WRITE_OFF = ['adjustment_out', 'count_out', 'return_out'];

    protected $fillable = [
        'hospital_id', 'item_id', 'store_id', 'location_id', 'batch_id', 'type', 'quantity', 'unit_cost',
        'balance_after', 'reference_type', 'reference_id', 'patient_id', 'encounter_id', 'prescription_id',
        'department_id', 'notes', 'created_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'integer',
            'balance_after' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'store_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
