<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Support\ChargeLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::query()->with(['patient', 'items', 'encounter', 'payments'])->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'exists:encounters,id'],
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

        $invoice = Invoice::query()->create([
            'hospital_id' => $request->user()->hospital_id,
            'patient_id' => $data['patient_id'],
            'encounter_id' => $data['encounter_id'] ?? null,
            'number' => $this->nextNumber($request->user()->hospital?->code),
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

        return $invoice->refresh()->load(['patient', 'items', 'payments']);
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['nullable', Rule::in(Payment::METHODS)],
        ]);

        $payment = Payment::query()->create([
            'hospital_id' => $invoice->hospital_id,
            'invoice_id' => $invoice->id,
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

        return $invoice->refresh()->load(['patient', 'items', 'payments']);
    }

    private function nextNumber(?string $code): string
    {
        $count = Invoice::query()->count() + 1;

        return sprintf('%s-INV-%04d', $code ?: 'HMS', $count);
    }
}
