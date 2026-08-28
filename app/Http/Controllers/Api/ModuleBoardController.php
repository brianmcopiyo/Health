<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BedAssignment;
use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\ServiceOrder;
use App\Support\ModuleCatalog;
use Illuminate\Http\Request;
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

        $facilityQuery = Facility::query()->with(['type', 'parent', 'department', 'hospital']);

        if (! empty($catalog['facility_type'])) {
            $facilityQuery->whereHas('type', fn ($query) => $query->where('slug', $catalog['facility_type']));
        }

        $facilities = $facilityQuery->orderBy('name')->get();

        $payload = [
            'module' => $catalog,
            'stats' => [
                'total' => $facilities->count(),
                'available' => $facilities->where('status', 'available')->count(),
                'occupied' => $facilities->where('status', 'occupied')->count(),
                'maintenance' => $facilities->where('status', 'maintenance')->count(),
                'unavailable' => $facilities->where('status', 'unavailable')->count(),
                'reserved' => $facilities->where('status', 'reserved')->count(),
                'capacity' => $facilities->sum('capacity'),
                'utilization' => $facilities->sum('current_utilization'),
                'remaining' => $facilities->sum(fn (Facility $facility) => $facility->remainingCapacity()),
            ],
            'facilities' => $facilities->map(fn (Facility $facility) => [
                'id' => $facility->id,
                'name' => $facility->name,
                'code' => $facility->code,
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
                ->get(),
        ];

        if (! empty($catalog['orders'])) {
            $payload['orders'] = ServiceOrder::query()
                ->with(['patient', 'facility', 'orderedBy', 'encounter'])
                ->where('module_key', $catalog['key'])
                ->latest()
                ->limit(50)
                ->get();
        }

        if ($catalog['key'] === 'pharmacy') {
            $payload['prescriptions'] = \App\Models\Prescription::query()
                ->with(['patient', 'encounter', 'items.medication', 'prescribedBy'])
                ->whereIn('status', ['pending', 'verified'])
                ->latest()
                ->limit(50)
                ->get();
        }

        if (! empty($catalog['assignments'])) {
            $payload['assignments'] = BedAssignment::query()
                ->with(['patient', 'facility', 'assignedBy', 'encounter', 'nurse'])
                ->where('status', 'active')
                ->latest()
                ->get();
        }

        if (! empty($catalog['encounter_type'])) {
            $payload['encounters'] = Encounter::query()
                ->with(['patient', 'clinician', 'facility', 'department', 'diagnoses', 'orders'])
                ->where('type', $catalog['encounter_type'])
                ->whereIn('status', ['waiting', 'in_progress'])
                ->latest()
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
}
