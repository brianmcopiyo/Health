<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BedAssignment;
use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Support\Audit;
use App\Support\ChargeLedger;
use App\Support\QueryList;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BedAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = BedAssignment::query()
            ->with(['patient:id,mrn,first_name,last_name,status', 'facility:id,name,code,status', 'assignedBy:id,name', 'encounter:id,type,status', 'nurse:id,name', 'ward:id,name,code'])
            ->latest();

        if ($request->string('status')->toString() !== 'all') {
            $query->where('status', $request->string('status')->toString() ?: 'active');
        }

        return QueryList::paginate($query, $request);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', TenantRules::inHospital('patients')],
            'facility_id' => ['required', TenantRules::inHospital('facilities')],
            'encounter_id' => ['nullable', TenantRules::inHospital('encounters')],
            'nurse_id' => ['nullable', 'exists:users,id'],
        ]);

        $assignment = DB::transaction(function () use ($data, $request) {
            $facility = Facility::query()->with(['type', 'parent'])->lockForUpdate()->findOrFail($data['facility_id']);
            abort_unless($facility->type?->slug === 'bed', 422, 'Select a bed.');
            abort_unless($facility->isAvailableFor(1) || $facility->status === 'cleaning', 422, 'Bed is not available.');
            abort_if($facility->status === 'cleaning', 422, 'Bed is being cleaned.');

            $existing = BedAssignment::query()
                ->where('patient_id', $data['patient_id'])
                ->where('status', 'active')
                ->lockForUpdate()
                ->exists();
            abort_if($existing, 422, 'Patient already has an active bed assignment.');

            $encounter = ! empty($data['encounter_id'])
                ? Encounter::query()->findOrFail($data['encounter_id'])
                : Encounter::query()
                    ->where('patient_id', $data['patient_id'])
                    ->where('type', 'admission')
                    ->whereIn('status', ['waiting', 'in_progress'])
                    ->latest()
                    ->first();

            if (! $encounter) {
                $encounter = Encounter::query()->create([
                    'hospital_id' => $request->user()->hospital_id,
                    'patient_id' => $data['patient_id'],
                    'department_id' => Department::query()->where('slug', 'wards')->value('id'),
                    'facility_id' => $facility->parent_id ?: $facility->id,
                    'type' => 'admission',
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'admitted_at' => now(),
                ]);
                ChargeLedger::forService($encounter, 'ADM-DAY', 'encounter', $encounter->id);
            }

            $assignment = BedAssignment::query()->create([
                'hospital_id' => $request->user()->hospital_id,
                'patient_id' => $data['patient_id'],
                'facility_id' => $facility->id,
                'ward_id' => $facility->parent_id,
                'encounter_id' => $encounter->id,
                'assigned_by' => $request->user()->id,
                'nurse_id' => $data['nurse_id'] ?? $request->user()->id,
                'status' => 'active',
                'assigned_at' => now(),
            ]);

            $facility->adjustUtilization(1);
            $encounter->patient->update(['status' => 'admitted']);
            $encounter->addClinician($assignment->nurse_id, 'nurse');
            Audit::record('assigned', $assignment, ['facility_id' => $facility->id]);

            return $assignment;
        });

        return response()->json($assignment->load(['patient', 'facility', 'assignedBy', 'encounter', 'nurse']), 201);
    }

    public function discharge(Request $request, BedAssignment $bedAssignment)
    {
        abort_unless($bedAssignment->status === 'active', 422, 'Assignment is already closed.');

        DB::transaction(function () use ($bedAssignment) {
            $bedAssignment = BedAssignment::query()->lockForUpdate()->findOrFail($bedAssignment->id);
            abort_unless($bedAssignment->status === 'active', 422, 'Assignment is already closed.');

            $bedAssignment->update([
                'status' => 'discharged',
                'discharged_at' => now(),
            ]);

            $facility = Facility::query()->with('type')->lockForUpdate()->find($bedAssignment->facility_id);
            $facility?->adjustUtilization(-1);
            if ($facility?->type?->slug === 'bed') {
                $facility->update(['status' => 'cleaning']);
            }

            $patient = $bedAssignment->patient;
            if ($patient && ! $patient->bedAssignments()->where('status', 'active')->exists()) {
                $patient->update(['status' => 'active']);
            }

            Audit::record('discharged', $bedAssignment);
        });

        return $bedAssignment->refresh()->load(['patient', 'facility', 'assignedBy']);
    }
}
