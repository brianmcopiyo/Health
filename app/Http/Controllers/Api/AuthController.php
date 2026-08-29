<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Access\AccountStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->with(['role.permissions', 'hospital'])->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email or Password is Invalid'],
            ]);
        }

        if (! $user->canAuthenticate()) {
            throw ValidationException::withMessages([
                'email' => [AccountStatus::denialMessage($user->status)],
            ]);
        }

        if ($user->hospital && ! $user->hospital->is_active && ! $user->isPlatformAdmin()) {
            throw ValidationException::withMessages([
                'email' => ['This hospital account is inactive'],
            ]);
        }

        $created = $user->createToken(
            'Caregrid workspace',
            ['*'],
            now()->addMinutes((int) config('hms.token_minutes', 480))
        );
        $created->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ])->save();

        $user->recordLogin();

        return response()->json(array_merge($user->toSessionPayload(), [
            'accessToken' => $created->plainTextToken,
        ]));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->toSessionPayload());
    }

    public function switchHospital(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'hospital_id' => ['required', 'exists:hospitals,id'],
        ]);

        $membership = $user->memberships()->with(['role', 'hospital'])->where('hospital_id', $data['hospital_id'])->first();
        abort_unless($membership, 403, 'This action is unauthorized.');
        abort_unless($membership->hospital?->is_active, 422, 'This hospital account is inactive');

        $user->hospital_id = $membership->hospital_id;
        $user->role_id = $membership->role_id;
        $user->save();
        $user->load(['role.permissions', 'hospital']);

        return response()->json($user->toSessionPayload());
    }
}
