<?php

namespace Tests\Feature;

use App\Models\Encounter;
use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStore;
use App\Models\InventoryUnit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DatasetExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pdf_and_excel_export_the_filtered_register(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $kept = Patient::query()->create([
            'first_name' => 'Export',
            'last_name' => 'Alpha',
            'sex' => 'female',
            'status' => 'active',
            'mrn' => 'RGH-EXP-A',
        ]);
        $other = Patient::query()->create([
            'first_name' => 'Export',
            'last_name' => 'Beta',
            'sex' => 'male',
            'status' => 'discharged',
            'mrn' => 'RGH-EXP-B',
        ]);

        $pdf = $this->get('/api/exports/patients?format=pdf&q=Alpha');
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('Patients', $pdf->getContent());
        $this->assertStringContainsString('Export Alpha', $pdf->getContent());
        $this->assertStringNotContainsString('Export Beta', $pdf->getContent());
        $this->assertStringContainsString('Search', $pdf->getContent());
        $this->assertStringContainsString('Confidential operational report', $pdf->getContent());
        $this->assertStringContainsString('MediaBox [0 0 595.28 841.89]', $pdf->getContent());
        $this->assertStringNotContainsString('792 612', $pdf->getContent());
        $this->assertStringNotContainsString('Key performance', $pdf->getContent());

        $xlsx = $this->get('/api/exports/patients?format=xlsx&status=discharged');
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('Content-Type'));
        $sheet = $this->xlsxText($xlsx->getContent());
        $this->assertStringContainsString('Export Beta', $sheet);
        $this->assertStringNotContainsString('Export Alpha', $sheet);
        $this->assertStringNotContainsString('Key performance indicators', $sheet);

        $empty = $this->get('/api/exports/patients?format=pdf&q=ZZZNOMATCHXYZ');
        $empty->assertOk();
        $this->assertStringContainsString('No records match the current filters.', $empty->getContent());

        $selected = $this->get('/api/exports/patients?format=pdf&ids='.$kept->id);
        $selected->assertOk();
        $this->assertStringContainsString('Export Alpha', $selected->getContent());
        $this->assertStringNotContainsString('Export Beta', $selected->getContent());
        $this->assertSame($other->status, 'discharged');
    }

    public function test_exports_ignore_list_pagination(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        foreach (range(1, 20) as $index) {
            Patient::query()->create([
                'first_name' => 'Bulk',
                'last_name' => 'Patient'.$index,
                'sex' => 'female',
                'status' => 'active',
                'mrn' => 'RGH-BLK-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $pdf = $this->get('/api/exports/patients?format=pdf&q=Bulk&per_page=1&page=1');
        $pdf->assertOk();
        $this->assertStringContainsString('Patient1', $pdf->getContent());
        $this->assertStringContainsString('Patient20', $pdf->getContent());
    }

    public function test_list_pdf_paginates_long_text_and_date_filters(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        Patient::query()->create([
            'first_name' => 'SupercalifragilisticexpialidociousExportName',
            'last_name' => 'Wrapped',
            'sex' => 'female',
            'status' => 'active',
            'mrn' => 'RGH-LONG-1',
        ]);

        foreach (range(1, 60) as $index) {
            Patient::query()->create([
                'first_name' => 'Paged',
                'last_name' => 'Row'.$index,
                'sex' => 'male',
                'status' => 'active',
                'mrn' => 'RGH-PAGE-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $long = $this->get('/api/exports/patients?format=pdf&q=Supercalifragilistic');
        $long->assertOk();
        $this->assertStringContainsString('Supercalifragilistic', $long->getContent());
        $this->assertStringContainsString('Page 1 of 1', $long->getContent());

        $paged = $this->get('/api/exports/patients?format=pdf&q=Paged');
        $paged->assertOk();
        $this->assertStringContainsString('Page 1 of', $paged->getContent());
        $this->assertStringContainsString('Page 2 of', $paged->getContent());
        $this->assertStringContainsString('Row1', $paged->getContent());
        $this->assertStringContainsString('Row60', $paged->getContent());

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();
        $report = $this->get('/api/exports/invoice-reports?format=pdf&from='.$from.'&to='.$to);
        $report->assertOk();
        $this->assertStringContainsString('Sales reports', $report->getContent());
        $this->assertStringContainsString($from, $report->getContent());
        $this->assertStringContainsString($to, $report->getContent());
        $this->assertStringContainsString('MediaBox [0 0 595.28 841.89]', $report->getContent());
        $this->assertStringNotContainsString('Key performance', $report->getContent());
    }

    public function test_record_exports_nest_children_under_each_parent(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $beta = Patient::query()->create([
            'first_name' => 'Hier',
            'last_name' => 'Beta',
            'sex' => 'male',
            'status' => 'active',
            'mrn' => 'RGH-HIER-B',
        ]);
        $alpha = Patient::query()->create([
            'first_name' => 'Hier',
            'last_name' => 'Alpha',
            'sex' => 'female',
            'status' => 'active',
            'mrn' => 'RGH-HIER-A',
        ]);

        Encounter::query()->create([
            'patient_id' => $alpha->id,
            'type' => 'opd',
            'status' => 'completed',
        ]);
        $invoice = Invoice::query()->create([
            'patient_id' => $alpha->id,
            'number' => 'INV-HIER-A',
            'status' => 'issued',
            'total' => 2500,
            'issued_at' => now(),
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Hier consult',
            'quantity' => 1,
            'unit_amount' => 2500,
            'amount' => 2500,
        ]);

        $pdf = $this->get('/api/exports/patients?format=pdf&q=Hier');
        $pdf->assertOk();
        $content = $pdf->getContent();
        $this->assertStringContainsString('Hier Alpha', $content);
        $this->assertStringContainsString('Hier Beta', $content);
        $this->assertStringContainsString('Encounters', $content);
        $this->assertStringContainsString('Invoices', $content);
        $this->assertStringContainsString('INV-HIER-A', $content);
        $this->assertLessThan(strpos($content, 'Hier Alpha'), strpos($content, 'MRN'));
        $this->assertLessThan(strpos($content, 'Encounters'), strpos($content, 'Hier Alpha'));
        $this->assertLessThan(strpos($content, 'Hier Beta'), strpos($content, 'INV-HIER-A'));
        $this->assertFalse(str_contains(substr($content, strpos($content, 'Hier Beta')), 'INV-HIER-A'));
        $this->assertFalse(str_contains(substr($content, strpos($content, 'Hier Beta')), 'Encounters'));

        $xlsx = $this->get('/api/exports/patients?format=xlsx&q=Hier');
        $xlsx->assertOk();
        $sheet = $this->xlsxText($xlsx->getContent());
        $this->assertLessThan(strpos($sheet, 'Encounters'), strpos($sheet, 'Hier Alpha'));
        $this->assertLessThan(strpos($sheet, 'Hier Beta'), strpos($sheet, 'INV-HIER-A'));
        $this->assertFalse(str_contains(substr($sheet, strpos($sheet, 'Hier Beta')), 'INV-HIER-A'));

        $invoicePdf = $this->get('/api/exports/invoices?format=pdf&ids='.$invoice->id);
        $invoicePdf->assertOk();
        $invoiceContent = $invoicePdf->getContent();
        $this->assertStringContainsString('INV-HIER-A', $invoiceContent);
        $this->assertStringContainsString('Number', $invoiceContent);
        $this->assertStringContainsString('Line items', $invoiceContent);
        $this->assertStringContainsString('Hier consult', $invoiceContent);

        $unit = InventoryUnit::query()->firstOrFail();
        $store = InventoryStore::query()->where('name', 'Main Pharmacy')->firstOrFail();
        $itemA = InventoryItem::query()->create([
            'unit_id' => $unit->id,
            'kind' => 'medicine',
            'name' => 'Hier Alpha Tablet',
            'sku' => 'HIER-TAB-A',
            'unit_price' => 40,
            'cost_price' => 20,
            'is_active' => true,
        ]);
        InventoryItem::query()->create([
            'unit_id' => $unit->id,
            'kind' => 'medicine',
            'name' => 'Hier Beta Syrup',
            'sku' => 'HIER-SYR-B',
            'unit_price' => 30,
            'cost_price' => 15,
            'is_active' => true,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $itemA->id,
            'store_id' => $store->id,
            'quantity' => 14,
        ]);
        InventoryBatch::query()->create([
            'item_id' => $itemA->id,
            'store_id' => $store->id,
            'batch_number' => 'LOT-HMS-A',
            'quantity' => 14,
            'status' => 'available',
            'expiry_date' => now()->addYear()->toDateString(),
            'received_at' => now(),
        ]);
        foreach (range(1, 60) as $index) {
            InventoryMovement::query()->create([
                'item_id' => $itemA->id,
                'store_id' => $store->id,
                'type' => 'receive',
                'quantity' => 1,
                'occurred_at' => now()->subMinutes($index),
            ]);
        }

        $stock = $this->get('/api/exports/inventory-items?format=pdf&q=Hier');
        $stock->assertOk();
        $stockContent = $stock->getContent();
        $this->assertStringContainsString('Hier Alpha Tablet', $stockContent);
        $this->assertStringContainsString('Hier Beta Syrup', $stockContent);
        $this->assertStringContainsString('Stock by store', $stockContent);
        $this->assertStringContainsString('Batches', $stockContent);
        $this->assertStringContainsString('Stock movements', $stockContent);
        $this->assertStringContainsString('LOT-HMS-A', $stockContent);
        $this->assertStringContainsString('Page 1 of', $stockContent);
        $this->assertStringContainsString('Page 2 of', $stockContent);
        $this->assertLessThan(strpos($stockContent, 'Stock by store'), strpos($stockContent, 'Hier Alpha Tablet'));
        $this->assertLessThan(strpos($stockContent, 'Hier Beta Syrup'), strpos($stockContent, 'LOT-HMS-A'));
        $this->assertFalse(str_contains(substr($stockContent, strpos($stockContent, 'Hier Beta Syrup')), 'LOT-HMS-A'));
        $this->assertSame($beta->status, 'active');
    }

    public function test_authorization_and_validation(): void
    {
        Sanctum::actingAs($this->user('billing@riverside.test'));
        $this->get('/api/exports/invoices?format=pdf')->assertOk();
        $this->get('/api/exports/invoice-reports?format=xlsx')->assertOk();
        $this->getJson('/api/exports/inventory-items?format=pdf')->assertForbidden();
        $this->getJson('/api/exports/patients?format=csv')->assertStatus(422);
        $this->getJson('/api/exports/unknown-dataset?format=pdf')->assertNotFound();

        Sanctum::actingAs($this->user('reception@riverside.test'));
        $this->get('/api/exports/patients?format=pdf')->assertOk();
        $this->getJson('/api/exports/invoices?format=pdf')->assertForbidden();
    }

    private function xlsxText(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $xml = '';
        for ($index = 1; $index <= $zip->numFiles; $index++) {
            $xml .= (string) $zip->getFromName('xl/worksheets/sheet'.$index.'.xml');
        }
        $zip->close();
        @unlink($tmp);

        return html_entity_decode(strip_tags($xml));
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
