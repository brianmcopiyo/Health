<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalMembership extends BaseModel
{
    protected $table = 'hospital_user';

    protected $fillable = [
        'user_id',
        'hospital_id',
        'role_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
