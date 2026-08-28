<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Support\ModuleCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceOrderController extends Controller
{
    public function index(Request $request)
    {
        $module = $request->string('module')->toString();
        $catalog = ModuleCatalog::find($module);
        abort_unless($catalog, 422, 'Module is required.');
        $this->authorizePermission($request->user(), 'read', $catalog['subject']);

        $query = ServiceOrder::query()
            ->with(['patient', 'facility', 'orderedBy', 'completedBy'])
            ->where('module_key', $module)
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'module_key' => ['required', 'string'],
            'patient_id' => ['required', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'exists:encounters,id'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'item_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $catalog = ModuleCatalog::find($data['module_key']);
        abort_unless($catalog, 422, 'Unknown module.');
        $this->authorizePermission($request->user(), 'create', $catalog['subject']);

        $order = ServiceOrder::query()->create([
            ...$data,
            'hospital_id' => $request->user()->hospital_id,
            'ordered_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return response()->json($order->load(['patient', 'facility', 'orderedBy']), 201);
    }

    public function update(Request $request, ServiceOrder $serviceOrder)
    {
        $catalog = ModuleCatalog::find($serviceOrder->module_key);
        abort_unless($catalog, 404);
        $this->authorizePermission($request->user(), 'update', $catalog['subject']);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(ServiceOrder::STATUSES)],
            'result' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'item_name' => ['sometimes', 'string', 'max:255'],
        ]);

        if (in_array($data['status'] ?? null, ['completed', 'cancelled'], true)) {
            $data['completed_by'] = $request->user()->id;
        }

        $serviceOrder->update($data);

        return $serviceOrder->refresh()->load(['patient', 'facility', 'orderedBy', 'completedBy']);
    }
}
