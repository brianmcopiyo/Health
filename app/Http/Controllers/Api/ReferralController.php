<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Encounter;
use App\Models\Referral;
use App\Support\Audit;
use App\Support\QueryList;
use App\Support\ReferralHandover;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReferralController extends Controller
{
    public function eligibleHospitals(Request $request)
    {
        $data = $request->validate([
            'facility_type_id' => ['required', 'exists:facility_types,id'],
            'required_capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $required = (int) ($data['required_capacity'] ?? 1);
        $originId = $request->user()->hospital_id;

        $facilities = Facility::withoutGlobalScope('hospital')
            ->with('hospital:id,name,code,city,region,is_active')
            ->where('facility_type_id', $data['facility_type_id'])
            ->hasRemainingCapacity($required)
            ->whereHas('hospital', fn ($hospital) => $hospital->where('is_active', true))
            ->when($originId, fn ($query) => $query->where('hospital_id', '!=', $originId))
            ->get(['id', 'hospital_id', 'name', 'code', 'status', 'capacity', 'current_utilization', 'facility_type_id']);

        return $facilities
            ->groupBy('hospital_id')
            ->map(function ($group) {
                $hospital = $group->first()->hospital;

                return [
                    'id' => $hospital->id,
                    'name' => $hospital->name,
                    'code' => $hospital->code,
                    'city' => $hospital->city,
                    'region' => $hospital->region,
                    'available_facilities' => $group->map(fn (Facility $facility) => [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'code' => $facility->code,
                        'status' => $facility->status,
                        'capacity' => $facility->capacity,
                        'current_utilization' => $facility->current_utilization,
                        'remaining_capacity' => $facility->remainingCapacity(),
                    ])->values(),
                ];
            })
            ->values();
    }

    public function index(Request $request)
    {
        $query = Referral::query()
            ->with([
                'fromHospital:id,name,code,city,region',
                'toHospital:id,name,code,city,region',
                'requiredFacilityType:id,name,slug',
                'destinationFacility:id,name,code,status',
                'creator:id,name',
                'reviewer:id,name',
                'patient:id,mrn,first_name,last_name,status',
                'encounter:id,type,status,chief_complaint',
                'referringClinician:id,name',
            ])
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($direction = $request->string('direction')->toString()) {
            $hospitalId = $request->user()->hospital_id;
            if ($direction === 'incoming' && $hospitalId) {
                $query->where('to_hospital_id', $hospitalId);
            }
            if ($direction === 'outgoing' && $hospitalId) {
                $query->where('from_hospital_id', $hospitalId);
            }
        }

        return QueryList::paginate($query, $request);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hospital_id, 422, 'Referrals must originate from a hospital.');

        $data = $request->validate([
            'to_hospital_id' => ['required', 'exists:hospitals,id'],
            'patient_id' => ['nullable', TenantRules::inHospital('patients')],
            'encounter_id' => ['nullable', TenantRules::inHospital('encounters')],
            'referring_clinician_id' => ['nullable', 'exists:users,id'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'patient_reference' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string'],
            'required_facility_type_id' => ['required', 'exists:facility_types,id'],
            'required_service_id' => ['nullable', TenantRules::inHospital('clinical_services')],
            'required_capacity' => ['nullable', 'integer', 'min:1'],
            'destination_facility_id' => ['nullable', 'exists:facilities,id'],
        ]);

        $encounter = ! empty($data['encounter_id']) ? Encounter::query()->with('patient:id,first_name,last_name,mrn')->find($data['encounter_id']) : null;
        if ($encounter) {
            $data['patient_id'] = $data['patient_id'] ?? $encounter->patient_id;
            $data['referring_clinician_id'] = $data['referring_clinician_id'] ?? $encounter->clinician_id ?? $user->id;
            $data['patient_name'] = $data['patient_name'] ?? $encounter->patient?->fullName();
            $data['patient_reference'] = $data['patient_reference'] ?? $encounter->patient?->mrn;
        } elseif (! empty($data['patient_id']) && empty($data['patient_name'])) {
            $patient = \App\Models\Patient::query()->find($data['patient_id'], ['id', 'first_name', 'last_name', 'mrn']);
            $data['patient_name'] = $patient?->fullName();
            $data['patient_reference'] = $data['patient_reference'] ?? $patient?->mrn;
        }

        abort_unless(($data['patient_name'] ?? null) || ($data['patient_id'] ?? null), 422, 'Patient is required.');

        abort_if((string) $data['to_hospital_id'] === (string) $user->hospital_id, 422, 'Select a different hospital.');

        $required = $data['required_capacity'] ?? 1;
        $destination = Hospital::query()->findOrFail($data['to_hospital_id']);
        abort_unless($destination->is_active, 422, 'Destination hospital is inactive.');

        $eligible = Facility::withoutGlobalScope('hospital')
            ->where('hospital_id', $destination->id)
            ->where('facility_type_id', $data['required_facility_type_id'])
            ->hasRemainingCapacity($required)
            ->get(['id', 'hospital_id', 'capacity', 'current_utilization', 'status']);

        abort_if($eligible->isEmpty(), 422, 'Destination hospital does not have the required capacity.');

        $destinationFacilityId = $data['destination_facility_id'] ?? null;
        if ($destinationFacilityId) {
            abort_unless($eligible->contains('id', $destinationFacilityId), 422, 'Selected facility is not available.');
        }

        $referral = DB::transaction(function () use ($user, $destination, $data, $required, $destinationFacilityId, $encounter) {
            $referral = Referral::query()->create([
                'from_hospital_id' => $user->hospital_id,
                'to_hospital_id' => $destination->id,
                'patient_id' => $data['patient_id'] ?? null,
                'encounter_id' => $data['encounter_id'] ?? null,
                'referring_clinician_id' => $data['referring_clinician_id'] ?? $user->id,
                'patient_name' => $data['patient_name'] ?? optional($encounter?->patient)->fullName(),
                'patient_reference' => $data['patient_reference'] ?? optional($encounter?->patient)->mrn,
                'reason' => $data['reason'],
                'required_facility_type_id' => $data['required_facility_type_id'],
                'required_service_id' => $data['required_service_id'] ?? null,
                'required_capacity' => $required,
                'destination_facility_id' => $destinationFacilityId,
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            if ($encounter && $encounter->isOpen()) {
                $encounter->update(['status' => 'transferred']);
            }

            Audit::record('created', $referral, ['to_hospital_id' => $destination->id]);

            return $referral;
        });

        return response()->json($referral->load(['fromHospital', 'toHospital', 'requiredFacilityType', 'destinationFacility', 'creator']), 201);
    }

    public function show(Referral $referral)
    {
        return $referral->load([
            'fromHospital',
            'toHospital',
            'requiredFacilityType',
            'destinationFacility',
            'creator',
            'reviewer',
            'ambulanceTrip',
            'patient:id,mrn,first_name,last_name,status',
            'encounter:id,type,status,chief_complaint',
        ]);
    }

    public function updateStatus(Request $request, Referral $referral)
    {
        $user = $request->user();
        $data = $request->validate([
            'status' => ['required', Rule::in(Referral::STATUSES)],
            'response_notes' => ['nullable', 'string'],
            'destination_facility_id' => ['nullable', 'exists:facilities,id'],
        ]);

        $status = $data['status'];
        $isDestination = $user->isPlatformAdmin() || $referral->to_hospital_id === $user->hospital_id;
        $isOrigin = $user->isPlatformAdmin() || $referral->from_hospital_id === $user->hospital_id;

        if (in_array($status, ['accepted', 'declined', 'more_info'], true)) {
            $this->authorizePermission($user, 'respond', 'Referral');
            abort_unless($isDestination, 403, 'Only the destination hospital can respond.');
            abort_unless($referral->status === 'pending' || $referral->status === 'more_info', 422, 'Referral is not pending.');
        } elseif ($status === 'cancelled') {
            $this->authorizePermission($user, 'update', 'Referral');
            abort_unless($isOrigin, 403, 'Only the originating hospital can cancel.');
            abort_unless(in_array($referral->status, ['pending', 'accepted'], true), 422, 'Referral cannot be cancelled.');
        } elseif (in_array($status, ['in_transit', 'completed'], true)) {
            abort_unless($isOrigin || $isDestination, 403, 'This action is unauthorized.');
            abort_unless($user->hasPermission('update', 'Referral') || $user->hasPermission('respond', 'Referral'), 403, 'This action is unauthorized.');
        } else {
            abort(422, 'Unsupported status transition.');
        }

        DB::transaction(function () use ($referral, $data, $status, $user) {
            $previous = $referral->status;

            if ($status === 'accepted') {
                $facilityId = $data['destination_facility_id'] ?? $referral->destination_facility_id;
                $facility = $this->claimFacility($referral, $facilityId);
                $referral->destination_facility_id = $facility->id;
                ReferralHandover::accept($referral, $user, $facility);
            }

            if (in_array($status, ['declined', 'cancelled', 'completed'], true) && $referral->destination_facility_id && $referral->status === 'accepted') {
                $this->releaseFacility($referral);
            }

            if ($status === 'declined') {
                $referral->reviewed_by = $user->id;
                $referral->reviewed_at = now();
            }

            $referral->status = $status;
            $referral->response_notes = $data['response_notes'] ?? $referral->response_notes;
            $referral->save();
            Audit::record('status_changed', $referral, ['from' => $previous, 'to' => $status]);
        });

        return $referral->load(['fromHospital', 'toHospital', 'requiredFacilityType', 'destinationFacility', 'creator', 'reviewer']);
    }

    private function claimFacility(Referral $referral, ?string $facilityId): Facility
    {
        $query = Facility::withoutGlobalScope('hospital')
            ->where('hospital_id', $referral->to_hospital_id)
            ->where('facility_type_id', $referral->required_facility_type_id)
            ->hasRemainingCapacity($referral->required_capacity)
            ->lockForUpdate();

        $facility = $facilityId
            ? (clone $query)->where('id', $facilityId)->first()
            : $query->orderByDesc('capacity')->first();

        abort_unless($facility, 422, 'Required facility is no longer available.');

        $facility->adjustUtilization($referral->required_capacity);

        return $facility;
    }

    private function releaseFacility(Referral $referral): void
    {
        $facility = Facility::withoutGlobalScope('hospital')->lockForUpdate()->find($referral->destination_facility_id);

        if ($facility) {
            $facility->adjustUtilization(-1 * $referral->required_capacity);
        }
    }
}
