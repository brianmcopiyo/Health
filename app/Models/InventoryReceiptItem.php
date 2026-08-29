<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReceiptItem extends BaseModel
{
    protected $fillable = [
        'receipt_id', 'item_id', 'batch_id', 'quantity', 'unit_cost', 'batch_number', 'expiry_date',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'integer', 'expiry_date' => 'date'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(InventoryReceipt::class, 'receipt_id');
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
