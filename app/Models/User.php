<?php

namespace App\Models;

use App\Support\ModuleCatalog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'hospital_id',
        'role_id',
        'phone',
        'job_title',
        'department_id',
        'specialty',
        'license_number',
        'avatar_path',
        'availability',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'avatar_path',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
        ];
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function memberships()
    {
        return $this->hasMany(HospitalMembership::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function staffAssignments()
    {
        return $this->hasMany(StaffAssignment::class);
    }

    public function encounters()
    {
        return $this->hasMany(Encounter::class, 'clinician_id');
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role?->slug === 'platform-admin';
    }

    public function hasPermission(string $action, string $subject): bool
    {
        $this->loadMissing('role.permissions');

        $permissions = $this->role?->permissions ?? collect();

        if ($permissions->contains(fn (Permission $permission) => $permission->action === 'manage' && $permission->subject === 'all')) {
            return true;
        }

        return $permissions->contains(function (Permission $permission) use ($action, $subject) {
            return $permission->subject === $subject
                && in_array($permission->action, [$action, 'manage'], true);
        });
    }

    public function abilityRules(): array
    {
        $this->loadMissing('role.permissions');

        return $this->role?->permissions
            ->map(fn (Permission $permission) => $permission->toAbilityRule())
            ->values()
            ->all() ?? [];
    }

    public function belongsToHospital(string $hospitalId): bool
    {
        if ($this->hospital_id === $hospitalId) {
            return true;
        }

        return $this->memberships()->where('hospital_id', $hospitalId)->exists();
    }

    public function preferenceMap(): array
    {
        return array_merge([
            'referrals' => true,
            'encounters' => true,
            'laboratory' => true,
            'pharmacy' => true,
            'invoices' => true,
        ], $this->preferences ?? []);
    }

    public function toAuthPayload(): array
    {
        $this->loadMissing(['role', 'hospital', 'department']);

        return [
            'id' => $this->id,
            'fullName' => $this->name,
            'username' => strstr($this->email, '@', true) ?: $this->email,
            'email' => $this->email,
            'role' => $this->role?->slug,
            'roleName' => $this->role?->name,
            'hospitalId' => $this->hospital_id,
            'hospitalName' => $this->hospital?->name,
            'phone' => $this->phone,
            'jobTitle' => $this->job_title,
            'specialty' => $this->specialty,
            'departmentId' => $this->department_id,
            'departmentName' => $this->department?->name,
            'availability' => $this->availability ?: 'available',
            'hasAvatar' => (bool) $this->avatar_path,
            'preferences' => $this->preferenceMap(),
            'avatar' => null,
            'workspace' => $this->role?->workspace,
            'homeRoute' => ModuleCatalog::homeRoute($this),
            'modules' => ModuleCatalog::keysFor($this),
            'memberships' => $this->memberships()->with(['hospital', 'role'])->get()->map(fn (HospitalMembership $membership) => [
                'hospitalId' => $membership->hospital_id,
                'hospitalName' => $membership->hospital?->name,
                'role' => $membership->role?->slug,
                'roleName' => $membership->role?->name,
            ])->values()->all(),
        ];
    }

    public function toSessionPayload(): array
    {
        return [
            'userData' => $this->toAuthPayload(),
            'userAbilityRules' => $this->abilityRules(),
            'navigation' => ModuleCatalog::navigation($this),
        ];
    }
}
