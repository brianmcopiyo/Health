<?php

namespace App\Support\Access;

class AccountGuard
{
    public static function assertCanRevokeAccess($actor, $user, string $action = 'remove'): void
    {
        abort_if($user->id === $actor->id, 422, 'You cannot '.$action.' your own account.');
        self::assertNotLastPrivileged($actor, $user);
    }

    public static function assertCanChangeStatus($actor, $user, string $status): void
    {
        if (AccountStatus::allowsAuthentication($status)) {
            return;
        }

        abort_if($user->id === $actor->id, 422, 'You cannot deactivate your own account.');
        self::assertNotLastPrivileged($actor, $user);
    }

    public static function assertCanChangeRole($actor, $user, $role): void
    {
        if (! $user->isPrivileged() || self::roleIsPrivileged($role, $user)) {
            abort_if(
                $user->id === $actor->id && $user->isGloballyPrivileged() && ! self::roleGrantsManageAll($role) && $role->slug !== 'platform-admin',
                422,
                'You cannot remove your own administrator access.'
            );

            return;
        }

        abort_if($user->id === $actor->id, 422, 'You cannot remove your own administrator access.');
        self::assertNotLastPrivileged($actor, $user);
    }

    public static function assertCanSyncPermissions($role, array $permissionIds): void
    {
        if (! self::roleGrantsManageAll($role) || self::permissionIdsGrantManageAll($role, $permissionIds)) {
            return;
        }

        abort_if(
            $role->is_system && in_array($role->slug, ['platform-admin', 'administrator'], true),
            422,
            'Administrator permissions cannot be changed.'
        );

        $assigned = $role->users()->where('status', AccountStatus::ACTIVE)->pluck('id');

        if ($assigned->isEmpty()) {
            return;
        }

        $sample = $role->users()->first();

        abort_if(
            self::globalPrivilegedCount($sample, $assigned->all()) < 1,
            422,
            'This role is the last source of administrator access.'
        );
    }

    public static function roleGrantsManageAll($role): bool
    {
        $role->loadMissing('permissions');

        return $role->permissions->contains(fn ($permission) => $permission->action === 'manage' && $permission->subject === 'all');
    }

    private static function assertNotLastPrivileged($actor, $user): void
    {
        if ($user->isGloballyPrivileged() && self::globalPrivilegedCount($user, [$user->id]) < 1) {
            abort(422, 'The last administrator cannot lose system access.');
        }

        $canOverrideHospital = method_exists($actor, 'isPlatformAdmin') && $actor->isPlatformAdmin();

        if (
            ! $canOverrideHospital
            && $user->isHospitalAdministrator()
            && filled($user->hospital_id)
            && self::hospitalAdminCount($user, $user->id, $user->hospital_id) < 1
        ) {
            abort(422, 'The last hospital administrator cannot lose system access.');
        }
    }

    private static function roleIsPrivileged($role, $user): bool
    {
        if ($role->slug === 'platform-admin') {
            return true;
        }

        if (self::roleGrantsManageAll($role)) {
            return true;
        }

        return $role->slug === 'administrator' && filled($user->hospital_id ?? null);
    }

    private static function permissionIdsGrantManageAll($role, array $permissionIds): bool
    {
        return $role->permissions()->getRelated()->newQuery()
            ->whereIn('id', $permissionIds)
            ->where('action', 'manage')
            ->where('subject', 'all')
            ->exists();
    }

    private static function globalPrivilegedCount($sample, array $exceptIds = []): int
    {
        $query = $sample->newQuery()
            ->where('status', AccountStatus::ACTIVE)
            ->where(function ($inner) {
                $inner->whereHas('role', fn ($role) => $role->where('slug', 'platform-admin'))
                    ->orWhereHas('role.permissions', fn ($permission) => $permission->where('action', 'manage')->where('subject', 'all'));
            });

        if ($exceptIds) {
            $query->whereNotIn('id', $exceptIds);
        }

        return $query->count();
    }

    private static function hospitalAdminCount($sample, string $exceptId, string $hospitalId): int
    {
        return $sample->newQuery()
            ->where('id', '!=', $exceptId)
            ->where('status', AccountStatus::ACTIVE)
            ->where('hospital_id', $hospitalId)
            ->whereHas('role', fn ($role) => $role->where('slug', 'administrator'))
            ->count();
    }
}
