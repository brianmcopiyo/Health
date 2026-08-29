<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'category_id', 'unit_id', 'medication_id', 'kind', 'name', 'sku', 'form', 'strength',
        'unit_price', 'cost_price', 'reorder_level', 'tracks_batch', 'tracks_expiry', 'is_controlled', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'cost_price' => 'integer',
            'reorder_level' => 'integer',
            'tracks_batch' => 'boolean',
            'tracks_expiry' => 'boolean',
            'is_controlled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'unit_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'item_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id');
    }

    public function label(): string
    {
        return trim($this->name.' '.$this->strength.' '.$this->form);
    }

    public function onHand(): float
    {
        return (float) $this->balances()->sum('quantity');
    }
}
