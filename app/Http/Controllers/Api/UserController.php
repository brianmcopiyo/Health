<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HospitalMembership;
use App\Models\Role;
use App\Models\User;
use App\Support\QueryList;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();

        $query = User::query()->with(['role', 'hospital', 'memberships.role', 'memberships.hospital'])->orderBy('name');

        if (! $actor->isPlatformAdmin()) {
            $query->where(function ($builder) use ($actor) {
                $builder->where('hospital_id', $actor->hospital_id)
                    ->orWhereHas('memberships', fn ($memberships) => $memberships->where('hospital_id', $actor->hospital_id));
            })->whereDoesntHave('role', fn ($role) => $role->where('slug', 'platform-admin'));
        }

        if ($search = $request->string('q')->toString()) {
            $prefix = addcslashes($search, '%_').'%';
            $query->where(function ($builder) use ($prefix) {
                $builder->where('name', 'like', $prefix)
                    ->orWhere('email', 'like', $prefix);
            });
        }

        $paginator = QueryList::paginate($query, $request);
        $paginator->getCollection()->transform(fn (User $user) => $this->serialize($user, $actor));

        return $paginator;
    }

    public function show(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless($actor->isPlatformAdmin() || $user->belongsToHospital($actor->hospital_id), 403, 'This action is unauthorized.');

        $user->load([
            'role.permissions',
            'hospital',
            'department:id,name,slug',
            'memberships.role',
            'memberships.hospital',
            'staffAssignments.department:id,name',
            'staffAssignments.facility:id,name,code',
            'encounters' => fn ($query) => $query->with('patient:id,mrn,first_name,last_name,status')->latest()->limit(12),
        ]);

        $activity = \App\Models\AuditEvent::query()
            ->where('actor_id', $user->id)
            ->latest('created_at')
            ->limit(20)
            ->get(['id', 'action', 'auditable_type', 'auditable_id', 'created_at']);

        return [
            ...$this->serialize($user, $actor),
            'department' => $user->department,
            'staff_assignments' => $user->staffAssignments,
            'encounters' => $user->encounters,
            'permissions' => $user->role?->permissions,
            'activity' => $activity,
        ];
    }

    public function directory(Request $request)
    {
        $actor = $request->user();

        $query = User::query()->with('role')->orderBy('name');

        if (! $actor->isPlatformAdmin()) {
            abort_unless($actor->hospital_id, 403, 'This action is unauthorized.');
            $query->where(function ($builder) use ($actor) {
                $builder->where('hospital_id', $actor->hospital_id)
                    ->orWhereHas('memberships', fn ($memberships) => $memberships->where('hospital_id', $actor->hospital_id));
            });
        }

        return $query->get(['id', 'name', 'email', 'job_title', 'role_id', 'hospital_id']);
    }

    public function store(Request $request)
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'hospital_id' => [$actor->isPlatformAdmin() ? 'nullable' : 'prohibited', 'exists:hospitals,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:120'],
        ]);

        $role = Role::query()->findOrFail($data['role_id']);
        abort_unless($role->isVisibleTo($actor), 422, 'Invalid role.');
        abort_if($role->slug === 'platform-admin' && ! $actor->isPlatformAdmin(), 403, 'This action is unauthorized.');

        $hospitalId = $actor->isPlatformAdmin()
            ? ($data['hospital_id'] ?? $role->hospital_id)
            : $actor->hospital_id;

        if ($role->slug !== 'platform-admin') {
            abort_unless($hospitalId, 422, 'Hospital is required for this role.');
            abort_unless($role->hospital_id === null || (string) $role->hospital_id === (string) $hospitalId, 422, 'Invalid role.');
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $role->id,
            'hospital_id' => $role->slug === 'platform-admin' ? null : $hospitalId,
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
        ]);

        if ($role->slug !== 'platform-admin') {
            $this->syncMembership($user, $hospitalId, $role->id);
        }

        return response()->json($this->serialize($user->load(['role', 'hospital', 'memberships.role', 'memberships.hospital']), $actor), 201);
    }

    public function update(Request $request, User $user)
    {
        $actor = $request->user();

        abort_unless($actor->isPlatformAdmin() || $user->belongsToHospital($actor->hospital_id), 403, 'This action is unauthorized.');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['prohibited'],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['sometimes', 'exists:roles,id'],
            'hospital_id' => [$actor->isPlatformAdmin() ? 'nullable' : 'prohibited', 'exists:hospitals,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:120'],
        ]);

        if (isset($data['role_id'])) {
            $role = Role::query()->findOrFail($data['role_id']);
            abort_unless($role->isVisibleTo($actor), 422, 'Invalid role.');
            abort_if($role->slug === 'platform-admin' && ! $actor->isPlatformAdmin(), 403, 'This action is unauthorized.');

            $hospitalId = $actor->isPlatformAdmin()
                ? ($data['hospital_id'] ?? $user->hospital_id ?? $role->hospital_id)
                : $actor->hospital_id;

            if ($role->slug === 'platform-admin') {
                $user->role_id = $role->id;
                $user->hospital_id = null;
            } else {
                abort_unless($hospitalId, 422, 'Hospital is required for this role.');
                $this->syncMembership($user, $hospitalId, $role->id);
                if ($actor->isPlatformAdmin() || $user->hospital_id === $hospitalId || ! $user->hospital_id) {
                    $user->role_id = $role->id;
                    $user->hospital_id = $hospitalId;
                }
            }
        }

        if ($actor->isPlatformAdmin() && array_key_exists('hospital_id', $data) && $user->role?->slug !== 'platform-admin') {
            $user->hospital_id = $data['hospital_id'];
        }

        $user->fill(collect($data)->only(['name', 'phone', 'job_title'])->all());

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return $this->serialize($user->load(['role', 'hospital', 'memberships.role', 'memberships.hospital']), $actor);
    }

    public function destroy(Request $request, User $user)
    {
        $actor = $request->user();

        abort_unless($actor->isPlatformAdmin() || $user->belongsToHospital($actor->hospital_id), 403, 'This action is unauthorized.');
        abort_if($user->id === $actor->id, 422, 'You cannot delete your own account.');

        if (! $actor->isPlatformAdmin() && $user->memberships()->where('hospital_id', '!=', $actor->hospital_id)->exists()) {
            $user->memberships()->where('hospital_id', $actor->hospital_id)->delete();
            if ($user->hospital_id === $actor->hospital_id) {
                $next = $user->memberships()->with('role')->first();
                $user->hospital_id = $next?->hospital_id;
                $user->role_id = $next?->role_id;
                $user->save();
            }

            return response()->json(['message' => 'User access removed']);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User removed']);
    }

    private function syncMembership(User $user, string $hospitalId, string $roleId): void
    {
        HospitalMembership::query()->updateOrCreate(
            ['user_id' => $user->id, 'hospital_id' => $hospitalId],
            ['role_id' => $roleId]
        );
    }

    private function serialize(User $user, User $actor): array
    {
        $membership = $actor->isPlatformAdmin()
            ? null
            : $user->memberships->firstWhere('hospital_id', $actor->hospital_id);

        $role = $membership?->role ?? $user->role;
        $hospital = $membership?->hospital ?? $user->hospital;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'availability' => $user->availability ?: 'available',
            'role_id' => $role?->id ?? $user->role_id,
            'hospital_id' => $hospital?->id ?? $user->hospital_id,
            'role' => $role,
            'hospital' => $hospital,
            'memberships' => $user->memberships,
        ];
    }
}
