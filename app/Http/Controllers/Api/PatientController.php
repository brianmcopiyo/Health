<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientCondition;
use App\Support\Audit;
use App\Support\HospitalSequence;
use App\Support\QueryList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $compact = $request->boolean('compact');
        $query = Patient::query()->latest();

        if ($compact) {
            $query->select([
                'id', 'hospital_id', 'mrn', 'first_name', 'last_name', 'sex', 'phone', 'status', 'date_of_birth',
            ]);
        }

        $query->search($request->string('q')->toString());

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->boolean('under_care')) {
            $userId = $request->user()->id;
            $query->whereIn('id', function ($builder) use ($userId, $request) {
                $builder->select('patient_id')
                    ->from('encounters')
                    ->where('hospital_id', $request->user()->hospital_id)
                    ->whereIn('status', ['waiting', 'in_progress'])
                    ->where(function ($inner) use ($userId) {
                        $inner->where('clinician_id', $userId)
                            ->orWhereIn('id', function ($team) use ($userId) {
                                $team->select('encounter_id')
                                    ->from('encounter_clinicians')
                                    ->where('user_id', $userId);
                            });
                    });
            });
        }

        $paginator = QueryList::paginate($query, $request, $compact ? 50 : 25);
        $paginator->getCollection()->transform(fn (Patient $patient) => $this->serialize($patient, true));

        return $paginator;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['hospital_id'] = $request->user()->hospital_id;
        $data['mrn'] = $data['mrn'] ?? HospitalSequence::nextMrn($request->user()->hospital);

        $allergies = $data['allergies'] ?? [];
        $conditions = $data['conditions'] ?? [];
        unset($data['allergies'], $data['conditions']);

        $patient = DB::transaction(function () use ($data, $allergies, $conditions, $request) {
            $patient = Patient::query()->create($data);
            $this->syncHistory($patient, $allergies, $conditions, $request->user()->id);
            Audit::record('created', $patient, ['mrn' => $patient->mrn]);

            return $patient;
        });

        return response()->json($this->serialize($patient->load(['allergies', 'conditions'])), 201);
    }

    public function show(Patient $patient)
    {
        $patient->load([
            'allergies.notedBy:id,name',
            'conditions.recordedBy:id,name',
            'encounters' => fn ($encounters) => $encounters->with(['clinician:id,name', 'department:id,name'])->latest()->limit(50),
            'bedAssignments' => fn ($assignments) => $assignments->with('facility:id,name,code,status')->latest()->limit(20),
            'invoices' => fn ($invoices) => $invoices->with('items')->latest()->limit(20),
            'prescriptions' => fn ($prescriptions) => $prescriptions->with('items.medication')->latest()->limit(20),
            'orders' => fn ($orders) => $orders->latest()->limit(30),
        ]);

        $payload = $this->serialize($patient);
        $payload['timeline'] = $patient->timeline();
        $payload['active_bed'] = $patient->activeBed()?->load('facility:id,name,code,status');

        return $payload;
    }

    public function update(Request $request, Patient $patient)
    {
        $data = $this->validated($request, $patient);
        $allergies = $data['allergies'] ?? null;
        $conditions = $data['conditions'] ?? null;
        unset($data['allergies'], $data['conditions']);

        DB::transaction(function () use ($patient, $data, $allergies, $conditions, $request) {
            $patient->update($data);

            if (is_array($allergies) || is_array($conditions)) {
                $this->syncHistory($patient, $allergies ?? [], $conditions ?? [], $request->user()->id);
            }

            Audit::record('updated', $patient, array_keys($data));
        });

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
            'national_id' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('patients', 'national_id')->where(fn ($query) => $query->where('hospital_id', $hospitalId))->ignore($patient?->id),
            ],
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
            $incoming = collect($allergies)->map(fn ($row) => mb_strtolower(trim($row['allergen'])));
            $patient->allergies()->where('is_current', true)->get()->each(function (PatientAllergy $row) use ($incoming) {
                if (! $incoming->contains(mb_strtolower(trim($row->allergen)))) {
                    $row->update(['is_current' => false]);
                }
            });

            foreach ($allergies as $allergy) {
                $match = $patient->allergies()
                    ->where('is_current', true)
                    ->whereRaw('lower(allergen) = ?', [mb_strtolower(trim($allergy['allergen']))])
                    ->first();

                $payload = [
                    'hospital_id' => $patient->hospital_id,
                    'patient_id' => $patient->id,
                    'allergen' => $allergy['allergen'],
                    'reaction' => $allergy['reaction'] ?? $match?->reaction,
                    'severity' => $allergy['severity'] ?? $match?->severity ?? 'moderate',
                    'is_current' => true,
                    'noted_by' => $userId,
                    'noted_at' => $match?->noted_at ?? now(),
                ];

                if ($match) {
                    $match->update($payload);
                } else {
                    PatientAllergy::query()->create($payload);
                }
            }
        }

        if ($conditions) {
            $incoming = collect($conditions)->map(fn ($row) => mb_strtolower(trim($row['name'])));
            $patient->conditions()->where('status', 'active')->get()->each(function (PatientCondition $row) use ($incoming) {
                if (! $incoming->contains(mb_strtolower(trim($row->name)))) {
                    $row->update(['status' => 'resolved']);
                }
            });

            foreach ($conditions as $condition) {
                $match = $patient->conditions()
                    ->whereIn('status', ['active', 'resolved'])
                    ->whereRaw('lower(name) = ?', [mb_strtolower(trim($condition['name']))])
                    ->first();

                $payload = [
                    'hospital_id' => $patient->hospital_id,
                    'patient_id' => $patient->id,
                    'name' => $condition['name'],
                    'status' => $condition['status'] ?? 'active',
                    'diagnosed_on' => $condition['diagnosed_on'] ?? $match?->diagnosed_on,
                    'notes' => $condition['notes'] ?? $match?->notes,
                    'recorded_by' => $userId,
                ];

                if ($match) {
                    $match->update($payload);
                } else {
                    PatientCondition::query()->create($payload);
                }
            }
        }
    }

    private function serialize(Patient $patient, bool $list = false): array
    {
        $allergies = $patient->relationLoaded('allergies') ? $patient->allergies : collect();
        $conditions = $patient->relationLoaded('conditions') ? $patient->conditions : collect();

        if ($list) {
            $allergies = $allergies->where('is_current', true)->values();
            $conditions = $conditions->where('status', 'active')->values();
        }

        $payload = [
            'id' => $patient->id,
            'mrn' => $patient->mrn,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'full_name' => $patient->fullName(),
            'sex' => $patient->sex,
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'phone' => $patient->phone,
            'status' => $patient->status,
        ];

        if ($list) {
            return $payload;
        }

        return [
            ...$payload,
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
            'notes' => $patient->notes,
            'allergies' => collect($allergies)->where('is_current', true)->values(),
            'conditions' => collect($conditions)->where('status', 'active')->values(),
            'encounters' => $patient->relationLoaded('encounters') ? $patient->encounters : [],
            'bed_assignments' => $patient->relationLoaded('bedAssignments') ? $patient->bedAssignments : [],
            'invoices' => $patient->relationLoaded('invoices') ? $patient->invoices : [],
            'prescriptions' => $patient->relationLoaded('prescriptions') ? $patient->prescriptions : [],
            'orders' => $patient->relationLoaded('orders') ? $patient->orders : [],
        ];
    }
}
