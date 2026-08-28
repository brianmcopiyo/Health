<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssistanceRequest;
use App\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssistanceRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = AssistanceRequest::query()
            ->with(['fromHospital', 'toHospital', 'creator', 'responder'])
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

        return $query->get();
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
        ]);

        abort_if((int) $data['to_hospital_id'] === (int) $user->hospital_id, 422, 'Select a different hospital.');

        $destination = Hospital::query()->findOrFail($data['to_hospital_id']);
        abort_unless($destination->is_active, 422, 'Destination hospital is inactive.');

        $requestModel = AssistanceRequest::query()->create([
            'from_hospital_id' => $user->hospital_id,
            'to_hospital_id' => $destination->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        return response()->json($requestModel->load(['fromHospital', 'toHospital', 'creator']), 201);
    }

    public function show(AssistanceRequest $assistanceRequest)
    {
        return $assistanceRequest->load(['fromHospital', 'toHospital', 'creator', 'responder']);
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
