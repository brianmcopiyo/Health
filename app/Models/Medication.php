<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medication extends Model
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'name', 'form', 'strength', 'sku', 'unit_price', 'stock_qty', 'reorder_level',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'stock_qty' => 'integer',
            'reorder_level' => 'integer',
        ];
    }

    public function label(): string
    {
        return trim($this->name.' '.$this->strength.' '.$this->form);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function adjustStock(int $delta): void
    {
        $row = static::withoutGlobalScope('hospital')->whereKey($this->id)->lockForUpdate()->firstOrFail();
        $row->stock_qty = max(0, $row->stock_qty + $delta);
        $row->save();
        $this->stock_qty = $row->stock_qty;
    }

    public static function platformBypassesTenant(): bool
    {
        return true;
    }
}
