<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReceipt extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = ['hospital_id', 'reference', 'store_id', 'supplier_id', 'created_by', 'received_at', 'notes'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'store_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(InventorySupplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryReceiptItem::class, 'receipt_id');
    }
}
