<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmbulanceStaff extends Model
{
    protected $table = 'ambulance_staff';

    protected $fillable = [
        'ambulance_id',
        'user_id',
        'assignment_role',
    ];

    public function ambulance(): BelongsTo
    {
        return $this->belongsTo(Ambulance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
