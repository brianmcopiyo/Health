<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;

class TaxRate extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'name', 'rate', 'is_inclusive', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'is_inclusive' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
