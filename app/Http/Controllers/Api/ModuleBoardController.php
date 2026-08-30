<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BedAssignment;
use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Prescription;
use App\Models\ServiceOrder;
use App\Support\ModuleCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ModuleBoardController extends Controller
{
    public function catalog()
    {
        return ModuleCatalog::all();
    }

    public function workspaces()
    {
        return ModuleCatalog::workspaces();
    }

    public function show(Request $request, string $module)
    {
        $catalog = ModuleCatalog::find($module);
        abort_unless($catalog, 404);
        $this->authorizePermission($request->user(), 'read', $catalog['subject']);

        $typeId = $this->facilityTypeId($catalog['facility_type'] ?? null);
        $facilityQuery = Facility::query()->with(['type:id,name,slug,icon', 'parent:id,name', 'department:id,name', 'hospital:id,name']);

        if ($typeId) {
            $facilityQuery->where('facility_type_id', $typeId);
        }

        $statsRow = Facility::query()
            ->when($typeId, fn ($query) => $query->where('facility_type_id', $typeId))
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                SUM(CASE WHEN status = 'unavailable' THEN 1 ELSE 0 END) as unavailable,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(capacity) as capacity,
                SUM(current_utilization) as utilization,
                SUM(CASE WHEN capacity > current_utilization THEN capacity - current_utilization ELSE 0 END) as remaining
            ")
            ->first();

        $facilities = $facilityQuery->orderBy('name')->limit(200)->get();

        $payload = [
            'module' => $catalog,
            'stats' => [
                'total' => (int) ($statsRow->total ?? 0),
                'available' => (int) ($statsRow->available ?? 0),
                'occupied' => (int) ($statsRow->occupied ?? 0),
                'maintenance' => (int) ($statsRow->maintenance ?? 0),
                'unavailable' => (int) ($statsRow->unavailable ?? 0),
                'reserved' => (int) ($statsRow->reserved ?? 0),
                'capacity' => (int) ($statsRow->capacity ?? 0),
                'utilization' => (int) ($statsRow->utilization ?? 0),
                'remaining' => (int) ($statsRow->remaining ?? 0),
            ],
            'facilities' => $facilities->map(fn (Facility $facility) => [
                'id' => $facility->id,
                'name' => $facility->name,
                'status' => $facility->status,
                'capacity' => $facility->capacity,
                'current_utilization' => $facility->current_utilization,
                'remaining_capacity' => $facility->remainingCapacity(),
                'resource_notes' => $facility->resource_notes,
                'department' => $facility->department,
                'parent' => $facility->parent,
                'type' => $facility->type,
                'updated_at' => $facility->updated_at,
            ]),
            'departments' => Department::query()
                ->where('module_key', $catalog['key'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'hospital_id', 'name', 'slug', 'module_key', 'is_active']),
        ];

        if (! empty($catalog['orders'])) {
            $payload['orders'] = ServiceOrder::query()
                ->with(['patient:id,mrn,first_name,last_name,status', 'facility:id,name', 'orderedBy:id,name', 'encounter:id,type,status'])
                ->where('module_key', $catalog['key'])
                ->latest()
                ->limit(50)
                ->get();
        }

        if ($catalog['key'] === 'pharmacy') {
            $payload['prescriptions'] = Prescription::query()
                ->with(['patient:id,mrn,first_name,last_name,status', 'encounter:id,type,status', 'items.medication', 'prescribedBy:id,name'])
                ->whereIn('status', ['pending', 'verified'])
                ->latest()
                ->limit(50)
                ->get();
        }

        if (! empty($catalog['assignments'])) {
            $payload['assignments'] = BedAssignment::query()
                ->with(['patient:id,mrn,first_name,last_name,status', 'facility:id,name,status,parent_id', 'facility.parent:id,name', 'assignedBy:id,name', 'encounter:id,type,status', 'nurse:id,name', 'ward:id,name'])
                ->where('status', 'active')
                ->latest()
                ->limit(100)
                ->get();
        }

        if (in_array($catalog['key'], ['wards', 'beds'], true)) {
            $beds = Facility::query()
                ->with([
                    'type:id,name,slug',
                    'parent:id,name,status',
                    'department:id,name',
                    'activeAssignment.patient:id,mrn,first_name,last_name,status',
                    'activeAssignment.encounter:id,type,status',
                    'activeAssignment.nurse:id,name',
                ])
                ->whereHas('type', fn ($query) => $query->where('slug', 'bed'))
                ->orderBy('name')
                ->limit(200)
                ->get()
                ->map(fn (Facility $bed) => [
                    'id' => $bed->id,
                    'name' => $bed->name,
                    'status' => $bed->status,
                    'capacity' => $bed->capacity,
                    'current_utilization' => $bed->current_utilization,
                    'remaining_capacity' => $bed->remainingCapacity(),
                    'parent_id' => $bed->parent_id,
                    'parent' => $bed->parent,
                    'ward' => $bed->parent,
                    'department' => $bed->department,
                    'assignment' => $bed->activeAssignment,
                    'patient' => $bed->activeAssignment?->patient,
                    'updated_at' => $bed->updated_at,
                ]);

            $payload['beds'] = $beds;

            if ($catalog['key'] === 'wards') {
                $payload['facilities'] = collect($payload['facilities'])->map(function (array $ward) use ($beds) {
                    $wardBeds = $beds->where('parent_id', $ward['id'])->values();
                    $ward['beds'] = $wardBeds;
                    $ward['capacity'] = $wardBeds->isNotEmpty() ? (int) $wardBeds->sum('capacity') : $ward['capacity'];
                    $ward['current_utilization'] = $wardBeds->isNotEmpty() ? (int) $wardBeds->sum('current_utilization') : $ward['current_utilization'];
                    $ward['remaining_capacity'] = max(0, $ward['capacity'] - $ward['current_utilization']);

                    return $ward;
                })->all();

                $payload['stats']['capacity'] = (int) collect($payload['facilities'])->sum('capacity');
                $payload['stats']['utilization'] = (int) collect($payload['facilities'])->sum('current_utilization');
                $payload['stats']['remaining'] = max(0, $payload['stats']['capacity'] - $payload['stats']['utilization']);
            }
        }

        if (! empty($catalog['encounter_type'])) {
            $payload['encounters'] = Encounter::query()
                ->with(['patient:id,mrn,first_name,last_name,sex,status', 'clinician:id,name', 'facility:id,name', 'department:id,name'])
                ->where('type', $catalog['encounter_type'])
                ->whereIn('status', ['waiting', 'in_progress'])
                ->latest()
                ->limit(100)
                ->get();
        }

        return $payload;
    }

    public function updateFacilityStatus(Request $request, string $module, Facility $facility)
    {
        $catalog = ModuleCatalog::find($module);
        abort_unless($catalog, 404);
        $this->authorizePermission($request->user(), 'update', $catalog['subject']);

        $facility->loadMissing('type');
        if (! empty($catalog['facility_type'])) {
            abort_unless($facility->type?->slug === $catalog['facility_type'], 404);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(Facility::STATUSES)],
            'current_utilization' => ['nullable', 'integer', 'min:0'],
            'resource_notes' => ['nullable', 'string'],
        ]);

        if (isset($data['current_utilization']) && $data['current_utilization'] > $facility->capacity) {
            return response()->json(['message' => 'Utilization cannot exceed capacity.'], 422);
        }

        $facility->update($data);

        return [
            'id' => $facility->id,
            'status' => $facility->status,
            'capacity' => $facility->capacity,
            'current_utilization' => $facility->current_utilization,
            'remaining_capacity' => $facility->remainingCapacity(),
            'resource_notes' => $facility->resource_notes,
        ];
    }

    private function facilityTypeId(?string $slug): ?string
    {
        if (! $slug) {
            return null;
        }

        return Cache::remember('facility_type:'.$slug, 86400, fn () => FacilityType::query()->where('slug', $slug)->value('id'));
    }
}
