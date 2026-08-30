<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePackage extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = ['hospital_id', 'name', 'unit_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServicePackageItem::class, 'package_id');
    }
}
