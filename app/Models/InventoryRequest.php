<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryRequest extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'reference', 'from_store_id', 'to_store_id', 'department_id', 'status',
        'requested_by', 'approved_by', 'requested_at', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'from_store_id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'to_store_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryRequestItem::class, 'request_id');
    }
}
