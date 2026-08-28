<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'hospital_id',
        'name',
        'slug',
        'description',
        'workspace',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HospitalMembership::class);
    }

    public function isAssigned(): bool
    {
        return $this->users()->exists() || $this->memberships()->exists();
    }

    public function isVisibleTo(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $this->hospital_id === $user->hospital_id;
    }
}
