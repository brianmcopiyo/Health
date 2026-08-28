<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FacilityController extends Controller
{
    public function types()
    {
        return FacilityType::query()->orderBy('name')->get();
    }

    public function index(Request $request)
    {
        $query = Facility::query()->with(['type', 'parent', 'hospital', 'department'])->orderBy('name');

        if ($typeId = $request->integer('facility_type_id')) {
            $query->where('facility_type_id', $typeId);
        }

        if ($type = $request->string('type')->toString()) {
            $query->whereHas('type', fn ($builder) => $builder->where('slug', $type));
        }

        if ($departmentId = $request->integer('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(fn (Facility $facility) => $this->serialize($facility));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $facility = Facility::query()->create($data);

        return response()->json($this->serialize($facility->load(['type', 'parent', 'hospital', 'department'])), 201);
    }

    public function show(Facility $facility)
    {
        return $this->serialize($facility->load(['type', 'parent', 'hospital', 'department', 'children.type']));
    }

    public function update(Request $request, Facility $facility)
    {
        $data = $this->validated($request, $facility);

        $facility->update($data);

        return $this->serialize($facility->refresh()->load(['type', 'parent', 'hospital', 'department']));
    }

    public function updateStatus(Request $request, Facility $facility)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Facility::STATUSES)],
            'current_utilization' => ['nullable', 'integer', 'min:0'],
            'resource_notes' => ['nullable', 'string'],
        ]);

        if (isset($data['current_utilization']) && $data['current_utilization'] > $facility->capacity) {
            return response()->json(['message' => 'Utilization cannot exceed capacity.'], 422);
        }

        $facility->update($data);

        return $this->serialize($facility->refresh()->load(['type', 'parent', 'hospital', 'department']));
    }

    public function destroy(Facility $facility)
    {
        abort_if($facility->children()->exists(), 422, 'Remove child facilities first.');

        $facility->delete();

        return response()->json(['message' => 'Facility removed']);
    }

    private function validated(Request $request, ?Facility $facility = null): array
    {
        $hospitalId = $request->user()->isPlatformAdmin()
            ? ($request->integer('hospital_id') ?: $facility?->hospital_id)
            : $request->user()->hospital_id;

        $data = $request->validate([
            'hospital_id' => [$request->user()->isPlatformAdmin() ? 'nullable' : 'prohibited', 'exists:hospitals,id'],
            'facility_type_id' => [$facility ? 'sometimes' : 'required', 'exists:facility_types,id'],
            'parent_id' => ['nullable', 'exists:facilities,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'name' => [$facility ? 'sometimes' : 'required', 'string', 'max:255'],
            'code' => [
                $facility ? 'sometimes' : 'required',
                'string',
                'max:50',
                Rule::unique('facilities', 'code')->where(fn ($query) => $query->where('hospital_id', $hospitalId))->ignore($facility?->id),
            ],
            'status' => ['sometimes', Rule::in(Facility::STATUSES)],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'current_utilization' => ['sometimes', 'integer', 'min:0'],
            'resource_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! $request->user()->isPlatformAdmin()) {
            $data['hospital_id'] = $hospitalId;
        } elseif (empty($data['hospital_id'])) {
            $data['hospital_id'] = $hospitalId;
        }

        if (isset($data['current_utilization'], $data['capacity']) && $data['current_utilization'] > $data['capacity']) {
            abort(422, 'Utilization cannot exceed capacity.');
        }

        if (isset($data['current_utilization']) && $facility && $data['current_utilization'] > ($data['capacity'] ?? $facility->capacity)) {
            abort(422, 'Utilization cannot exceed capacity.');
        }

        return $data;
    }

    private function serialize(Facility $facility): array
    {
        return [
            'id' => $facility->id,
            'hospital_id' => $facility->hospital_id,
            'hospital' => $facility->hospital,
            'facility_type_id' => $facility->facility_type_id,
            'type' => $facility->type,
            'parent_id' => $facility->parent_id,
            'parent' => $facility->parent,
            'department_id' => $facility->department_id,
            'department' => $facility->department,
            'children' => $facility->relationLoaded('children') ? $facility->children : [],
            'name' => $facility->name,
            'code' => $facility->code,
            'status' => $facility->status,
            'capacity' => $facility->capacity,
            'current_utilization' => $facility->current_utilization,
            'remaining_capacity' => $facility->remainingCapacity(),
            'resource_notes' => $facility->resource_notes,
            'notes' => $facility->notes,
            'updated_at' => $facility->updated_at,
        ];
    }
}
