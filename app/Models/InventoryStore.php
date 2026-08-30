<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryStore extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'department_id', 'facility_id', 'name', 'type', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class, 'store_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'store_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'store_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'store_id');
    }
}
