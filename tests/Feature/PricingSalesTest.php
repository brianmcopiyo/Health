<?php

namespace Tests\Feature;

use App\Models\ClinicalService;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingSalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs($this->user('billing@riverside.test'));
    }

    public function test_quote_uses_insurance_list_and_category_discount(): void
    {
        $service = ClinicalService::query()->where('code', 'OPD-CON')->firstOrFail();
        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();

        $list = $this->postJson('/api/price-lists', [
            'name' => 'NHIF OPD',
            'kind' => 'insurance',
        ])->assertCreated();

        $this->postJson('/api/price-lists/'.$list->json('id').'/items', [
            'billable_type' => 'service',
            'billable_id' => $service->id,
            'unit_price' => 100,
        ])->assertCreated();

        $this->postJson('/api/pricing-rules', [
            'name' => 'Consult 10%',
            'type' => 'discount_percent',
            'scope' => 'category',
            'service_category' => 'consultation',
            'value' => 10,
        ])->assertCreated();

        $quote = $this->postJson('/api/pricing/quote', [
            'service_id' => $service->id,
            'patient_id' => $patient->id,
            'payer_type' => 'insurance',
            'quantity' => 1,
        ])->assertOk();

        $this->assertSame(100, (int) $quote->json('list_price'));
        $this->assertSame(10, (int) $quote->json('discount_amount'));
        $this->assertSame(90, (int) $quote->json('line_total'));
        $this->assertSame(90, (int) $quote->json('original_unit_price'));

        $ignored = $this->postJson('/api/pricing/quote', [
            'service_id' => $service->id,
            'patient_id' => $patient->id,
            'payer_type' => 'insurance',
            'quantity' => 1,
            'unit_amount' => 1,
        ])->assertOk();
        $this->assertSame(90, (int) $ignored->json('line_total'));
        $this->assertFalse((bool) $ignored->json('is_override'));
    }

    public function test_quantity_department_and_product_prices_resolve_automatically(): void
    {
        $service = ClinicalService::query()->where('code', 'OPD-CON')->firstOrFail();
        $medication = Medication::query()->where('sku', 'PCM-500')->firstOrFail();
        $inventory = InventoryItem::query()->where('is_active', true)->firstOrFail();
        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();

        $qtyList = $this->postJson('/api/price-lists', [
            'name' => 'Volume consults',
            'kind' => 'self_pay',
            'is_default' => true,
        ])->assertCreated();
        $this->postJson('/api/price-lists/'.$qtyList->json('id').'/items', [
            'billable_type' => 'service',
            'billable_id' => $service->id,
            'min_quantity' => 3,
            'unit_price' => 120,
        ])->assertCreated();

        $qtyQuote = $this->postJson('/api/pricing/quote', [
            'service_id' => $service->id,
            'quantity' => 3,
        ])->assertOk();
        $this->assertSame(120, (int) $qtyQuote->json('unit_price'));

        $deptList = $this->postJson('/api/price-lists', [
            'name' => 'OPD desk',
            'kind' => 'department',
            'department_id' => $service->department_id,
        ])->assertCreated();
        $this->postJson('/api/price-lists/'.$deptList->json('id').'/items', [
            'billable_type' => 'service',
            'billable_id' => $service->id,
            'unit_price' => 130,
        ])->assertCreated();

        $deptQuote = $this->postJson('/api/pricing/quote', [
            'service_id' => $service->id,
            'quantity' => 1,
        ])->assertOk();
        $this->assertSame(130, (int) $deptQuote->json('unit_price'));

        $medInvoice = $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'items' => [[
                'medication_id' => $medication->id,
                'quantity' => 2,
            ]],
        ])->assertCreated();
        $this->assertSame((int) $medication->unit_price * 2, (int) $medInvoice->json('total'));
        $this->assertSame('medication', $medInvoice->json('items.0.billable_type'));

        $stockInvoice = $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'items' => [[
                'inventory_item_id' => $inventory->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();
        $this->assertSame((int) $inventory->unit_price, (int) $stockInvoice->json('items.0.unit_amount'));
        $this->assertSame('inventory', $stockInvoice->json('items.0.billable_type'));
    }

    public function test_invoice_snapshots_price_and_override_requires_permission(): void
    {
        $service = ClinicalService::query()->where('code', 'OPD-CON')->firstOrFail();
        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();

        $invoice = $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'items' => [[
                'service_id' => $service->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();

        $this->assertSame((int) $service->unit_price, (int) $invoice->json('items.0.unit_amount'));
        $this->assertSame((int) $service->unit_price, (int) $invoice->json('items.0.original_unit_price'));
        $this->assertSame((int) $service->unit_price, (int) $invoice->json('total'));

        $original = (int) $invoice->json('items.0.unit_amount');
        $service->forceFill(['unit_price' => $service->unit_price + 50])->save();

        $this->getJson('/api/invoices/'.$invoice->json('id'))
            ->assertOk()
            ->assertJsonPath('items.0.unit_amount', $original);

        Sanctum::actingAs($this->restrictedClerk($patient->hospital_id));
        $tampered = $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'items' => [[
                'service_id' => $service->id,
                'quantity' => 1,
                'unit_amount' => 20,
            ]],
        ])->assertCreated();
        $this->assertSame((int) $service->fresh()->unit_price, (int) $tampered->json('items.0.unit_amount'));
        $this->assertFalse((bool) $tampered->json('items.0.is_override'));

        $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'items' => [[
                'service_id' => $service->id,
                'quantity' => 1,
                'override' => true,
                'unit_amount' => 20,
                'override_reason' => 'Courtesy waiver',
            ]],
        ])->assertStatus(422);

        Sanctum::actingAs($this->user('billing@riverside.test'));
        $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'items' => [[
                'service_id' => $service->id,
                'quantity' => 1,
                'override' => true,
                'unit_amount' => 40,
            ]],
        ])->assertStatus(422);

        $overridden = $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'items' => [[
                'service_id' => $service->id,
                'quantity' => 1,
                'override' => true,
                'unit_amount' => 40,
                'override_reason' => 'Approved hardship rate',
            ]],
        ])->assertCreated();

        $this->assertTrue((bool) $overridden->json('items.0.is_override'));
        $this->assertSame(40, (int) $overridden->json('items.0.unit_amount'));
        $this->assertSame((int) $service->fresh()->unit_price, (int) $overridden->json('items.0.original_unit_price'));
        $this->assertSame('Approved hardship rate', $overridden->json('items.0.override_reason'));
        $this->assertSame($this->user('billing@riverside.test')->id, $overridden->json('items.0.overridden_by'));
        $this->assertNotEmpty($overridden->json('items.0.overridden_at'));
        $this->assertNotEmpty($this->getJson('/api/pricing/history')->assertOk()->json('data'));
    }

    public function test_partial_payment_refund_and_sales_report(): void
    {
        $service = ClinicalService::query()->where('code', 'OPD-CON')->firstOrFail();
        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();

        $invoice = $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'items' => [[
                'service_id' => $service->id,
                'quantity' => 2,
            ]],
        ])->assertCreated();

        $total = (int) $invoice->json('total');
        $this->assertSame((int) $service->unit_price * 2, $total);

        $this->postJson('/api/invoices/'.$invoice->json('id').'/payments', [
            'amount' => (int) floor($total / 2),
            'method' => 'cash',
        ])->assertOk();

        $show = $this->getJson('/api/invoices/'.$invoice->json('id'))->assertOk();
        $this->assertSame((int) floor($total / 2), (int) $show->json('paid_amount'));
        $this->assertSame($total - (int) floor($total / 2), (int) $show->json('outstanding'));
        $this->assertSame('issued', $show->json('status'));

        $this->postJson('/api/invoices/'.$invoice->json('id').'/refunds', [
            'amount' => 20,
            'method' => 'cash',
            'reason' => 'Overcharge correction',
        ])->assertCreated();

        $after = $this->getJson('/api/invoices/'.$invoice->json('id'))->assertOk();
        $this->assertSame($total, (int) $after->json('total'));
        $this->assertSame((int) floor($total / 2) - 20, (int) $after->json('paid_amount'));
        $this->assertNotEmpty($after->json('refunds'));

        $report = $this->getJson('/api/invoices/reports')->assertOk();
        $this->assertGreaterThan(0, (int) $report->json('summary.revenue'));
        $this->assertGreaterThan(0, (int) $report->json('summary.refunds'));
    }

    public function test_package_can_be_sold_on_an_invoice(): void
    {
        $service = ClinicalService::query()->where('code', 'OPD-CON')->firstOrFail();
        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();

        $package = $this->postJson('/api/service-packages', [
            'name' => 'Antenatal bundle',
            'code' => 'ANC-1',
            'unit_price' => 400,
            'items' => [[
                'service_id' => $service->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();

        $invoice = $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'items' => [[
                'package_id' => $package->json('id'),
                'quantity' => 1,
            ]],
        ])->assertCreated();

        $this->assertSame(400, (int) $invoice->json('total'));
        $this->assertSame('package', $invoice->json('items.0.billable_type'));
        $this->assertSame(1, Invoice::query()->whereKey($invoice->json('id'))->count());
    }

    private function restrictedClerk(string $hospitalId): User
    {
        $role = Role::query()->create([
            'hospital_id' => $hospitalId,
            'name' => 'Invoice Clerk',
            'slug' => 'invoice-clerk-'.uniqid(),
            'workspace' => 'billing',
        ]);
        $ids = Permission::query()
            ->where('subject', 'Invoice')
            ->whereIn('action', ['read', 'create'])
            ->pluck('id');
        $role->permissions()->sync($ids);

        return User::query()->create([
            'name' => 'Clerk',
            'email' => 'clerk-'.uniqid().'@riverside.test',
            'password' => 'password',
            'hospital_id' => $hospitalId,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
