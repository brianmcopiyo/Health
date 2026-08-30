<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\ModuleCatalog;
use App\Support\Access\AccountGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Role::query()->with('permissions')->orderBy('name');

        if (! $user->isPlatformAdmin()) {
            $query->where('hospital_id', $user->hospital_id);
        }

        return $query->get();
    }

    public function show(Request $request, Role $role)
    {
        abort_unless($role->isVisibleTo($request->user()), 403, 'This action is unauthorized.');

        return $role->load([
            'permissions',
            'users:id,name,email,job_title,role_id,department_id,status,last_login_at',
            'users.department:id,name',
            'hospital:id,name',
        ]);
    }

    public function permissions(Request $request)
    {
        $query = Permission::query()->orderBy('group')->orderBy('name');

        if (! $request->user()->isPlatformAdmin()) {
            $query->whereNotIn('subject', ['Hospital', 'all']);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'workspace' => ['nullable', 'string', 'max:80'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['uuid', 'exists:permissions,id'],
        ]);

        $role = Role::query()->create([
            'hospital_id' => $user->isPlatformAdmin() ? null : $user->hospital_id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::random(6),
            'description' => $data['description'] ?? null,
            'workspace' => $data['workspace'] ?? ModuleCatalog::all()[0]['to'],
            'is_system' => false,
        ]);

        $role->permissions()->sync($this->filterPermissionIds($user, $data['permission_ids'] ?? []));

        return $role->load('permissions');
    }

    public function update(Request $request, Role $role)
    {
        $user = $request->user();

        abort_unless($role->isVisibleTo($user), 403, 'This action is unauthorized.');
        abort_if($role->slug === 'platform-admin' && ! $user->isPlatformAdmin(), 403, 'This action is unauthorized.');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'workspace' => ['nullable', 'string', 'max:80'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['uuid', 'exists:permissions,id'],
        ]);

        $role->fill(collect($data)->only(['name', 'description', 'workspace'])->all());

        if (isset($data['name']) && ! $role->is_system) {
            $role->slug = Str::slug($data['name']).'-'.$role->id;
        }

        $role->save();

        if (array_key_exists('permission_ids', $data)) {
            abort_if($role->is_system && $role->slug === 'platform-admin', 422, 'Platform admin permissions cannot be changed.');
            AccountGuard::assertCanSyncPermissions($role, $data['permission_ids']);
            $role->permissions()->sync($this->filterPermissionIds($user, $data['permission_ids']));
        }

        return $role->load('permissions');
    }

    public function destroy(Request $request, Role $role)
    {
        abort_unless($role->isVisibleTo($request->user()), 403, 'This action is unauthorized.');
        abort_if($role->is_system, 422, 'System roles cannot be deleted.');
        abort_if($role->isAssigned(), 422, 'Reassign users before deleting this role.');

        $role->delete();

        return response()->json(['message' => 'Role removed']);
    }

    private function filterPermissionIds($user, array $permissionIds): array
    {
        $query = Permission::query()->whereIn('id', $permissionIds);

        if (! $user->isPlatformAdmin()) {
            $query->whereNotIn('subject', ['Hospital', 'all']);
        }

        return $query->pluck('id')->all();
    }
}
