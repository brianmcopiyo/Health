<?php

namespace App\Support\Access;

trait HasAccountAccess
{
    public function canAuthenticate(): bool
    {
        return AccountStatus::allowsAuthentication($this->status ?? AccountStatus::ACTIVE);
    }

    public function recordLogin(): void
    {
        $this->forceFill(['last_login_at' => now()])->save();
    }

    public function revokeAccessTokens(): void
    {
        if (method_exists($this, 'tokens')) {
            $this->tokens()->delete();
        }
    }

    public function accountStatus(): string
    {
        return $this->status ?: AccountStatus::ACTIVE;
    }

    public function isGloballyPrivileged(): bool
    {
        $this->loadMissing('role.permissions');

        if ($this->role?->slug === 'platform-admin') {
            return true;
        }

        $permissions = $this->role?->permissions ?? collect();

        return $permissions->contains(fn ($permission) => $permission->action === 'manage' && $permission->subject === 'all');
    }

    public function isHospitalAdministrator(): bool
    {
        return $this->role?->slug === 'administrator' && filled($this->hospital_id ?? null);
    }

    public function isPrivileged(): bool
    {
        return $this->isGloballyPrivileged() || $this->isHospitalAdministrator();
    }

    public function hasPermission(string $action, string $subject): bool
    {
        $this->loadMissing('role.permissions');

        $permissions = $this->role?->permissions ?? collect();

        if ($permissions->contains(fn ($permission) => $permission->action === 'manage' && $permission->subject === 'all')) {
            return true;
        }

        return $permissions->contains(function ($permission) use ($action, $subject) {
            return $permission->subject === $subject
                && in_array($permission->action, [$action, 'manage'], true);
        });
    }

    public function abilityRules(): array
    {
        $this->loadMissing('role.permissions');

        return $this->role?->permissions
            ->map(fn ($permission) => method_exists($permission, 'toAbilityRule')
                ? $permission->toAbilityRule()
                : ['action' => $permission->action, 'subject' => $permission->subject])
            ->values()
            ->all() ?? [];
    }

    public function applyAccountStatus(string $status): void
    {
        $this->status = $status;
        $this->save();

        if (! AccountStatus::allowsAuthentication($status)) {
            $this->revokeAccessTokens();
        }
    }
}
