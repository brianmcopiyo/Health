<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::query()->with(['patient', 'items'])->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'exists:encounters,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_amount' => ['required', 'integer', 'min:0'],
        ]);

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
        return $invoice->load(['patient', 'items', 'encounter']);
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

        return $invoice->refresh()->load(['patient', 'items']);
    }

    private function nextNumber(?string $code): string
    {
        $count = Invoice::query()->count() + 1;

        return sprintf('%s-INV-%04d', $code ?: 'HMS', $count);
    }
}
