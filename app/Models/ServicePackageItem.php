<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePackageItem extends BaseModel
{
    protected $fillable = ['package_id', 'service_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'package_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ClinicalService::class, 'service_id');
    }
}
