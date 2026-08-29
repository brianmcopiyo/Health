<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryUnitConversion extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = ['hospital_id', 'from_unit_id', 'to_unit_id', 'factor'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:6'];
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'to_unit_id');
    }
}
