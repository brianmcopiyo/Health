<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalService;
use App\Models\Dispensing;
use App\Models\Encounter;
use App\Models\Medication;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Support\ChargeLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrescriptionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()->hasPermission('read', 'Pharmacy')
            || $request->user()->hasPermission('read', 'Opd')
            || $request->user()->hasPermission('read', 'Emergency')
            || $request->user()->hasPermission('read', 'Ward'),
            403,
            'This action is unauthorized.'
        );

        $query = Prescription::query()
            ->with(['patient', 'encounter', 'prescribedBy', 'items.medication'])
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->boolean('queue')) {
            $query->whereIn('status', ['pending', 'verified']);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'encounter_id' => ['required', 'exists:encounters,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medication_id' => ['required', 'exists:medications,id'],
            'items.*.dose' => ['required', 'string', 'max:80'],
            'items.*.frequency' => ['required', 'string', 'max:80'],
            'items.*.duration' => ['nullable', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.instructions' => ['nullable', 'string', 'max:255'],
        ]);

        $encounter = Encounter::query()->findOrFail($data['encounter_id']);
        abort_unless(
            $request->user()->hasPermission('update', 'Opd')
            || $request->user()->hasPermission('update', 'Emergency')
            || $request->user()->hasPermission('update', 'Ward')
            || $request->user()->hasPermission('create', 'Pharmacy'),
            403,
            'This action is unauthorized.'
        );

        $rx = Prescription::query()->create([
            'hospital_id' => $encounter->hospital_id,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'prescribed_by' => $request->user()->id,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
            'prescribed_at' => now(),
        ]);

        foreach ($data['items'] as $item) {
            PrescriptionItem::query()->create([
                'prescription_id' => $rx->id,
                ...$item,
            ]);
        }

        return response()->json($rx->load(['patient', 'items.medication', 'prescribedBy']), 201);
    }

    public function updateStatus(Request $request, Prescription $prescription)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Prescription::STATUSES)],
        ]);

        if ($data['status'] === 'verified') {
            $this->authorizePermission($request->user(), 'update', 'Pharmacy');
            abort_unless($prescription->status === 'pending', 422, 'Prescription is not awaiting verification.');
            $prescription->verified_by = $request->user()->id;
            $prescription->verified_at = now();
        }

        if ($data['status'] === 'dispensed') {
            $this->authorizePermission($request->user(), 'update', 'Pharmacy');
            abort_unless(in_array($prescription->status, ['pending', 'verified'], true), 422, 'Prescription cannot be dispensed.');
            $this->dispense($prescription, $request->user()->id);
            $prescription->dispensed_at = now();
            if (! $prescription->verified_at) {
                $prescription->verified_by = $request->user()->id;
                $prescription->verified_at = now();
            }
        }

        if ($data['status'] === 'cancelled') {
            abort_unless(
                $request->user()->id === $prescription->prescribed_by
                || $request->user()->hasPermission('update', 'Pharmacy'),
                403,
                'This action is unauthorized.'
            );
        }

        $prescription->status = $data['status'];
        $prescription->save();

        return $prescription->refresh()->load(['patient', 'items.medication', 'prescribedBy', 'dispensings']);
    }

    public function medications(Request $request)
    {
        abort_unless(
            $request->user()->hasPermission('read', 'Pharmacy')
            || $request->user()->hasPermission('read', 'Opd')
            || $request->user()->hasPermission('read', 'Emergency')
            || $request->user()->hasPermission('read', 'Ward'),
            403,
            'This action is unauthorized.'
        );

        return Medication::query()->orderBy('name')->get();
    }

    public function services()
    {
        return ClinicalService::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function dispense(Prescription $prescription, int $userId): void
    {
        $prescription->load(['items.medication', 'encounter']);

        foreach ($prescription->items as $item) {
            abort_if($item->medication->stock_qty < $item->quantity, 422, $item->medication->name.' is out of stock.');
            $item->medication->adjustStock(-1 * $item->quantity);
            Dispensing::query()->create([
                'hospital_id' => $prescription->hospital_id,
                'patient_id' => $prescription->patient_id,
                'encounter_id' => $prescription->encounter_id,
                'prescription_id' => $prescription->id,
                'prescription_item_id' => $item->id,
                'medication_id' => $item->medication_id,
                'dispensed_by' => $userId,
                'quantity' => $item->quantity,
                'dispensed_at' => now(),
            ]);
            ChargeLedger::post(
                $prescription->encounter,
                'dispensing',
                $item->id,
                $item->medication->label(),
                $item->medication->unit_price,
                $item->quantity
            );
        }
    }
}
