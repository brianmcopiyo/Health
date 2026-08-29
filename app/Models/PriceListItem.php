<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PriceListItem extends BaseModel
{
    protected $fillable = [
        'price_list_id', 'billable_type', 'billable_id', 'min_quantity', 'max_quantity',
        'unit_price', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'unit_price' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function matches(int $quantity, $at): bool
    {
        if ($quantity < (int) $this->min_quantity) {
            return false;
        }
        if ($this->max_quantity !== null && $quantity > (int) $this->max_quantity) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->gt($at)) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->lt($at)) {
            return false;
        }

        return true;
    }
}
