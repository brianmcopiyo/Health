<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAssignment extends BaseModel
{
    use BelongsToHospital;

    public const SHIFTS = ['day', 'evening', 'night'];

    protected $fillable = [
        'hospital_id', 'user_id', 'department_id', 'facility_id', 'assignment_role', 'shift', 'status', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public static function platformBypassesTenant(): bool
    {
        return true;
    }
}
