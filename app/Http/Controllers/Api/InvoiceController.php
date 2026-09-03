<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PriceHistory;
use App\Models\Refund;
use App\Support\Audit;
use App\Support\ChargeLedger;
use App\Support\HospitalSequence;
use App\Support\InvoiceReport;
use App\Support\PriceResolver;
use App\Support\QueryList;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

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

        if ($patientId = $request->input('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($search = $request->string('q')->toString()) {
            $term = QueryList::term($search);
            if ($term) {
                $query->where(function ($builder) use ($term) {
                    $builder->where('number', 'like', $term)
                        ->orWhereHas('patient', fn ($patient) => $patient
                            ->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('mrn', 'like', $term));
                });
            }
        }

        QueryList::equals($query, $request, 'payer_type');
        QueryList::dateRange($query, $request, 'issued_at');
        QueryList::numberRange($query, $request, 'total', 'min_total', 'max_total');

        $paginator = QueryList::paginate($query, $request);
        $paginator->getCollection()->transform(fn (Invoice $invoice) => $this->serialize($invoice));

        return $paginator;
    }

    public function reports(Request $request, InvoiceReport $report)
    {
        return $report->build($request);
    }

    public function store(Request $request, PriceResolver $resolver)
    {
        $data = $request->validate([
            'patient_id' => ['nullable', TenantRules::inHospital('patients')],
            'encounter_id' => ['nullable', TenantRules::inHospital('encounters')],
            'price_list_id' => ['nullable', TenantRules::inHospital('price_lists')],
            'payer_type' => ['nullable', 'in:self_pay,insurance'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.override' => ['boolean'],
            'items.*.override_reason' => ['required_if:items.*.override,true', 'string', 'min:3'],
            'items.*.unit_amount' => ['required_if:items.*.override,true', 'nullable', 'integer', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'integer', 'min:0'],
            'items.*.service_id' => ['nullable', TenantRules::inHospital('clinical_services')],
            'items.*.medication_id' => ['nullable', TenantRules::inHospital('medications')],
            'items.*.inventory_item_id' => ['nullable', TenantRules::inHospital('inventory_items')],
            'items.*.package_id' => ['nullable', TenantRules::inHospital('service_packages')],
        ]);

        if (! empty($data['items'])) {
            $data['items'] = array_values(array_filter(
                $data['items'],
                fn ($item) => ! empty($item['service_id']) || ! empty($item['medication_id']) || ! empty($item['inventory_item_id']) || ! empty($item['package_id'])
            ));
        }

        if (! empty($data['encounter_id']) && empty($data['items'])) {
            $encounter = Encounter::query()->findOrFail($data['encounter_id']);
            $invoice = ChargeLedger::openInvoice($encounter);

            return response()->json($this->serialize($invoice->load(['patient', 'items', 'encounter'])), 201);
        }

        abort_unless(! empty($data['patient_id']) && ! empty($data['items']), 422, 'Patient and line items are required.');

        $user = $request->user();

        $invoice = DB::transaction(function () use ($request, $data, $resolver, $user) {
            $invoice = Invoice::query()->create([
                'hospital_id' => $user->hospital_id,
                'patient_id' => $data['patient_id'],
                'encounter_id' => $data['encounter_id'] ?? null,
                'price_list_id' => $data['price_list_id'] ?? null,
                'payer_type' => $data['payer_type'] ?? 'self_pay',
                'number' => HospitalSequence::nextInvoiceNumber($user->hospital),
                'status' => 'draft',
                'total' => 0,
            ]);

            foreach ($data['items'] as $item) {
                $row = $this->pricedItem($resolver, $invoice, $item, $user);
                $created = InvoiceItem::query()->create($row);
                if (! empty($row['is_override'])) {
                    PriceHistory::query()->create([
                        'hospital_id' => $invoice->hospital_id,
                        'subject_type' => InvoiceItem::class,
                        'subject_id' => $created->id,
                        'field' => 'invoice_override',
                        'old_price' => $row['original_unit_price'],
                        'new_price' => $row['unit_amount'],
                        'changed_by' => $user->id,
                        'reason' => $row['override_reason'],
                    ]);
                    Audit::record('price_overridden', $created, [
                        'original_unit_price' => $row['original_unit_price'],
                        'unit_amount' => $row['unit_amount'],
                        'reason' => $row['override_reason'],
                    ]);
                }
            }

            $invoice->recalculateTotal();
            Audit::record('created', $invoice, ['number' => $invoice->number]);

            return $invoice;
        });

        return response()->json($this->serialize($invoice->load(['patient', 'items'])), 201);
    }

    public function show(Invoice $invoice)
    {
        return $this->serialize($invoice->load(['patient', 'items.service', 'encounter', 'payments', 'refunds', 'priceList']));
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

        return $this->serialize($invoice->refresh()->load(['patient', 'items', 'payments', 'refunds']));
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['nullable', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        DB::transaction(function () use ($request, $invoice, $data) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            Payment::query()->create([
                'hospital_id' => $invoice->hospital_id,
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'amount' => $data['amount'],
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'status' => 'completed',
                'received_by' => $request->user()->id,
                'received_at' => now(),
            ]);

            if ($invoice->fresh()->paidAmount() >= $invoice->total) {
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

        return $this->serialize($invoice->refresh()->load(['patient', 'items', 'payments', 'refunds']));
    }

    public function refund(Request $request, Invoice $invoice)
    {
        abort_unless($request->user()->hasPermission('refund', 'Invoice'), 403, 'This action is unauthorized.');

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reason' => ['nullable', 'string', 'max:255'],
            'payment_id' => ['nullable', TenantRules::inHospital('payments')],
        ]);

        abort_if($data['amount'] > $invoice->paidAmount(), 422, 'Refund exceeds collected payments.');

        $refund = DB::transaction(function () use ($request, $invoice, $data) {
            $refund = Refund::query()->create([
                'hospital_id' => $invoice->hospital_id,
                'invoice_id' => $invoice->id,
                'payment_id' => $data['payment_id'] ?? null,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reason' => $data['reason'] ?? null,
                'created_by' => $request->user()->id,
                'authorized_by' => $request->user()->id,
                'occurred_at' => now(),
            ]);

            $invoice = $invoice->fresh();
            if ($invoice->status === 'paid' && $invoice->outstanding() > 0) {
                $invoice->status = 'issued';
                $invoice->paid_at = null;
                $invoice->save();
            }

            Audit::record('refunded', $invoice, ['amount' => $data['amount']]);

            return $refund;
        });

        return response()->json($refund, 201);
    }

    private function pricedItem(PriceResolver $resolver, Invoice $invoice, array $item, $user): array
    {
        $billable = ! empty($item['service_id']) || ! empty($item['medication_id']) || ! empty($item['inventory_item_id']) || ! empty($item['package_id']);
        abort_unless($billable, 422, 'Select a service or product. Manual prices are not used on invoices.');

        $override = ! empty($item['override']);

        try {
            $quote = $resolver->quote([
                'service_id' => $item['service_id'] ?? null,
                'medication_id' => $item['medication_id'] ?? null,
                'inventory_item_id' => $item['inventory_item_id'] ?? null,
                'package_id' => $item['package_id'] ?? null,
                'patient_id' => $invoice->patient_id,
                'payer_type' => $invoice->payer_type,
                'price_list_id' => $invoice->price_list_id,
                'quantity' => $item['quantity'],
                'override' => $override,
                'override_reason' => $override ? ($item['override_reason'] ?? '') : null,
                'unit_amount' => $override ? ($item['unit_amount'] ?? null) : null,
                'discount_amount' => $item['discount_amount'] ?? 0,
                'discount_percent' => $item['discount_percent'] ?? 0,
                'allow_override' => $override && $user->hasPermission('override', 'Invoice'),
                'allow_discount' => $user->hasPermission('discount', 'Invoice'),
                'allow_exception' => $user->hasPermission('approve', 'Invoice'),
            ]);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return [
            'invoice_id' => $invoice->id,
            'service_id' => $quote['service_id'],
            'billable_type' => $quote['billable_type'],
            'billable_id' => $quote['billable_id'],
            'description' => $item['description'] ?? $quote['description'],
            'quantity' => $quote['quantity'],
            'unit_amount' => $quote['unit_price'],
            'list_price' => $quote['list_price'],
            'original_unit_price' => $quote['original_unit_price'],
            'discount_amount' => $quote['discount_amount'],
            'discount_percent' => $quote['discount_percent'],
            'tax_amount' => $quote['tax_amount'],
            'tax_rate' => $quote['tax_rate'],
            'amount' => $quote['line_total'],
            'price_list_id' => $quote['price_list_id'],
            'pricing_rule_id' => $quote['pricing_rule_id'],
            'is_override' => $quote['is_override'],
            'override_reason' => $quote['override_reason'],
            'overridden_by' => $quote['is_override'] ? $user->id : null,
            'overridden_at' => $quote['is_override'] ? now() : null,
        ];
    }

    private function serialize(Invoice $invoice): array
    {
        return [
            ...$invoice->toArray(),
            'outstanding' => $invoice->outstanding(),
            'paid_amount' => $invoice->paidAmount(),
        ];
    }
}
