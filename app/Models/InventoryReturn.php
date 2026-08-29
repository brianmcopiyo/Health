<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReturn extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'reference', 'from_store_id', 'to_store_id', 'issue_id', 'created_by', 'occurred_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'from_store_id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'to_store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryReturnItem::class, 'return_id');
    }
}
