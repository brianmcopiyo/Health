<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssistanceRequest;
use App\Models\Hospital;
use App\Support\QueryList;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssistanceRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = AssistanceRequest::query()
            ->with(['fromHospital:id,name,code', 'toHospital:id,name,code', 'creator:id,name', 'responder:id,name', 'patient:id,mrn,first_name,last_name,status', 'facility:id,name,code', 'facilityType:id,name,slug'])
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

        abort_unless($user->hospital_id, 422, 'Assistance requests must originate from a hospital.');

        $data = $request->validate([
            'to_hospital_id' => ['required', 'exists:hospitals,id'],
            'type' => ['required', Rule::in(AssistanceRequest::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'patient_id' => ['nullable', TenantRules::inHospital('patients')],
            'encounter_id' => ['nullable', TenantRules::inHospital('encounters')],
            'facility_type_id' => ['nullable', 'exists:facility_types,id'],
            'facility_id' => ['nullable', TenantRules::inHospital('facilities')],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_if((string) $data['to_hospital_id'] === (string) $user->hospital_id, 422, 'Select a different hospital.');

        $destination = Hospital::query()->findOrFail($data['to_hospital_id']);
        abort_unless($destination->is_active, 422, 'Destination hospital is inactive.');

        $requestModel = AssistanceRequest::query()->create([
            'from_hospital_id' => $user->hospital_id,
            'to_hospital_id' => $destination->id,
            'patient_id' => $data['patient_id'] ?? null,
            'encounter_id' => $data['encounter_id'] ?? null,
            'facility_type_id' => $data['facility_type_id'] ?? null,
            'facility_id' => $data['facility_id'] ?? null,
            'type' => $data['type'],
            'quantity' => $data['quantity'] ?? 1,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        return response()->json($requestModel->load(['fromHospital', 'toHospital', 'creator']), 201);
    }

    public function show(AssistanceRequest $assistanceRequest)
    {
        return $assistanceRequest->load([
            'fromHospital',
            'toHospital',
            'creator',
            'responder',
            'patient:id,mrn,first_name,last_name,status',
            'encounter:id,type,status,chief_complaint',
            'facility.type:id,name,slug',
            'facilityType:id,name,slug',
        ]);
    }

    public function updateStatus(Request $request, AssistanceRequest $assistanceRequest)
    {
        $user = $request->user();
        $data = $request->validate([
            'status' => ['required', Rule::in(AssistanceRequest::STATUSES)],
            'response_notes' => ['nullable', 'string'],
        ]);

        $status = $data['status'];
        $isDestination = $user->isPlatformAdmin() || $assistanceRequest->to_hospital_id === $user->hospital_id;
        $isOrigin = $user->isPlatformAdmin() || $assistanceRequest->from_hospital_id === $user->hospital_id;

        if (in_array($status, ['accepted', 'declined', 'fulfilled'], true)) {
            $this->authorizePermission($user, 'respond', 'AssistanceRequest');
            abort_unless($isDestination, 403, 'Only the destination hospital can respond.');
        } elseif ($status === 'cancelled') {
            $this->authorizePermission($user, 'update', 'AssistanceRequest');
            abort_unless($isOrigin, 403, 'Only the originating hospital can cancel.');
            abort_unless($assistanceRequest->status === 'pending', 422, 'Request cannot be cancelled.');
        } else {
            abort(422, 'Unsupported status transition.');
        }

        if (in_array($status, ['accepted', 'declined'], true)) {
            abort_unless($assistanceRequest->status === 'pending', 422, 'Request is not pending.');
        }

        if ($status === 'fulfilled') {
            abort_unless($assistanceRequest->status === 'accepted', 422, 'Request must be accepted first.');
        }

        $assistanceRequest->status = $status;
        $assistanceRequest->response_notes = $data['response_notes'] ?? $assistanceRequest->response_notes;

        if (in_array($status, ['accepted', 'declined', 'fulfilled'], true)) {
            $assistanceRequest->responded_by = $user->id;
            $assistanceRequest->responded_at = now();
        }

        $assistanceRequest->save();

        return $assistanceRequest->load(['fromHospital', 'toHospital', 'creator', 'responder']);
    }
}
