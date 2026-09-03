<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\AmbulanceStaff;
use App\Models\AmbulanceTrip;
use App\Models\Hospital;
use App\Models\Referral;
use App\Models\User;
use App\Support\Audit;
use App\Support\ChargeLedger;
use App\Support\QueryList;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if ($search = $request->string('q')->toString()) {
            $term = QueryList::term($search);
            if ($term) {
                $query->where(function ($builder) use ($term) {
                    $builder->where('vehicle_code', 'like', $term)
                        ->orWhere('vehicle_type', 'like', $term)
                        ->orWhere('notes', 'like', $term);
                });
            }
        }

        QueryList::equals($query, $request, 'vehicle_type');

        $paginator = QueryList::paginate($query, $request);
        $paginator->getCollection()->transform(fn (Ambulance $ambulance) => $this->serialize($ambulance));

        return $paginator;
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
            $ambulance->load(['staff.user', 'hospital', 'trips.driver', 'trips.destinationHospital', 'trips.patient', 'trips.referral:id,status,from_hospital_id,to_hospital_id', 'trips.encounter:id,type,status'])
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
        abort_if($ambulance->trips()->exists(), 422, 'This ambulance has trip history and cannot be deleted.');

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
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'destination_hospital_id' => ['nullable', 'exists:hospitals,id'],
            'destination_facility_id' => ['nullable', 'exists:facilities,id'],
            'driver_user_id' => ['nullable', 'exists:users,id'],
            'referral_id' => ['nullable', 'exists:referrals,id'],
            'patient_id' => ['nullable', TenantRules::inHospital('patients')],
            'encounter_id' => ['nullable', TenantRules::inHospital('encounters')],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['destination_hospital_id'])) {
            Hospital::query()->findOrFail($data['destination_hospital_id']);
        }

        $referral = ! empty($data['referral_id']) ? Referral::query()->find($data['referral_id']) : null;

        $trip = DB::transaction(function () use ($ambulance, $data, $referral, $user) {
            $ambulance = Ambulance::query()->lockForUpdate()->findOrFail($ambulance->id);
            abort_unless($ambulance->status === 'available', 422, 'Ambulance is not available for dispatch.');

            $trip = AmbulanceTrip::query()->create([
                'hospital_id' => $ambulance->hospital_id,
                'ambulance_id' => $ambulance->id,
                'patient_id' => $data['patient_id'] ?? $referral?->patient_id,
                'encounter_id' => $data['encounter_id'] ?? $referral?->encounter_id,
                'referral_id' => $referral?->id,
                'driver_user_id' => $data['driver_user_id'] ?? null,
                'origin' => $data['origin'],
                'pickup_location' => $data['pickup_location'] ?? $data['origin'],
                'destination' => $data['destination'],
                'destination_hospital_id' => $data['destination_hospital_id'] ?? $referral?->to_hospital_id,
                'destination_facility_id' => $data['destination_facility_id'] ?? $referral?->destination_facility_id,
                'status' => 'dispatched',
                'dispatched_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $ambulance->update(['status' => 'on_trip']);

            if ($referral) {
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

            Audit::record('dispatched', $trip, ['ambulance_id' => $ambulance->id]);

            return $trip;
        });

        return response()->json($trip->load(['ambulance', 'driver', 'destinationHospital', 'patient', 'encounter']), 201);
    }

    public function trips(Request $request)
    {
        $query = AmbulanceTrip::query()
            ->with(['ambulance', 'driver', 'destinationHospital', 'patient', 'encounter', 'linkedReferral'])
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->boolean('active')) {
            $query->whereIn('status', ['dispatched', 'en_route', 'arrived']);
        }

        return QueryList::paginate($query, $request);
    }

    public function updateTripStatus(Request $request, AmbulanceTrip $trip)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(AmbulanceTrip::STATUSES)],
            'notes' => ['nullable', 'string'],
            'handover_notes' => ['nullable', 'string'],
        ]);

        abort_unless($trip->isActive() || $data['status'] === 'cancelled', 422, 'Trip is already closed.');

        DB::transaction(function () use ($trip, $data) {
            $trip = AmbulanceTrip::query()->lockForUpdate()->findOrFail($trip->id);
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

            if ($data['status'] === 'completed') {
                $trip->handover_at = now();
                $trip->handover_notes = $data['handover_notes'] ?? $trip->handover_notes;
                if ($trip->encounter_id) {
                    ChargeLedger::forService($trip->encounter, 'AMB-TRP', 'ambulance', $trip->id);
                }
                if ($trip->referral_id) {
                    $referral = Referral::query()->find($trip->referral_id);
                    if ($referral && $referral->status === 'in_transit') {
                        $referral->update(['status' => 'completed']);
                    }
                }
            }

            $trip->save();
            Audit::record('status_changed', $trip, ['to' => $data['status']]);
        });

        return $trip->refresh()->load(['ambulance', 'driver', 'destinationHospital']);
    }

    private function validatedVehicle(Request $request, ?Ambulance $ambulance = null): array
    {
        $hospitalId = $request->user()->isPlatformAdmin()
            ? ($request->input('hospital_id') ?: $ambulance?->hospital_id)
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
