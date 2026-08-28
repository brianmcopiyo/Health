<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalService extends Model
{
    use BelongsToHospital;

    public const CATEGORIES = ['consultation', 'admission', 'laboratory', 'imaging', 'procedure', 'pharmacy', 'ambulance', 'other'];

    protected $fillable = [
        'hospital_id', 'department_id', 'name', 'code', 'category', 'unit_price', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
