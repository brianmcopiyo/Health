<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCount extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = ['hospital_id', 'reference', 'store_id', 'status', 'created_by', 'counted_at', 'notes'];

    protected function casts(): array
    {
        return ['counted_at' => 'datetime'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class, 'count_id');
    }
}
