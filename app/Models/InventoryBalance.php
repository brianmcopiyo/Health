<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = ['hospital_id', 'item_id', 'store_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'store_id');
    }
}
