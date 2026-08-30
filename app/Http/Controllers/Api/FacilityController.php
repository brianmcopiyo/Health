<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\BedAssignment;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Support\FacilityOccupancy;
use App\Support\QueryList;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FacilityController extends Controller
{
    public function types()
    {
        return Cache::remember('hms.facility_types', 86400, fn () => FacilityType::query()->orderBy('name')->get());
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $type = $request->string('type')->toString();
        $allowed = $user->hasPermission('read', 'Facility')
            || ($type === 'ward' && $user->hasPermission('read', 'Ward'))
            || ($type === 'bed' && ($user->hasPermission('read', 'Bed') || $user->hasPermission('read', 'Ward')));

        abort_unless($allowed, 403, 'This action is unauthorized.');

        $query = Facility::query()->with(['type:id,name,slug,icon', 'parent:id,name', 'hospital:id,name', 'department:id,name'])->orderBy('name');

        if ($typeId = $request->input('facility_type_id')) {
            $query->where('facility_type_id', $typeId);
        }

        if ($type = $request->string('type')->toString()) {
            $typeId = Cache::remember('facility_type:'.$type, 86400, fn () => FacilityType::query()->where('slug', $type)->value('id'));
            $query->where('facility_type_id', $typeId ?: '00000000-0000-0000-0000-000000000000');
        }

        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $query->search($request->string('q')->toString());

        $paginator = QueryList::paginate($query, $request, $request->boolean('compact') ? 50 : 25);
        $paginator->getCollection()->transform(fn (Facility $facility) => $this->serialize($facility));

        return $paginator;
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $slug = FacilityType::query()->whereKey($request->input('facility_type_id'))->value('slug');

        if ($slug) {
            $this->authorizeCreate($user, $slug);
        } else {
            abort_unless(
                $user->hasPermission('create', 'Facility')
                || $user->hasPermission('create', 'Ward')
                || $user->hasPermission('create', 'Bed'),
                403,
                'This action is unauthorized.'
            );
        }

        $data = $this->validated($request);

        $facility = DB::transaction(function () use ($data) {
            $facility = Facility::query()->create($data);
            $facility->load(['type', 'parent.type']);
            FacilityOccupancy::syncForBed($facility);

            return $facility;
        });

        return response()->json($this->serialize($facility->load(['type', 'parent', 'hospital', 'department'])), 201);
    }

    public function show(Request $request, Facility $facility)
    {
        $this->authorizeView($request, $facility);

        $facility->load([
            'type',
            'parent.type',
            'hospital',
            'department',
            'children.type',
            'children.activeAssignment.patient:id,mrn,first_name,last_name,status',
            'children.activeAssignment.encounter:id,type,status',
            'activeAssignment.patient:id,mrn,first_name,last_name,status',
            'activeAssignment.encounter:id,type,status',
            'activeAssignment.nurse:id,name',
            'staffAssignments.user:id,name,job_title,email',
            'staffAssignments.department:id,name',
        ]);

        $payload = $this->serialize($facility);
        $payload['history'] = $this->history($facility);
        $payload['activity'] = $this->activity($facility);

        return $payload;
    }

    public function update(Request $request, Facility $facility)
    {
        $data = $this->validated($request, $facility);
        $previousParentId = $facility->parent_id;

        $facility = DB::transaction(function () use ($facility, $data, $previousParentId) {
            $facility->update($data);
            $facility->load(['type', 'parent.type']);
            FacilityOccupancy::syncForBed($facility->fresh(['type', 'parent.type']), $previousParentId);

            return $facility->fresh();
        });

        return $this->serialize($facility->load(['type', 'parent', 'hospital', 'department']));
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
        $facility->load(['type', 'parent.type']);
        FacilityOccupancy::syncForBed($facility);

        return $this->serialize($facility->refresh()->load(['type', 'parent', 'hospital', 'department']));
    }

    public function destroy(Facility $facility)
    {
        abort_if($facility->children()->exists(), 422, 'Remove child facilities first.');
        abort_if(BedAssignment::query()->where('facility_id', $facility->id)->where('status', 'active')->exists(), 422, 'Discharge the assigned patient before removing this facility.');
        abort_if(Encounter::query()->where('facility_id', $facility->id)->whereIn('status', ['waiting', 'in_progress'])->exists(), 422, 'This facility is linked to open encounters and cannot be deleted.');

        $parentId = $facility->parent_id;
        $facility->delete();
        FacilityOccupancy::syncWard(Facility::query()->find($parentId));

        return response()->json(['message' => 'Facility removed']);
    }

    private function authorizeCreate($user, ?string $slug): void
    {
        $allowed = $user->hasPermission('create', 'Facility')
            || ($slug === 'ward' && $user->hasPermission('create', 'Ward'))
            || ($slug === 'bed' && $user->hasPermission('create', 'Bed'));

        abort_unless($allowed, 403, 'This action is unauthorized.');
    }

    private function authorizeView(Request $request, Facility $facility): void
    {
        $user = $request->user();
        $facility->loadMissing('type');
        $allowed = $user->hasPermission('read', 'Facility')
            || ($facility->type?->slug === 'ward' && $user->hasPermission('read', 'Ward'))
            || ($facility->type?->slug === 'bed' && ($user->hasPermission('read', 'Bed') || $user->hasPermission('read', 'Ward')));

        abort_unless($allowed, 403, 'This action is unauthorized.');
    }

    private function validated(Request $request, ?Facility $facility = null): array
    {
        $hospitalId = $request->user()->isPlatformAdmin()
            ? ($request->input('hospital_id') ?: $facility?->hospital_id)
            : $request->user()->hospital_id;

        $data = $request->validate([
            'hospital_id' => [$request->user()->isPlatformAdmin() ? 'nullable' : 'prohibited', 'exists:hospitals,id'],
            'facility_type_id' => [$facility ? 'sometimes' : 'required', 'exists:facility_types,id'],
            'parent_id' => ['nullable', TenantRules::inHospital('facilities')],
            'department_id' => ['nullable', TenantRules::inHospital('departments')],
            'name' => [$facility ? 'sometimes' : 'required', 'string', 'max:255'],
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

        $facility?->loadMissing('type');

        if ($facility?->type?->slug === 'ward' || ($data['facility_type_id'] ?? $facility?->facility_type_id)) {
            $typeId = $data['facility_type_id'] ?? $facility?->facility_type_id;
            $slug = $facility?->type?->slug ?: FacilityType::query()->whereKey($typeId)->value('slug');
            if ($slug === 'ward' && $facility?->beds()->exists()) {
                unset($data['capacity'], $data['current_utilization']);
            }
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
            'beds' => $beds = $facility->relationLoaded('children')
                ? $facility->children->filter(fn (Facility $child) => $child->type?->slug === 'bed')->values()
                : collect(),
            'units' => $facility->relationLoaded('children')
                ? $facility->children->reject(fn (Facility $child) => $child->type?->slug === 'bed')->values()
                : [],
            'occupants' => collect($beds)->filter(fn (Facility $bed) => $bed->activeAssignment)->map(function (Facility $bed) {
                $assignment = $bed->activeAssignment->toArray();
                $assignment['facility'] = ['id' => $bed->id, 'name' => $bed->name];
                $assignment['facility_id'] = $bed->id;

                return $assignment;
            })->values(),
            'assignment' => $facility->relationLoaded('activeAssignment') ? $facility->activeAssignment : null,
            'staff_assignments' => $facility->relationLoaded('staffAssignments') ? $facility->staffAssignments : [],
            'name' => $facility->name,
            'status' => $facility->status,
            'capacity' => $facility->capacity,
            'current_utilization' => $facility->current_utilization,
            'remaining_capacity' => $facility->remainingCapacity(),
            'resource_notes' => $facility->resource_notes,
            'notes' => $facility->notes,
            'updated_at' => $facility->updated_at,
        ];
    }

    private function history(Facility $facility)
    {
        $query = BedAssignment::query()
            ->with([
                'patient:id,mrn,first_name,last_name,status',
                'facility:id,name',
                'ward:id,name',
                'nurse:id,name',
                'encounter:id,type,status',
            ])
            ->latest()
            ->limit(30);

        if ($facility->type?->slug === 'ward') {
            $query->where('ward_id', $facility->id);
        } else {
            $query->where('facility_id', $facility->id);
        }

        return $query->get();
    }

    private function activity(Facility $facility)
    {
        return AuditEvent::query()
            ->with('actor:id,name')
            ->where('auditable_type', $facility->getMorphClass())
            ->where('auditable_id', $facility->id)
            ->latest('created_at')
            ->limit(20)
            ->get();
    }
}
