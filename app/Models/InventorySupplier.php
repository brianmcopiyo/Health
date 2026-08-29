<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySupplier extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = ['hospital_id', 'name', 'code', 'phone', 'email', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(InventoryReceipt::class, 'supplier_id');
    }
}
