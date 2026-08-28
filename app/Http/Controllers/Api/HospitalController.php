<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Support\HospitalProvisioner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HospitalController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403, 'This action is unauthorized.');

        return Hospital::query()->orderBy('name')->get();
    }

    public function network(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermission('read', 'Referral')
            || $user->hasPermission('create', 'AssistanceRequest')
            || $user->hasPermission('read', 'Hospital'),
            403,
            'This action is unauthorized.'
        );

        $query = Hospital::query()->where('is_active', true)->orderBy('name');

        if (! $user->isPlatformAdmin() && $user->hospital_id) {
            $query->where('id', '!=', $user->hospital_id);
        }

        return $query->get(['id', 'name', 'code', 'city', 'region', 'phone']);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403, 'This action is unauthorized.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:hospitals,code'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $hospital = Hospital::query()->create($data);
        HospitalProvisioner::bootstrap($hospital);

        return response()->json($hospital, 201);
    }

    public function show(Request $request, Hospital $hospital)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403, 'This action is unauthorized.');

        return $hospital;
    }

    public function update(Request $request, Hospital $hospital)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403, 'This action is unauthorized.');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('hospitals', 'code')->ignore($hospital->id)],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $hospital->update($data);

        return $hospital->refresh();
    }

    public function destroy(Request $request, Hospital $hospital)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403, 'This action is unauthorized.');

        $hospital->delete();

        return response()->json(['message' => 'Hospital removed']);
    }
}
