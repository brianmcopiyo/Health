<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityType extends BaseModel
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
    ];

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }
}
