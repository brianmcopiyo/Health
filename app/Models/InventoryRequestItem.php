<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRequestItem extends BaseModel
{
    protected $fillable = ['request_id', 'item_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(InventoryRequest::class, 'request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
