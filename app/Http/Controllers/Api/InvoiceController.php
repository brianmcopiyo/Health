<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Support\Audit;
use App\Support\ChargeLedger;
use App\Support\HospitalSequence;
use App\Support\QueryList;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::query()
            ->with(['patient:id,mrn,first_name,last_name,status', 'items', 'encounter:id,type,status', 'payments'])
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($patientId = $request->integer('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        return QueryList::paginate($query, $request);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['nullable', TenantRules::inHospital('patients')],
            'encounter_id' => ['nullable', TenantRules::inHospital('encounters')],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_amount' => ['required_with:items', 'integer', 'min:0'],
        ]);

        if (! empty($data['items'])) {
            $data['items'] = array_values(array_filter(
                $data['items'],
                fn ($item) => ! empty($item['description'])
            ));
        }

        if (! empty($data['encounter_id']) && empty($data['items'])) {
            $encounter = Encounter::query()->findOrFail($data['encounter_id']);
            $invoice = ChargeLedger::openInvoice($encounter);

            return response()->json($invoice->load(['patient', 'items', 'encounter']), 201);
        }

        abort_unless(! empty($data['patient_id']) && ! empty($data['items']), 422, 'Patient and line items are required.');

        $invoice = DB::transaction(function () use ($request, $data) {
            $invoice = Invoice::query()->create([
                'hospital_id' => $request->user()->hospital_id,
                'patient_id' => $data['patient_id'],
                'encounter_id' => $data['encounter_id'] ?? null,
                'number' => HospitalSequence::nextInvoiceNumber($request->user()->hospital),
                'status' => 'draft',
                'total' => 0,
            ]);

            foreach ($data['items'] as $item) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_amount' => $item['unit_amount'],
                    'amount' => $item['quantity'] * $item['unit_amount'],
                ]);
            }

            $invoice->recalculateTotal();
            Audit::record('created', $invoice, ['number' => $invoice->number]);

            return $invoice;
        });

        return response()->json($invoice->load(['patient', 'items']), 201);
    }

    public function show(Invoice $invoice)
    {
        return $invoice->load(['patient', 'items', 'encounter', 'payments']);
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Invoice::STATUSES)],
        ]);

        $previous = $invoice->status;

        if ($data['status'] === 'issued' && $invoice->status === 'draft') {
            $invoice->issued_at = now();
        }

        if ($data['status'] === 'paid') {
            $invoice->paid_at = now();
            if (! $invoice->issued_at) {
                $invoice->issued_at = now();
            }
        }

        $invoice->status = $data['status'];
        $invoice->save();
        Audit::record('status_changed', $invoice, ['from' => $previous, 'to' => $data['status']]);

        return $invoice->refresh()->load(['patient', 'items', 'payments']);
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['nullable', Rule::in(Payment::METHODS)],
        ]);

        DB::transaction(function () use ($request, $invoice, $data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            Payment::query()->create([
                'hospital_id' => $invoice->hospital_id,
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'amount' => $data['amount'],
                'method' => $data['method'] ?? 'cash',
                'received_by' => $request->user()->id,
                'received_at' => now(),
            ]);

            $paid = (int) $invoice->payments()->sum('amount');
            if ($paid >= $invoice->total) {
                $invoice->status = 'paid';
                $invoice->paid_at = now();
                if (! $invoice->issued_at) {
                    $invoice->issued_at = now();
                }
                $invoice->save();
            } elseif ($invoice->status === 'draft') {
                $invoice->status = 'issued';
                $invoice->issued_at = now();
                $invoice->save();
            }

            Audit::record('payment_received', $invoice, ['amount' => $data['amount']]);
        });

        return $invoice->refresh()->load(['patient', 'items', 'payments']);
    }
}
