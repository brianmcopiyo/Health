<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryUnit extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = ['hospital_id', 'name', 'symbol', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(InventoryUnitConversion::class, 'from_unit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'unit_id');
    }
}
