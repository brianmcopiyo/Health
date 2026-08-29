<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Department;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load(['role', 'hospital', 'department']);

        return $this->payload($user);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'specialty' => ['nullable', 'string', 'max:120'],
            'license_number' => ['nullable', 'string', 'max:80'],
            'department_id' => [
                'nullable',
                $hospitalId
                    ? Rule::exists('departments', 'id')->where(fn ($query) => $query->where('hospital_id', $hospitalId))
                    : 'prohibited',
            ],
            'availability' => ['sometimes', Rule::in(['available', 'busy', 'away'])],
            'preferences' => ['sometimes', 'array'],
            'preferences.referrals' => ['sometimes', 'boolean'],
            'preferences.encounters' => ['sometimes', 'boolean'],
            'preferences.laboratory' => ['sometimes', 'boolean'],
            'preferences.pharmacy' => ['sometimes', 'boolean'],
            'preferences.invoices' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['preferences'])) {
            $data['preferences'] = array_merge($user->preferenceMap(), $data['preferences']);
        }

        $user->fill(collect($data)->only([
            'name', 'phone', 'job_title', 'specialty', 'license_number', 'department_id', 'availability', 'preferences',
        ])->all());
        $user->save();

        Audit::record('updated', $user, array_keys($data));

        return $this->payload($user->fresh()->load(['role', 'hospital', 'department']));
    }

    public function password(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        abort_if(Hash::check($data['password'], $user->password), 422, 'Choose a different password.');

        $user->password = $data['password'];
        $user->save();

        $current = $user->currentAccessToken();
        $currentId = isset($current->id) ? $current->id : null;
        $user->tokens()->when($currentId, fn ($query) => $query->where('id', '!=', $currentId))->delete();

        Audit::record('updated', $user, ['password']);

        return response()->json(['message' => 'Password updated']);
    }

    public function email(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->email = $data['email'];
        $user->save();

        Audit::record('updated', $user, ['email']);

        return $this->payload($user->fresh()->load(['role', 'hospital', 'department']));
    }

    public function avatar(Request $request)
    {
        $user = $request->user();
        abort_unless($user->avatar_path && Storage::disk('avatars')->exists($user->avatar_path), 404, 'No profile photo.');

        $contents = Storage::disk('avatars')->get($user->avatar_path);
        $mime = match (strtolower(pathinfo((string) $user->avatar_path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="avatar"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $user = $request->user();
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $data = $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $file = $data['file'];
        $extension = strtolower((string) $file->getClientOriginalExtension());
        abort_unless(in_array($extension, $allowed, true), 422, 'Use a JPG, PNG, or WEBP image.');

        $contents = $file->getContent();
        abort_if($contents === false || $contents === '', 422, 'The file could not be read.');

        if ($user->avatar_path) {
            Storage::disk('avatars')->delete($user->avatar_path);
        }

        $path = $user->id.'.'.$extension;
        Storage::disk('avatars')->put($path, $contents);
        $user->avatar_path = $path;
        $user->save();

        Audit::record('updated', $user, ['avatar']);

        return $this->payload($user->fresh()->load(['role', 'hospital', 'department']));
    }

    public function destroyAvatar(Request $request)
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('avatars')->delete($user->avatar_path);
        }
        $user->avatar_path = null;
        $user->save();

        Audit::record('updated', $user, ['avatar']);

        return $this->payload($user->fresh()->load(['role', 'hospital', 'department']));
    }

    public function sessions(Request $request)
    {
        $user = $request->user();
        $current = $user->currentAccessToken();
        $currentId = isset($current->id) ? $current->id : null;

        $items = $user->tokens()->latest()->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'ip_address' => $token->ip_address,
            'user_agent' => $token->user_agent,
            'last_used_at' => $token->last_used_at,
            'created_at' => $token->created_at,
            'expires_at' => $token->expires_at,
            'is_current' => $currentId !== null && (int) $token->id === (int) $currentId,
        ]);

        return ['data' => $items];
    }

    public function destroySession(Request $request, int $session)
    {
        $user = $request->user();
        $token = $user->tokens()->whereKey($session)->first();
        abort_unless($token, 403, 'This action is unauthorized.');

        $current = $user->currentAccessToken();
        $isCurrent = isset($current->id) && (int) $current->id === (int) $token->id;
        $token->delete();

        if ($isCurrent) {
            return response()->json(['message' => 'Signed out', 'signed_out' => true]);
        }

        return response()->json(['message' => 'Session revoked']);
    }

    public function revokeOtherSessions(Request $request)
    {
        $user = $request->user();
        $current = $user->currentAccessToken();
        $currentId = isset($current->id) ? $current->id : null;
        abort_unless($currentId, 422, 'This session cannot revoke others.');

        $user->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json(['message' => 'Other sessions signed out']);
    }

    public function activity(Request $request)
    {
        $user = $request->user();
        $hospitalIds = $user->memberships()->pluck('hospital_id')->filter()->push($user->hospital_id)->unique()->values();

        $query = AuditEvent::query()
            ->with('hospital:id,name,code')
            ->where('actor_id', $user->id)
            ->latest('created_at');

        if (! $user->isPlatformAdmin()) {
            $query->where(function ($builder) use ($hospitalIds) {
                $builder->whereIn('hospital_id', $hospitalIds)->orWhereNull('hospital_id');
            });
        }

        $events = $query->limit(50)->get()->map(fn (AuditEvent $event) => [
            'id' => $event->id,
            'action' => $event->action,
            'entity' => class_basename((string) $event->auditable_type),
            'hospital' => $event->hospital?->name,
            'at' => $event->created_at,
        ]);

        return ['data' => $events];
    }

    private function payload($user): array
    {
        $departments = $user->hospital_id
            ? Department::query()
                ->where('hospital_id', $user->hospital_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $session = $user->toSessionPayload();
        $session['profile'] = [
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'specialty' => $user->specialty,
            'license_number' => $user->license_number,
            'department_id' => $user->department_id,
            'department_name' => $user->department?->name,
            'availability' => $user->availability ?: 'available',
            'has_avatar' => (bool) $user->avatar_path,
            'preferences' => $user->preferenceMap(),
            'departments' => $departments,
        ];

        return $session;
    }
}
