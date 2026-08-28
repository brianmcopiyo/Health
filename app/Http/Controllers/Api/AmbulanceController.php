<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\AmbulanceStaff;
use App\Models\AmbulanceTrip;
use App\Models\Hospital;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AmbulanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Ambulance::query()
            ->with(['staff.user', 'hospital'])
            ->orderBy('vehicle_code');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return $query->get()->map(fn (Ambulance $ambulance) => $this->serialize($ambulance));
    }

    public function store(Request $request)
    {
        $data = $this->validatedVehicle($request);
        $staffIds = $data['staff'] ?? [];
        unset($data['staff']);

        $ambulance = Ambulance::query()->create($data);
        $this->syncStaff($ambulance, $staffIds);

        return response()->json($this->serialize($ambulance->load(['staff.user', 'hospital'])), 201);
    }

    public function show(Ambulance $ambulance)
    {
        return $this->serialize(
            $ambulance->load(['staff.user', 'hospital', 'trips.driver', 'trips.destinationHospital'])
        );
    }

    public function update(Request $request, Ambulance $ambulance)
    {
        $data = $this->validatedVehicle($request, $ambulance);
        $staffIds = $data['staff'] ?? null;
        unset($data['staff']);

        $ambulance->update($data);

        if (is_array($staffIds)) {
            $this->syncStaff($ambulance, $staffIds);
        }

        return $this->serialize($ambulance->refresh()->load(['staff.user', 'hospital']));
    }

    public function destroy(Ambulance $ambulance)
    {
        abort_if($ambulance->status === 'on_trip', 422, 'Complete the active trip before removing this ambulance.');

        $ambulance->delete();

        return response()->json(['message' => 'Ambulance removed']);
    }

    public function dispatch(Request $request, Ambulance $ambulance)
    {
        abort_unless($ambulance->status === 'available', 422, 'Ambulance is not available for dispatch.');

        $user = $request->user();
        $data = $request->validate([
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'destination_hospital_id' => ['nullable', 'exists:hospitals,id'],
            'driver_user_id' => ['nullable', 'exists:users,id'],
            'referral_id' => ['nullable', 'exists:referrals,id'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['destination_hospital_id'])) {
            Hospital::query()->findOrFail($data['destination_hospital_id']);
        }

        $trip = AmbulanceTrip::query()->create([
            'hospital_id' => $ambulance->hospital_id,
            'ambulance_id' => $ambulance->id,
            'driver_user_id' => $data['driver_user_id'] ?? null,
            'origin' => $data['origin'],
            'destination' => $data['destination'],
            'destination_hospital_id' => $data['destination_hospital_id'] ?? null,
            'status' => 'dispatched',
            'dispatched_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        $ambulance->update(['status' => 'on_trip']);

        if (! empty($data['referral_id'])) {
            $referral = Referral::query()->findOrFail($data['referral_id']);
            abort_unless(
                $referral->from_hospital_id === $user->hospital_id || $user->isPlatformAdmin(),
                403,
                'This action is unauthorized.'
            );
            $referral->update([
                'ambulance_trip_id' => $trip->id,
                'status' => $referral->status === 'accepted' ? 'in_transit' : $referral->status,
            ]);
        }

        return response()->json($trip->load(['ambulance', 'driver', 'destinationHospital']), 201);
    }

    public function trips(Request $request)
    {
        $query = AmbulanceTrip::query()
            ->with(['ambulance', 'driver', 'destinationHospital'])
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->boolean('active')) {
            $query->whereIn('status', ['dispatched', 'en_route', 'arrived']);
        }

        return $query->get();
    }

    public function updateTripStatus(Request $request, AmbulanceTrip $trip)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(AmbulanceTrip::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        abort_unless($trip->isActive() || $data['status'] === 'cancelled', 422, 'Trip is already closed.');

        $trip->status = $data['status'];
        $trip->notes = $data['notes'] ?? $trip->notes;

        if ($data['status'] === 'arrived') {
            $trip->arrived_at = now();
        }

        if (in_array($data['status'], ['completed', 'cancelled'], true)) {
            $trip->completed_at = now();
            $trip->ambulance->update(['status' => 'available']);
        }

        $trip->save();

        return $trip->load(['ambulance', 'driver', 'destinationHospital']);
    }

    private function validatedVehicle(Request $request, ?Ambulance $ambulance = null): array
    {
        $hospitalId = $request->user()->isPlatformAdmin()
            ? ($request->integer('hospital_id') ?: $ambulance?->hospital_id)
            : $request->user()->hospital_id;

        $data = $request->validate([
            'hospital_id' => [$request->user()->isPlatformAdmin() ? 'nullable' : 'prohibited', 'exists:hospitals,id'],
            'vehicle_code' => [
                $ambulance ? 'sometimes' : 'required',
                'string',
                'max:50',
                Rule::unique('ambulances', 'vehicle_code')->where(fn ($query) => $query->where('hospital_id', $hospitalId))->ignore($ambulance?->id),
            ],
            'vehicle_type' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(Ambulance::STATUSES)],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'staff' => ['array'],
            'staff.*.user_id' => ['required', 'exists:users,id'],
            'staff.*.assignment_role' => ['required', 'string', 'max:50'],
        ]);

        $data['hospital_id'] = $hospitalId ?: $ambulance?->hospital_id;

        return $data;
    }

    private function syncStaff(Ambulance $ambulance, array $staff): void
    {
        $ambulance->staff()->delete();

        foreach ($staff as $member) {
            $user = User::query()->find($member['user_id']);
            abort_unless($user && ($user->belongsToHospital($ambulance->hospital_id) || $user->isPlatformAdmin()), 422, 'Staff must belong to this hospital.');

            AmbulanceStaff::query()->create([
                'ambulance_id' => $ambulance->id,
                'user_id' => $user->id,
                'assignment_role' => $member['assignment_role'],
            ]);
        }
    }

    private function serialize(Ambulance $ambulance): array
    {
        return [
            'id' => $ambulance->id,
            'hospital_id' => $ambulance->hospital_id,
            'hospital' => $ambulance->hospital,
            'vehicle_code' => $ambulance->vehicle_code,
            'vehicle_type' => $ambulance->vehicle_type,
            'status' => $ambulance->status,
            'capacity' => $ambulance->capacity,
            'notes' => $ambulance->notes,
            'staff' => $ambulance->staff,
            'active_trip' => $ambulance->relationLoaded('trips')
                ? $ambulance->trips->first(fn (AmbulanceTrip $trip) => $trip->isActive())
                : $ambulance->activeTrip(),
            'trips' => $ambulance->relationLoaded('trips') ? $ambulance->trips : [],
            'updated_at' => $ambulance->updated_at,
        ];
    }
}
