<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends BaseModel
{
    protected $fillable = ['count_id', 'item_id', 'batch_id', 'system_quantity', 'counted_quantity', 'variance'];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'variance' => 'decimal:3',
        ];
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class, 'count_id');
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
