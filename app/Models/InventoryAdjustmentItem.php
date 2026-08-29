<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentItem extends BaseModel
{
    protected $fillable = ['adjustment_id', 'item_id', 'batch_id', 'quantity', 'direction'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'adjustment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }
}
