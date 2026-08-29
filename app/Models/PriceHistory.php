<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PriceHistory extends BaseModel
{
    use BelongsToHospital;

    protected $fillable = [
        'hospital_id', 'subject_type', 'subject_id', 'field', 'old_price', 'new_price', 'changed_by', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'integer',
            'new_price' => 'integer',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
