<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'item_id', 'store_id', 'location_id', 'supplier_id', 'batch_number', 'expiry_date',
        'quantity', 'unit_cost', 'status', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'quantity' => 'decimal:3',
            'unit_cost' => 'integer',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(InventorySupplier::class, 'supplier_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'batch_id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->endOfDay()->isPast();
    }
}
