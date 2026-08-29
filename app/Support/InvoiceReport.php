<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;

class InvoiceReport
{
    public function build(Request $request): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        $invoices = Invoice::query()->whereBetween('invoices.created_at', [$from, $to]);
        $items = InvoiceItem::query()->whereHas('invoice', fn ($query) => $query->whereBetween('invoices.created_at', [$from, $to]));
        $payments = Payment::query()->whereBetween('received_at', [$from, $to]);
        $refunds = Refund::query()->whereBetween('occurred_at', [$from, $to]);

        $revenue = (int) (clone $invoices)->sum('total');
        $discounts = (int) (clone $invoices)->sum('discount_total');
        $refundTotal = (int) (clone $refunds)->sum('amount');
        $collected = (int) (clone $payments)->sum('amount');
        $outstanding = (int) (clone $invoices)->get()->sum(fn (Invoice $invoice) => $invoice->outstanding());

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'invoices' => (clone $invoices)->count(),
                'revenue' => $revenue,
                'discounts' => $discounts,
                'refunds' => $refundTotal,
                'collected' => $collected,
                'outstanding' => $outstanding,
            ],
            'by_date' => $this->group((clone $invoices)->selectRaw('date(invoices.created_at) as label, count(*) as count, coalesce(sum(invoices.total), 0) as amount')->groupBy('label')->orderBy('label')),
            'by_service' => $this->group((clone $items)->selectRaw('description as label, coalesce(sum(quantity), 0) as quantity, coalesce(sum(amount), 0) as amount, coalesce(sum(discount_amount), 0) as discounts')->groupBy('description')->orderByDesc('amount')->limit(50)),
            'by_category' => $this->group((clone $items)->leftJoin('clinical_services', 'clinical_services.id', '=', 'invoice_items.service_id')->selectRaw('coalesce(clinical_services.category, invoice_items.billable_type, ?) as label, coalesce(sum(invoice_items.amount), 0) as amount', ['other'])->groupBy('label')->orderByDesc('amount')),
            'by_customer' => $this->group((clone $invoices)->join('patients', 'patients.id', '=', 'invoices.patient_id')->selectRaw("trim(patients.first_name || ' ' || patients.last_name) as label, count(invoices.id) as count, coalesce(sum(invoices.total), 0) as amount")->groupBy('label')->orderByDesc('amount')->limit(50)),
            'by_branch' => $this->group((clone $invoices)->join('hospitals', 'hospitals.id', '=', 'invoices.hospital_id')->selectRaw('hospitals.name as label, count(invoices.id) as count, coalesce(sum(invoices.total), 0) as amount')->groupBy('hospitals.name')->orderByDesc('amount')),
            'by_user' => $this->group((clone $payments)->leftJoin('users', 'users.id', '=', 'payments.received_by')->selectRaw('coalesce(users.name, ?) as label, count(payments.id) as count, coalesce(sum(payments.amount), 0) as amount', ['System'])->groupBy('label')->orderByDesc('amount')),
            'by_payment_method' => $this->group((clone $payments)->selectRaw('method as label, count(*) as count, coalesce(sum(amount), 0) as amount')->groupBy('method')->orderByDesc('amount')),
            'discounts' => $this->group((clone $items)->where('discount_amount', '>', 0)->selectRaw('description as label, coalesce(sum(discount_amount), 0) as amount')->groupBy('description')->orderByDesc('amount')->limit(50)),
            'refunds' => $this->group((clone $refunds)->selectRaw('method as label, count(*) as count, coalesce(sum(amount), 0) as amount')->groupBy('method')->orderByDesc('amount')),
        ];
    }

    private function group($query): array
    {
        return $query->get()->map(fn ($row) => [
            'label' => $row->label,
            'count' => isset($row->count) ? (int) $row->count : null,
            'quantity' => isset($row->quantity) ? (int) $row->quantity : null,
            'amount' => (int) ($row->amount ?? 0),
            'discounts' => isset($row->discounts) ? (int) $row->discounts : null,
        ])->all();
    }
}
