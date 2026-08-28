<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BedAssignment;
use App\Models\Facility;
use Illuminate\Http\Request;

class BedAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = BedAssignment::query()
            ->with(['patient', 'facility', 'assignedBy', 'encounter'])
            ->latest();

        if ($request->string('status')->toString() !== 'all') {
            $query->where('status', $request->string('status')->toString() ?: 'active');
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'facility_id' => ['required', 'exists:facilities,id'],
            'encounter_id' => ['nullable', 'exists:encounters,id'],
        ]);

        $facility = Facility::query()->with('type')->findOrFail($data['facility_id']);
        abort_unless($facility->type?->slug === 'bed', 422, 'Select a bed.');
        abort_unless($facility->isAvailableFor(1), 422, 'Bed is not available.');

        $existing = BedAssignment::query()
            ->where('patient_id', $data['patient_id'])
            ->where('status', 'active')
            ->exists();
        abort_if($existing, 422, 'Patient already has an active bed assignment.');

        $assignment = BedAssignment::query()->create([
            'hospital_id' => $request->user()->hospital_id,
            'patient_id' => $data['patient_id'],
            'facility_id' => $facility->id,
            'encounter_id' => $data['encounter_id'] ?? null,
            'assigned_by' => $request->user()->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $facility->adjustUtilization(1);

        return response()->json($assignment->load(['patient', 'facility', 'assignedBy']), 201);
    }

    public function discharge(Request $request, BedAssignment $bedAssignment)
    {
        abort_unless($bedAssignment->status === 'active', 422, 'Assignment is already closed.');

        $bedAssignment->update([
            'status' => 'discharged',
            'discharged_at' => now(),
        ]);

        $facility = Facility::query()->find($bedAssignment->facility_id);
        $facility?->adjustUtilization(-1);

        return $bedAssignment->refresh()->load(['patient', 'facility', 'assignedBy']);
    }
}
