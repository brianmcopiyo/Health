<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::query()->latest();

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

        return $query->get()->map(fn (Patient $patient) => $this->serialize($patient));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['hospital_id'] = $request->user()->hospital_id;
        $data['mrn'] = $data['mrn'] ?? $this->nextMrn($request->user()->hospital);

        $patient = Patient::query()->create($data);

        return response()->json($this->serialize($patient), 201);
    }

    public function show(Patient $patient)
    {
        return $this->serialize($patient->load(['encounters.clinician', 'bedAssignments.facility', 'invoices']));
    }

    public function update(Request $request, Patient $patient)
    {
        $patient->update($this->validated($request, $patient));

        return $this->serialize($patient->refresh());
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
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(Patient::STATUSES)],
        ]);
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
            'address' => $patient->address,
            'status' => $patient->status,
            'encounters' => $patient->relationLoaded('encounters') ? $patient->encounters : [],
            'bed_assignments' => $patient->relationLoaded('bedAssignments') ? $patient->bedAssignments : [],
            'invoices' => $patient->relationLoaded('invoices') ? $patient->invoices : [],
        ];
    }
}
