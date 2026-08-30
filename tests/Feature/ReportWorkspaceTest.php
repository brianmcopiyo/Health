<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_overview_keeps_legacy_snapshot_and_adds_workspace_payload(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $this->getJson('/api/reports')
            ->assertOk()
            ->assertJsonStructure([
                'facilities',
                'referrals',
                'assistance',
                'ambulances',
                'patients',
                'section',
                'tabs',
                'kpis',
                'charts',
                'table' => ['headers', 'items', 'meta'],
            ])
            ->assertJsonPath('section', 'overview');
    }

    public function test_tabs_are_role_aware(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));
        $admin = collect($this->getJson('/api/reports/meta')->assertOk()->json('tabs'))->pluck('key');
        $this->assertTrue($admin->contains('overview'));
        $this->assertTrue($admin->contains('billing'));
        $this->assertTrue($admin->contains('pharmacy'));
        $this->assertTrue($admin->contains('staff'));

        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $doctor = collect($this->getJson('/api/reports/meta')->assertOk()->json('tabs'))->pluck('key');
        $this->assertTrue($doctor->contains('patients'));
        $this->assertTrue($doctor->contains('opd'));
        $this->assertFalse($doctor->contains('billing'));
        $this->assertFalse($doctor->contains('pharmacy'));

        Sanctum::actingAs($this->user('billing@riverside.test'));
        $billing = collect($this->getJson('/api/reports/meta')->assertOk()->json('tabs'))->pluck('key');
        $this->assertTrue($billing->contains('overview'));
        $this->assertTrue($billing->contains('billing'));
        $this->assertTrue($billing->contains('patients'));
        $this->assertFalse($billing->contains('laboratory'));
    }

    public function test_section_permissions_are_enforced(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));
        $this->getJson('/api/reports')->assertForbidden();
        $this->getJson('/api/reports/export?format=pdf')->assertForbidden();

        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $this->getJson('/api/reports?section=patients')->assertOk();
        $this->getJson('/api/reports?section=billing')->assertForbidden();
        $this->getJson('/api/reports/export?section=billing&format=xlsx')->assertForbidden();
        $doctorPdf = $this->get('/api/reports/export?section=patients&format=pdf');
        $doctorPdf->assertOk();
        $this->assertStringContainsString('Patients', $doctorPdf->getContent());
        $this->assertStringContainsString('OPD', $doctorPdf->getContent());
        $this->assertStringNotContainsString('Billing', $doctorPdf->getContent());

        Sanctum::actingAs($this->user('billing@riverside.test'));
        $this->getJson('/api/reports?section=billing')->assertOk();
        $this->getJson('/api/reports?section=pharmacy')->assertForbidden();
    }

    public function test_filters_change_patient_counts_consistently(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $recent = Patient::query()->create([
            'first_name' => 'Report',
            'last_name' => 'Recent',
            'sex' => 'female',
            'status' => 'active',
            'mrn' => 'RGH-RPT-NEW',
        ]);
        $historic = Patient::query()->create([
            'first_name' => 'Report',
            'last_name' => 'Historic',
            'sex' => 'male',
            'status' => 'active',
            'mrn' => 'RGH-RPT-OLD',
        ]);
        $historic->forceFill([
            'created_at' => now()->subDays(80),
            'updated_at' => now()->subDays(80),
        ])->save();

        $wide = $this->getJson('/api/reports?section=patients&from='.now()->subDays(90)->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->json();
        $narrow = $this->getJson('/api/reports?section=patients&from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->json();

        $this->assertSame($wide['kpis'][0]['value'], $wide['table']['meta']['total']);
        $this->assertSame($narrow['kpis'][0]['value'], $narrow['table']['meta']['total']);
        $this->assertGreaterThan($narrow['table']['meta']['total'], $wide['table']['meta']['total']);

        $wideIds = collect($wide['table']['items'])->pluck('id');
        $narrowIds = collect($this->getJson('/api/reports/table?section=patients&from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString().'&per_page=100')->json('items'))->pluck('id');
        $this->assertTrue($wideIds->contains($historic->id) || collect($this->getJson('/api/reports/table?section=patients&from='.now()->subDays(90)->toDateString().'&to='.now()->toDateString().'&per_page=100')->json('items'))->pluck('id')->contains($historic->id));
        $this->assertFalse($narrowIds->contains($historic->id));
        $this->assertTrue($narrowIds->contains($recent->id) || $narrow['table']['meta']['total'] >= 1);
    }

    public function test_reports_stay_within_tenant_and_omit_sensitive_fields(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));
        $lakeside = Hospital::query()->where('name', 'Lakeside Medical Center')->firstOrFail();
        $foreign = Patient::withoutGlobalScope('hospital')->create([
            'hospital_id' => $lakeside->id,
            'mrn' => 'LMC-RPT-9',
            'first_name' => 'Other',
            'last_name' => 'Campus',
            'status' => 'active',
        ]);

        $table = $this->getJson('/api/reports/table?section=patients&per_page=100')->assertOk()->json();
        $ids = collect($table['items'])->pluck('id');
        $this->assertFalse($ids->contains($foreign->id));

        $row = $table['items'][0] ?? [];
        $this->assertArrayNotHasKey('phone', $row);
        $this->assertArrayNotHasKey('notes', $row);
        $this->assertArrayNotHasKey('national_id', $row);
        $this->assertArrayNotHasKey('chief_complaint', $row);
        $this->assertArrayNotHasKey('result', $row);
    }

    public function test_module_reports_use_live_aggregates(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        foreach (['encounters', 'opd', 'emergency', 'wards', 'beds', 'laboratory', 'imaging', 'pharmacy', 'theatre', 'referrals', 'assistance', 'ambulances', 'billing', 'staff'] as $section) {
            $payload = $this->getJson('/api/reports?section='.$section)->assertOk()->json();
            $this->assertSame($section, $payload['section']);
            $this->assertNotEmpty($payload['kpis']);
            $this->assertArrayHasKey('items', $payload['table']);
            $this->assertArrayHasKey('meta', $payload['table']);
        }
    }

    public function test_exports_include_hospital_and_selected_report(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $xlsx = $this->get('/api/reports/export?section=patients&format=xlsx');
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('content-type'));
        $this->assertStringStartsWith('PK', $xlsx->getContent());
        $workbook = $this->xlsxWorkbook($xlsx->getContent());
        $this->assertStringContainsString('Summary', $workbook);
        $this->assertStringContainsString('Executive Summary', $workbook);
        $this->assertStringContainsString('Emergency', $workbook);
        $this->assertStringContainsString('Billing', $workbook);

        $pdf = $this->get('/api/reports/export?section=billing&format=pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('Riverside', $pdf->getContent());
        $this->assertStringContainsString('Hospital report', $pdf->getContent());
        $this->assertStringContainsString('Billing', $pdf->getContent());
        $this->assertStringContainsString('Emergency', $pdf->getContent());
        $this->assertStringContainsString('Laboratory', $pdf->getContent());
        $this->assertStringContainsString('Patients', $pdf->getContent());
        $this->assertStringContainsString('Executive', $pdf->getContent());
        $this->assertStringNotContainsString('???', $pdf->getContent());
    }

    public function test_export_always_merges_every_allowed_tab(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $pdf = $this->get('/api/reports/export?section=emergency&format=pdf');
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('Hospital report', $pdf->getContent());
        foreach (['Overview', 'Patients', 'Clinical', 'OPD', 'Emergency', 'Inpatient', 'Beds', 'Laboratory', 'Imaging', 'Pharmacy', 'Theatre', 'Referrals', 'Assistance', 'Ambulances', 'Billing', 'Staff'] as $title) {
            $this->assertStringContainsString($title, $pdf->getContent(), $title.' missing from complete PDF');
        }
        $this->assertStringContainsString('Page ', $pdf->getContent());
        $this->assertStringNotContainsString('???', $pdf->getContent());

        $xlsx = $this->get('/api/reports/export?section=emergency&format=xlsx');
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->getContent());
        $workbook = $this->xlsxWorkbook($xlsx->getContent());
        $this->assertStringContainsString('Summary', $workbook);
        $this->assertStringContainsString('Emergency', $workbook);
        $this->assertStringContainsString('Laboratory', $workbook);
        $this->assertStringContainsString('Billing', $workbook);
    }

    public function test_export_resolves_filter_names_and_formats_values(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));
        $department = \App\Models\Department::query()->where('name', 'like', '%Emergency%')->first()
            ?? \App\Models\Department::query()->firstOrFail();

        $pdf = $this->get('/api/reports/export?section=emergency&format=pdf&department_id='.$department->id);
        $pdf->assertOk();
        $this->assertStringContainsString($department->name, $pdf->getContent());
        $this->assertStringNotContainsString($department->id, $pdf->getContent());
        $this->assertStringContainsString('Emergency', $pdf->getContent());
        $this->assertStringContainsString('Overview', $pdf->getContent());
        $this->assertStringNotContainsString('???', $pdf->getContent());
    }

    public function test_table_is_paginated(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        for ($index = 1; $index <= 8; $index++) {
            Patient::query()->create([
                'first_name' => 'Paged',
                'last_name' => 'Patient'.$index,
                'sex' => 'female',
                'status' => 'active',
                'mrn' => 'RGH-RPT-P'.$index,
            ]);
        }

        $page = $this->getJson('/api/reports/table?section=patients&per_page=5')->assertOk();
        $this->assertCount(5, $page->json('items'));
        $this->assertSame(1, $page->json('meta.current_page'));
        $this->assertGreaterThan(5, $page->json('meta.total'));
    }

    private function xlsxWorkbook(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $summary = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        return $workbook."\n".$summary;
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
