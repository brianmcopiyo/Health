<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarePlan;
use App\Models\ClinicalNote;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\Encounter;
use App\Models\EncounterClinician;
use App\Models\Facility;
use App\Models\Vital;
use App\Support\Audit;
use App\Support\ChargeLedger;
use App\Support\ClinicalPayload;
use App\Support\QueryList;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EncounterController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->string('type')->toString();
        $mine = $request->boolean('mine');

        $query = Encounter::query()
            ->with([
                'patient:id,hospital_id,mrn,first_name,last_name,sex,status',
                'clinician:id,name',
                'facility:id,name,code,status',
                'department:id,name',
            ])
            ->latest();

        if ($type) {
            $subject = $this->subjectForType($type);
            abort_unless(
                $request->user()->hasPermission('read', $subject)
                || $request->user()->hasPermission('create', $subject)
                || $request->user()->hasPermission('read', 'Referral'),
                403,
                'This action is unauthorized.'
            );
            $query->where('type', $type);
        } else {
            abort_unless($this->canListEncounters($request->user()), 403, 'This action is unauthorized.');
        }

        if ($type === 'admission') {
            $query->with(['bedAssignments' => fn ($assignments) => $assignments->where('status', 'active')->with('facility:id,name,code,status')]);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        } elseif ($request->boolean('open')) {
            $query->whereIn('status', ['waiting', 'in_progress']);
        }

        if ($mine) {
            $userId = $request->user()->id;
            $query->where(function ($builder) use ($userId) {
                $builder->where('clinician_id', $userId)
                    ->orWhereIn('id', EncounterClinician::query()->select('encounter_id')->where('user_id', $userId));
            });
        }

        if ($patientId = $request->integer('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        return QueryList::paginate($query, $request, $patientId ? 50 : 25);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', TenantRules::inHospital('patients')],
            'type' => ['required', Rule::in(Encounter::TYPES)],
            'department_id' => ['nullable', TenantRules::inHospital('departments')],
            'clinician_id' => ['nullable', 'exists:users,id'],
            'facility_id' => ['nullable', TenantRules::inHospital('facilities')],
            'chief_complaint' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'parent_encounter_id' => ['nullable', TenantRules::inHospital('encounters')],
        ]);

        $subject = $this->subjectForType($data['type']);
        abort_unless(
            $request->user()->hasPermission('create', $subject)
            || $request->user()->hasPermission('create', 'Reception'),
            403,
            'This action is unauthorized.'
        );

        if (empty($data['department_id'])) {
            $data['department_id'] = Department::query()
                ->where('module_key', $this->moduleForType($data['type']))
                ->value('id');
        }

        $encounter = DB::transaction(function () use ($data, $request) {
            $encounter = Encounter::query()->create([
                ...$data,
                'hospital_id' => $request->user()->hospital_id,
                'status' => 'waiting',
            ]);

            $encounter->addClinician($data['clinician_id'] ?? $request->user()->id);

            $code = match ($encounter->type) {
                'opd' => 'OPD-CON',
                'emergency' => 'ER-CON',
                'admission' => 'ADM-DAY',
                default => null,
            };
            if ($code) {
                ChargeLedger::forService($encounter, $code, 'encounter', $encounter->id);
            }

            Audit::record('created', $encounter, ['type' => $encounter->type]);

            return $encounter;
        });

        return response()->json($encounter->load(['patient', 'clinician', 'facility', 'department']), 201);
    }

    public function show(Encounter $encounter)
    {
        $this->authorizeEncounter($encounter, request()->user(), 'read');

        return ClinicalPayload::encounter($encounter);
    }

    public function update(Request $request, Encounter $encounter)
    {
        $this->authorizeEncounter($encounter, $request->user(), 'update');

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(Encounter::STATUSES)],
            'clinician_id' => ['nullable', 'exists:users,id'],
            'facility_id' => ['nullable', TenantRules::inHospital('facilities')],
            'chief_complaint' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if (($data['status'] ?? null) === 'in_progress' && ! $encounter->started_at) {
            $data['started_at'] = now();
        }

        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = now();
        }

        DB::transaction(function () use ($encounter, $data) {
            $previous = $encounter->status;
            $encounter->update($data);
            $encounter->addClinician($data['clinician_id'] ?? null);
            if (isset($data['status']) && $data['status'] !== $previous) {
                Audit::record('status_changed', $encounter, ['from' => $previous, 'to' => $data['status']]);
            }
        });

        return ClinicalPayload::encounter($encounter->refresh());
    }

    public function storeVitals(Request $request, Encounter $encounter)
    {
        $this->authorizeEncounter($encounter, $request->user(), 'update');

        $data = $request->validate([
            'temperature' => ['nullable', 'numeric'],
            'pulse' => ['nullable', 'integer', 'min:0'],
            'respiration' => ['nullable', 'integer', 'min:0'],
            'systolic' => ['nullable', 'integer', 'min:0'],
            'diastolic' => ['nullable', 'integer', 'min:0'],
            'spo2' => ['nullable', 'integer', 'min:0', 'max:100'],
            'weight' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $vital = Vital::query()->create([
            ...$data,
            'hospital_id' => $encounter->hospital_id,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'recorded_by' => $request->user()->id,
            'recorded_at' => now(),
        ]);

        return response()->json($vital->load('recordedBy:id,name'), 201);
    }

    public function storeNote(Request $request, Encounter $encounter)
    {
        $this->authorizeEncounter($encounter, $request->user(), 'update');

        $data = $request->validate([
            'type' => ['nullable', Rule::in(ClinicalNote::TYPES)],
            'body' => ['required', 'string'],
        ]);

        $note = ClinicalNote::query()->create([
            'hospital_id' => $encounter->hospital_id,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'author_id' => $request->user()->id,
            'type' => $data['type'] ?? 'progress',
            'body' => $data['body'],
            'recorded_at' => now(),
        ]);

        return response()->json($note->load('author:id,name'), 201);
    }

    public function storeDiagnosis(Request $request, Encounter $encounter)
    {
        $this->authorizeEncounter($encounter, $request->user(), 'update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40'],
            'kind' => ['nullable', Rule::in(['primary', 'secondary'])],
        ]);

        $diagnosis = Diagnosis::query()->create([
            ...$data,
            'hospital_id' => $encounter->hospital_id,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'kind' => $data['kind'] ?? 'primary',
            'recorded_by' => $request->user()->id,
            'recorded_at' => now(),
        ]);

        return response()->json($diagnosis->load('recordedBy:id,name'), 201);
    }

    public function storeCarePlan(Request $request, Encounter $encounter)
    {
        $this->authorizeEncounter($encounter, $request->user(), 'update');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        $plan = CarePlan::query()->create([
            ...$data,
            'hospital_id' => $encounter->hospital_id,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return response()->json($plan->load('createdBy:id,name'), 201);
    }

    public function admit(Request $request, Encounter $encounter)
    {
        $this->authorizeEncounter($encounter, $request->user(), 'update');

        $data = $request->validate([
            'facility_id' => ['nullable', TenantRules::inHospital('facilities')],
            'clinician_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $admission = DB::transaction(function () use ($encounter, $data) {
            $admission = Encounter::query()->create([
                'hospital_id' => $encounter->hospital_id,
                'patient_id' => $encounter->patient_id,
                'department_id' => Department::query()->where('slug', 'wards')->value('id'),
                'clinician_id' => $data['clinician_id'] ?? $encounter->clinician_id,
                'facility_id' => $data['facility_id'] ?? null,
                'parent_encounter_id' => $encounter->id,
                'type' => 'admission',
                'status' => 'in_progress',
                'chief_complaint' => $encounter->chief_complaint,
                'notes' => $data['notes'] ?? $encounter->notes,
                'started_at' => now(),
                'admitted_at' => now(),
            ]);

            $admission->addClinician($admission->clinician_id);
            $encounter->patient->update(['status' => 'admitted']);
            ChargeLedger::forService($admission, 'ADM-DAY', 'encounter', $admission->id);
            Audit::record('admitted', $admission, ['from_encounter_id' => $encounter->id]);

            return $admission;
        });

        return response()->json(ClinicalPayload::encounter($admission), 201);
    }

    public function discharge(Request $request, Encounter $encounter)
    {
        $this->authorizeEncounter($encounter, $request->user(), 'update');

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($encounter, $data) {
            $encounter->update([
                'status' => 'completed',
                'completed_at' => now(),
                'discharged_at' => now(),
                'notes' => $data['notes'] ?? $encounter->notes,
            ]);

            $active = $encounter->bedAssignments()->where('status', 'active')->lockForUpdate()->get();
            foreach ($active as $assignment) {
                $assignment->update([
                    'status' => 'discharged',
                    'discharged_at' => now(),
                ]);
                $facility = Facility::query()->with('type')->find($assignment->facility_id);
                $facility?->adjustUtilization(-1);
                if ($facility?->type?->slug === 'bed') {
                    $facility->update(['status' => 'cleaning']);
                }
            }

            if (! $encounter->patient->bedAssignments()->where('status', 'active')->exists()) {
                $encounter->patient->update(['status' => 'active']);
            }

            Audit::record('discharged', $encounter);
        });

        return ClinicalPayload::encounter($encounter->refresh());
    }

    public function invoice(Encounter $encounter)
    {
        $this->authorizeEncounter($encounter, request()->user(), 'read');

        $invoice = ChargeLedger::openInvoice($encounter);

        return $invoice->load(['patient', 'items.service', 'payments']);
    }

    private function authorizeEncounter(Encounter $encounter, $user, string $action): void
    {
        $subject = $this->subjectForType($encounter->type);
        abort_unless(
            $user->hasPermission($action, $subject)
            || $user->hasPermission('update', 'Opd')
            || $user->hasPermission('update', 'Emergency')
            || $user->hasPermission('update', 'Ward')
            || $user->hasPermission('read', 'Patient'),
            403,
            'This action is unauthorized.'
        );
    }

    private function subjectForType(string $type): string
    {
        return match ($type) {
            'opd', 'follow_up' => 'Opd',
            'emergency' => 'Emergency',
            'admission' => 'Ward',
            'procedure' => 'Theatre',
            'referral' => 'Referral',
            default => 'Reception',
        };
    }

    private function canListEncounters($user): bool
    {
        foreach ([
            'Patient', 'Opd', 'Emergency', 'Ward', 'Reception', 'Laboratory',
            'Imaging', 'Pharmacy', 'Theatre', 'Invoice', 'Referral', 'Ambulance', 'AssistanceRequest',
        ] as $subject) {
            if ($user->hasPermission('read', $subject) || $user->hasPermission('create', $subject) || $user->hasPermission('update', $subject)) {
                return true;
            }
        }

        return false;
    }

    private function moduleForType(string $type): string
    {
        return match ($type) {
            'opd', 'follow_up' => 'opd',
            'emergency' => 'emergency',
            'admission' => 'wards',
            'procedure' => 'theatre',
            default => 'reception',
        };
    }
}
