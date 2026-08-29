<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalService;
use App\Models\Encounter;
use App\Models\ServiceOrder;
use App\Support\Access;
use App\Support\ChargeLedger;
use App\Support\ModuleCatalog;
use App\Support\QueryList;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceOrderController extends Controller
{
    public function index(Request $request)
    {
        $module = $request->string('module')->toString();
        $query = ServiceOrder::query()
            ->with(['patient:id,mrn,first_name,last_name,status', 'facility:id,name,code', 'orderedBy:id,name', 'completedBy:id,name', 'encounter:id,type,status', 'service:id,name,code,unit_price'])
            ->latest();

        if ($module) {
            $catalog = ModuleCatalog::find($module);
            abort_unless($catalog, 422, 'Unknown module.');
            $this->authorizePermission($request->user(), 'read', $catalog['subject']);
            $query->where('module_key', $module);
        } else {
            abort_unless($request->user()->hasPermission('read', 'Patient'), 403, 'This action is unauthorized.');
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($encounterId = $request->input('encounter_id')) {
            $query->where('encounter_id', $encounterId);
        }

        return QueryList::paginate($query, $request);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'module_key' => ['required', 'string'],
            'patient_id' => ['nullable', TenantRules::inHospital('patients')],
            'encounter_id' => ['nullable', TenantRules::inHospital('encounters')],
            'facility_id' => ['nullable', TenantRules::inHospital('facilities')],
            'service_id' => ['nullable', TenantRules::inHospital('clinical_services')],
            'item_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $catalog = ModuleCatalog::find($data['module_key']);
        abort_unless($catalog, 422, 'Unknown module.');
        abort_unless(
            $request->user()->hasPermission('create', $catalog['subject'])
            || $request->user()->hasPermission('update', 'Opd')
            || $request->user()->hasPermission('update', 'Emergency')
            || $request->user()->hasPermission('update', 'Ward'),
            403,
            'This action is unauthorized.'
        );

        $encounter = ! empty($data['encounter_id']) ? Encounter::query()->findOrFail($data['encounter_id']) : null;
        $service = ! empty($data['service_id']) ? ClinicalService::query()->find($data['service_id']) : null;
        $patientId = $data['patient_id'] ?? $encounter?->patient_id;
        abort_unless($patientId, 422, 'Patient and test or item are required.');
        $patient = \App\Models\Patient::query()->findOrFail($patientId);
        abort_unless(Access::canViewPatient($request->user(), $patient), 403, 'This action is unauthorized.');
        if ($encounter) {
            abort_unless(Access::canViewEncounter($request->user(), $encounter) || Access::canUpdateEncounter($request->user(), $encounter), 403, 'This action is unauthorized.');
        }

        $order = ServiceOrder::query()->create([
            'hospital_id' => $request->user()->hospital_id,
            'patient_id' => $data['patient_id'] ?? $encounter?->patient_id,
            'encounter_id' => $encounter?->id,
            'facility_id' => $data['facility_id'] ?? null,
            'service_id' => $service?->id,
            'ordered_by' => $request->user()->id,
            'module_key' => $data['module_key'],
            'order_type' => $data['module_key'],
            'item_name' => $data['item_name'] ?? $service?->name,
            'notes' => $data['notes'] ?? null,
            'status' => 'requested',
            'requested_at' => now(),
        ]);

        abort_unless($order->patient_id && $order->item_name, 422, 'Patient and test or item are required.');

        return response()->json($order->load(['patient', 'facility', 'orderedBy', 'service']), 201);
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

        $status = $data['status'] ?? null;
        if ($status === 'collected') {
            $data['collected_at'] = now();
        }
        if ($status === 'scheduled') {
            $data['scheduled_at'] = now();
        }
        if ($status === 'processing' && ! $serviceOrder->collected_at && $serviceOrder->module_key === 'laboratory') {
            $data['collected_at'] = $serviceOrder->collected_at ?: now();
        }
        if (in_array($status, ['completed', 'cancelled'], true)) {
            $data['completed_by'] = $request->user()->id;
            $data['completed_at'] = now();
        }

        $serviceOrder->update($data);

        if ($status === 'completed' && $serviceOrder->encounter_id) {
            $code = $serviceOrder->service?->code;
            if ($code) {
                ChargeLedger::forService($serviceOrder->encounter, $code, 'order', $serviceOrder->id, $serviceOrder->item_name, $serviceOrder->service?->unit_price ?? 0);
            } else {
                ChargeLedger::post($serviceOrder->encounter, 'order', $serviceOrder->id, $serviceOrder->item_name, $serviceOrder->service?->unit_price ?? 80);
            }
        }

        return $serviceOrder->refresh()->load(['patient', 'facility', 'orderedBy', 'completedBy', 'service', 'encounter']);
    }
}
