<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hospital extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'region',
        'phone',
        'email',
        'address',
        'is_active',
        'patient_seq',
        'invoice_seq',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'patient_seq' => 'integer',
            'invoice_seq' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public function ambulances(): HasMany
    {
        return $this->hasMany(Ambulance::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HospitalMembership::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
