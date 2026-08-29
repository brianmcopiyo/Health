<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustment extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = ['hospital_id', 'reference', 'store_id', 'reason', 'created_by', 'occurred_at', 'notes'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class, 'adjustment_id');
    }
}
