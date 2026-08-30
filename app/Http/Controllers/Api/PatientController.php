<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientCondition;
use App\Support\Access;
use App\Support\Audit;
use App\Support\FieldCrypt;
use App\Support\HospitalSequence;
use App\Support\QueryList;
use App\Support\Redactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $compact = $request->boolean('compact');
        $query = Access::patientQuery($request->user(), Patient::query()->latest(), $compact);

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
        $paginator->getCollection()->transform(
            fn (Patient $patient) => Redactor::patient($this->serialize($patient, true), $request->user(), true)
        );

        return $paginator;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        unset($data['hospital_id']);
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

        return response()->json(
            Redactor::patient($this->serialize($patient->load(['allergies', 'conditions'])), $request->user()),
            201
        );
    }

    public function show(Request $request, Patient $patient)
    {
        abort_unless(Access::canViewPatient($request->user(), $patient), 403, 'This action is unauthorized.');

        $patient->load([
            'allergies.notedBy:id,name',
            'conditions.recordedBy:id,name',
            'encounters' => fn ($encounters) => $encounters->with(['clinician:id,name', 'department:id,name'])->latest()->limit(50),
            'bedAssignments' => fn ($assignments) => $assignments->with(['facility:id,name,status,parent_id', 'facility.parent:id,name', 'ward:id,name', 'nurse:id,name'])->latest()->limit(20),
            'invoices' => fn ($invoices) => $invoices->with('items')->latest()->limit(20),
            'prescriptions' => fn ($prescriptions) => $prescriptions->with('items.medication')->latest()->limit(20),
            'orders' => fn ($orders) => $orders->latest()->limit(30),
            'referrals' => fn ($referrals) => $referrals->with(['fromHospital:id,name', 'toHospital:id,name'])->latest()->limit(20),
        ]);

        $payload = $this->serialize($patient);
        $payload['timeline'] = $patient->timeline();
        $payload['active_bed'] = $patient->activeBed()?->load(['facility:id,name,status,parent_id', 'facility.parent:id,name', 'ward:id,name', 'nurse:id,name']);
        Audit::viewed($patient, ['mrn' => $patient->mrn]);

        return Redactor::patient($payload, $request->user());
    }

    public function update(Request $request, Patient $patient)
    {
        abort_unless(Access::canUpdatePatient($request->user(), $patient), 403, 'This action is unauthorized.');

        $data = $this->validated($request, $patient);
        unset($data['hospital_id']);
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

        return Redactor::patient($this->serialize($patient->refresh()->load(['allergies', 'conditions'])), $request->user());
    }

    public function archive(Request $request, Patient $patient)
    {
        abort_unless(Access::canUpdatePatient($request->user(), $patient), 403, 'This action is unauthorized.');
        abort_if($patient->bedAssignments()->where('status', 'active')->exists(), 422, 'Discharge the patient from their bed first.');

        $patient->update([
            'archived_at' => now(),
            'status' => $patient->status === 'admitted' ? 'discharged' : $patient->status,
        ]);
        Audit::record('archived', $patient);

        return Redactor::patient($this->serialize($patient->refresh()), $request->user());
    }

    public function export(Request $request, Patient $patient)
    {
        abort_unless(Access::canExportPatient($request->user(), $patient), 403, 'This action is unauthorized.');

        $patient->load(['allergies', 'conditions', 'encounters', 'orders', 'prescriptions.items', 'invoices.items']);
        $payload = $this->serialize($patient);
        $payload['timeline'] = $patient->timeline();
        Audit::exported($patient, ['mrn' => $patient->mrn, 'format' => 'json']);

        return response()->json(Redactor::patient($payload, $request->user()))
            ->header('Content-Disposition', 'attachment; filename="'.$patient->mrn.'.json"')
            ->header('Cache-Control', 'no-store, private');
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
                function (string $attribute, mixed $value, \Closure $fail) use ($hospitalId, $patient) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $index = FieldCrypt::blindIndex(FieldCrypt::normalizeNationalId((string) $value));
                    $exists = Patient::query()
                        ->where('hospital_id', $hospitalId)
                        ->where('national_id_index', $index)
                        ->when($patient, fn ($query) => $query->where('id', '!=', $patient->id))
                        ->exists();
                    if ($exists) {
                        $fail('The national id has already been taken.');
                    }
                },
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

    private function syncHistory(Patient $patient, array $allergies, array $conditions, string $userId): void
    {
        if ($allergies) {
            $incoming = collect($allergies)->map(fn ($row) => mb_strtolower(trim($row['allergen'])));
            $current = $patient->allergies()->where('is_current', true)->get();
            $current->each(function (PatientAllergy $row) use ($incoming) {
                if (! $incoming->contains(mb_strtolower(trim((string) $row->allergen)))) {
                    $row->update(['is_current' => false]);
                }
            });

            foreach ($allergies as $allergy) {
                $needle = mb_strtolower(trim($allergy['allergen']));
                $match = $current->first(fn (PatientAllergy $row) => mb_strtolower(trim((string) $row->allergen)) === $needle);

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
                    $created = PatientAllergy::query()->create($payload);
                    $current->push($created);
                }
            }
        }

        if ($conditions) {
            $incoming = collect($conditions)->map(fn ($row) => mb_strtolower(trim($row['name'])));
            $rows = $patient->conditions()->whereIn('status', ['active', 'resolved'])->get();
            $rows->each(function (PatientCondition $row) use ($incoming) {
                if (! $incoming->contains(mb_strtolower(trim((string) $row->name)))) {
                    $row->update(['status' => 'resolved']);
                }
            });

            foreach ($conditions as $condition) {
                $needle = mb_strtolower(trim($condition['name']));
                $match = $rows->first(fn (PatientCondition $row) => mb_strtolower(trim((string) $row->name)) === $needle);

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
                    $created = PatientCondition::query()->create($payload);
                    $rows->push($created);
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
            'archived_at' => $patient->archived_at,
            'allergies' => collect($allergies)->where('is_current', true)->values(),
            'conditions' => collect($conditions)->where('status', 'active')->values(),
            'encounters' => $patient->relationLoaded('encounters') ? $patient->encounters : [],
            'bed_assignments' => $patient->relationLoaded('bedAssignments') ? $patient->bedAssignments : [],
            'invoices' => $patient->relationLoaded('invoices') ? $patient->invoices : [],
            'prescriptions' => $patient->relationLoaded('prescriptions') ? $patient->prescriptions : [],
            'orders' => $patient->relationLoaded('orders') ? $patient->orders : [],
            'referrals' => $patient->relationLoaded('referrals') ? $patient->referrals : [],
        ];
    }
}
