<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EncounterController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->string('type')->toString();
        abort_unless($type, 422, 'Encounter type is required.');

        $subject = $this->subjectForType($type);
        abort_unless(
            $request->user()->hasPermission('read', $subject)
            || $request->user()->hasPermission('create', $subject),
            403,
            'This action is unauthorized.'
        );

        $query = Encounter::query()
            ->with(['patient', 'clinician', 'facility', 'department'])
            ->where('type', $type)
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'type' => ['required', Rule::in(Encounter::TYPES)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'clinician_id' => ['nullable', 'exists:users,id'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'chief_complaint' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $subject = $this->subjectForType($data['type']);
        abort_unless(
            $request->user()->hasPermission('create', $subject) || $request->user()->hasPermission('create', 'Reception'),
            403,
            'This action is unauthorized.'
        );

        $encounter = Encounter::query()->create([
            ...$data,
            'hospital_id' => $request->user()->hospital_id,
            'status' => 'waiting',
        ]);

        return response()->json($encounter->load(['patient', 'clinician', 'facility', 'department']), 201);
    }

    public function update(Request $request, Encounter $encounter)
    {
        $subject = $this->subjectForType($encounter->type);
        $this->authorizePermission($request->user(), 'update', $subject);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(Encounter::STATUSES)],
            'clinician_id' => ['nullable', 'exists:users,id'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'chief_complaint' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if (($data['status'] ?? null) === 'in_progress' && ! $encounter->started_at) {
            $data['started_at'] = now();
        }

        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = now();
        }

        $encounter->update($data);

        return $encounter->refresh()->load(['patient', 'clinician', 'facility', 'department']);
    }

    private function subjectForType(string $type): string
    {
        return match ($type) {
            'opd' => 'Opd',
            'emergency' => 'Emergency',
            'admission' => 'Ward',
            default => 'Reception',
        };
    }
}
