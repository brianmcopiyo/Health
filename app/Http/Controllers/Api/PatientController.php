<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientCondition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::query()
            ->with(['allergies', 'encounters' => fn ($encounters) => $encounters->latest()->limit(3)])
            ->latest();

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder->where('mrn', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->boolean('under_care')) {
            $query->whereHas('encounters', function ($encounters) use ($request) {
                $encounters->whereIn('status', ['waiting', 'in_progress'])
                    ->where(function ($builder) use ($request) {
                        $builder->where('clinician_id', $request->user()->id)
                            ->orWhereHas('careTeam', fn ($team) => $team->where('user_id', $request->user()->id));
                    });
            });
        }

        return $query->get()->map(fn (Patient $patient) => $this->serialize($patient));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['hospital_id'] = $request->user()->hospital_id;
        $data['mrn'] = $data['mrn'] ?? $this->nextMrn($request->user()->hospital);

        $allergies = $data['allergies'] ?? [];
        $conditions = $data['conditions'] ?? [];
        unset($data['allergies'], $data['conditions']);

        $patient = Patient::query()->create($data);
        $this->syncHistory($patient, $allergies, $conditions, $request->user()->id);

        return response()->json($this->serialize($patient->load(['allergies', 'conditions'])), 201);
    }

    public function show(Patient $patient)
    {
        $patient->load([
            'allergies.notedBy',
            'conditions.recordedBy',
            'encounters.clinician',
            'encounters.department',
            'bedAssignments.facility',
            'invoices.items',
            'prescriptions.items.medication',
            'orders',
        ]);

        $payload = $this->serialize($patient);
        $payload['timeline'] = $patient->timeline();
        $payload['active_bed'] = $patient->activeBed()?->load('facility');

        return $payload;
    }

    public function update(Request $request, Patient $patient)
    {
        $data = $this->validated($request, $patient);
        $allergies = $data['allergies'] ?? null;
        $conditions = $data['conditions'] ?? null;
        unset($data['allergies'], $data['conditions']);

        $patient->update($data);

        if (is_array($allergies) || is_array($conditions)) {
            $this->syncHistory($patient, $allergies ?? [], $conditions ?? [], $request->user()->id);
        }

        return $this->serialize($patient->refresh()->load(['allergies', 'conditions']));
    }

    private function validated(Request $request, ?Patient $patient = null): array
    {
        $hospitalId = $request->user()->hospital_id;

        return $request->validate([
            'mrn' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('patients', 'mrn')->where(fn ($query) => $query->where('hospital_id', $hospitalId))->ignore($patient?->id),
            ],
            'first_name' => [$patient ? 'sometimes' : 'required', 'string', 'max:120'],
            'last_name' => [$patient ? 'sometimes' : 'required', 'string', 'max:120'],
            'sex' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'national_id' => ['nullable', 'string', 'max:80'],
            'blood_group' => ['nullable', 'string', 'max:8'],
            'marital_status' => ['nullable', 'string', 'max:40'],
            'occupation' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:40'],
            'next_of_kin_name' => ['nullable', 'string', 'max:120'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:50'],
            'next_of_kin_relation' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(Patient::STATUSES)],
            'notes' => ['nullable', 'string'],
            'allergies' => ['array'],
            'allergies.*.allergen' => ['required', 'string', 'max:120'],
            'allergies.*.reaction' => ['nullable', 'string', 'max:120'],
            'allergies.*.severity' => ['nullable', 'string', 'max:40'],
            'conditions' => ['array'],
            'conditions.*.name' => ['required', 'string', 'max:120'],
            'conditions.*.status' => ['nullable', 'string', 'max:40'],
            'conditions.*.diagnosed_on' => ['nullable', 'date'],
            'conditions.*.notes' => ['nullable', 'string'],
        ]);
    }

    private function syncHistory(Patient $patient, array $allergies, array $conditions, int $userId): void
    {
        if ($allergies) {
            $patient->allergies()->delete();
            foreach ($allergies as $allergy) {
                PatientAllergy::query()->create([
                    'hospital_id' => $patient->hospital_id,
                    'patient_id' => $patient->id,
                    'allergen' => $allergy['allergen'],
                    'reaction' => $allergy['reaction'] ?? null,
                    'severity' => $allergy['severity'] ?? 'moderate',
                    'noted_by' => $userId,
                    'noted_at' => now(),
                ]);
            }
        }

        if ($conditions) {
            $patient->conditions()->delete();
            foreach ($conditions as $condition) {
                PatientCondition::query()->create([
                    'hospital_id' => $patient->hospital_id,
                    'patient_id' => $patient->id,
                    'name' => $condition['name'],
                    'status' => $condition['status'] ?? 'active',
                    'diagnosed_on' => $condition['diagnosed_on'] ?? null,
                    'notes' => $condition['notes'] ?? null,
                    'recorded_by' => $userId,
                ]);
            }
        }
    }

    private function nextMrn(?Hospital $hospital): string
    {
        $code = $hospital?->code ?: 'HMS';
        $count = Patient::query()->count() + 1;

        return sprintf('%s-%04d', $code, $count);
    }

    private function serialize(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'mrn' => $patient->mrn,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'full_name' => $patient->fullName(),
            'sex' => $patient->sex,
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'phone' => $patient->phone,
            'email' => $patient->email,
            'national_id' => $patient->national_id,
            'blood_group' => $patient->blood_group,
            'marital_status' => $patient->marital_status,
            'occupation' => $patient->occupation,
            'address' => $patient->address,
            'emergency_contact_name' => $patient->emergency_contact_name,
            'emergency_contact_phone' => $patient->emergency_contact_phone,
            'emergency_contact_relation' => $patient->emergency_contact_relation,
            'next_of_kin_name' => $patient->next_of_kin_name,
            'next_of_kin_phone' => $patient->next_of_kin_phone,
            'next_of_kin_relation' => $patient->next_of_kin_relation,
            'status' => $patient->status,
            'notes' => $patient->notes,
            'allergies' => $patient->relationLoaded('allergies') ? $patient->allergies : [],
            'conditions' => $patient->relationLoaded('conditions') ? $patient->conditions : [],
            'encounters' => $patient->relationLoaded('encounters') ? $patient->encounters : [],
            'bed_assignments' => $patient->relationLoaded('bedAssignments') ? $patient->bedAssignments : [],
            'invoices' => $patient->relationLoaded('invoices') ? $patient->invoices : [],
            'prescriptions' => $patient->relationLoaded('prescriptions') ? $patient->prescriptions : [],
            'orders' => $patient->relationLoaded('orders') ? $patient->orders : [],
        ];
    }
}
