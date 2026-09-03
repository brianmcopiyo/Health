<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Patient;
use App\Support\HospitalProvisioner;
use App\Support\QueryList;
use Illuminate\Http\Request;

class HospitalController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403, 'This action is unauthorized.');

        $query = Hospital::query()->orderBy('name');
        if ($search = $request->string('q')->toString()) {
            $term = QueryList::term($search);
            if ($term) {
                $query->where(fn ($builder) => $builder
                    ->where('name', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('region', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            }
        }
        QueryList::boolean($query, $request, 'is_active');

        return $query->get();
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

        return $query->get(['id', 'name', 'city', 'region', 'phone']);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403, 'This action is unauthorized.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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

        $hospital->load([
            'departments:id,hospital_id,name,slug,module_key,is_active',
            'facilities.type:id,name,slug',
            'users:id,name,email,job_title,role_id,hospital_id',
            'users.role:id,name,slug',
            'ambulances:id,hospital_id,vehicle_code,vehicle_type,status,capacity',
            'roles:id,hospital_id,name,slug,workspace',
        ]);

        $beds = $hospital->facilities->filter(fn ($facility) => $facility->type?->slug === 'bed');

        return [
            ...$hospital->toArray(),
            'capacity' => [
                'facilities' => $hospital->facilities->count(),
                'beds' => $beds->sum('capacity'),
                'occupied' => $beds->sum('current_utilization'),
                'staff' => $hospital->users->count(),
            ],
        ];
    }

    public function update(Request $request, Hospital $hospital)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403, 'This action is unauthorized.');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
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
        abort_if(Patient::withoutGlobalScope('hospital')->where('hospital_id', $hospital->id)->exists(), 422, 'This hospital has clinical records and cannot be deleted.');

        $hospital->delete();

        return response()->json(['message' => 'Hospital removed']);
    }
}
