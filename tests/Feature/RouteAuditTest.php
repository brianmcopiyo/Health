<?php

namespace Tests\Feature;

use App\Models\Ambulance;
use App\Models\AssistanceRequest;
use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBatch;
use App\Models\InventoryCount;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\InventoryReceipt;
use App\Models\InventoryRequest;
use App\Models\InventoryReturn;
use App\Models\InventoryStore;
use App\Models\InventoryTransfer;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PriceList;
use App\Models\Referral;
use App\Models\Role;
use App\Models\User;
use App\Support\Exports\ExportCatalog;
use App\Support\ModuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouteAuditTest extends TestCase
{
    use RefreshDatabase;

    private array $failures = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs($this->user('admin@riverside.test'));
    }

    public function test_seeded_lists_modules_and_spa_pages_succeed(): void
    {
        $this->withoutVite();

        foreach ($this->listRoutes() as $url) {
            $this->assertOk($url);
        }

        foreach (ModuleCatalog::all() as $module) {
            if (($module['key'] ?? null) === 'hospitals') {
                continue;
            }
            $this->assertOk('/api/modules/'.$module['key']);
        }

        foreach ($this->spaPages() as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('id="app"', false)
                ->assertSee('window.__PAGE_ERROR__ = null', false);
        }

        $this->assertNoFailures();
    }

    public function test_details_exports_and_filters_succeed(): void
    {
        foreach ($this->detailRoutes() as $url) {
            $this->assertOk($url);
        }

        foreach (array_keys(ExportCatalog::all()) as $dataset) {
            if ($dataset === 'hospitals') {
                continue;
            }
            $this->assertOk('/api/exports/'.$dataset);
        }

        Sanctum::actingAs($this->user('platform@health.test'));
        $this->assertOk('/api/hospitals');
        $this->assertOk('/api/modules/hospitals');
        $this->assertOk('/api/exports/hospitals');
        foreach (Hospital::query()->limit(5)->get() as $hospital) {
            $this->assertOk('/api/hospitals/'.$hospital->id);
        }

        $this->assertNoFailures();
    }

    public function test_invalid_ids_return_api_404(): void
    {
        $missing = '00000000-0000-4000-8000-000000000000';

        foreach ($this->detailTemplates() as $template) {
            $url = str_replace('{id}', $missing, $template);
            $response = $this->getJson($url);
            if ($response->status() !== 404) {
                $this->failures[] = $url.' expected 404, got '.$response->status().' '.$response->content();
            }
        }

        $this->assertNoFailures();
    }

    public function test_missing_relationships_do_not_500(): void
    {
        $patient = $this->postJson('/api/patients', [
            'first_name' => 'Orphan',
            'last_name' => 'Chart',
            'sex' => 'male',
        ])->assertCreated()->json();
        $this->assertOk('/api/patients/'.$patient['id']);

        $visit = $this->postJson('/api/encounters', [
            'patient_id' => $patient['id'],
            'type' => 'opd',
            'chief_complaint' => 'Review',
        ])->assertCreated()->json();
        $this->assertOk('/api/encounters/'.$visit['id']);

        $typeId = \App\Models\FacilityType::query()->value('id');
        if ($typeId) {
            $this->assertOk('/api/referrals/eligible-hospitals?facility_type_id='.$typeId);
        }

        $this->assertNoFailures();
    }

    public function test_clinical_and_admin_workflows(): void
    {
        $patient = $this->postJson('/api/patients', [
            'first_name' => 'Audit',
            'last_name' => 'Patient',
            'sex' => 'female',
        ])->assertCreated()->json();

        $this->putJson('/api/patients/'.$patient['id'], [
            'first_name' => 'Audit',
            'last_name' => 'Patient',
            'sex' => 'female',
            'phone' => '0700000002',
        ])->assertOk();

        $visit = $this->postJson('/api/encounters', [
            'patient_id' => $patient['id'],
            'type' => 'opd',
            'chief_complaint' => 'Audit visit',
        ])->assertCreated()->json();

        $this->getJson('/api/encounters/'.$visit['id'])->assertOk()
            ->assertJsonPath('patient.id', $patient['id']);
        $this->getJson('/api/patients/'.$patient['id'])->assertOk();

        $this->getJson('/api/auth/profile')->assertOk();
        $this->getJson('/api/auth/sessions')->assertOk();
        $this->getJson('/api/workspace')->assertOk();
        $this->getJson('/api/clinical-services')->assertOk();
        $this->getJson('/api/medications')->assertOk();
        $this->getJson('/api/facility-types')->assertOk();
        $this->getJson('/api/network/hospitals')->assertOk();
    }

    private function listRoutes(): array
    {
        return [
            '/api/dashboard',
            '/api/workspace',
            '/api/reports',
            '/api/reports/meta',
            '/api/reports/table',
            '/api/modules/catalog',
            '/api/modules/workspaces',
            '/api/network/hospitals',
            '/api/roles',
            '/api/permissions',
            '/api/users',
            '/api/users/directory',
            '/api/departments',
            '/api/staff-assignments',
            '/api/facility-types',
            '/api/facilities',
            '/api/patients',
            '/api/encounters',
            '/api/encounters?type=opd&open=1',
            '/api/bed-assignments',
            '/api/service-orders',
            '/api/prescriptions',
            '/api/inventory/dashboard',
            '/api/inventory/items',
            '/api/inventory/categories',
            '/api/inventory/units',
            '/api/inventory/suppliers',
            '/api/inventory/stores',
            '/api/inventory/locations',
            '/api/inventory/stock',
            '/api/inventory/batches',
            '/api/inventory/movements',
            '/api/inventory/receipts',
            '/api/inventory/transfers',
            '/api/inventory/requests',
            '/api/inventory/issues',
            '/api/inventory/returns',
            '/api/inventory/adjustments',
            '/api/inventory/counts',
            '/api/invoices',
            '/api/invoices/reports',
            '/api/pricing/history',
            '/api/pricing/services',
            '/api/pricing/catalog',
            '/api/price-lists',
            '/api/pricing-rules',
            '/api/tax-rates',
            '/api/service-packages',
            '/api/referrals',
            '/api/assistance-requests',
            '/api/ambulances',
            '/api/ambulance-trips',
            '/api/clinical-services',
            '/api/medications',
            '/api/auth/me',
            '/api/auth/profile',
            '/api/auth/sessions',
            '/api/auth/activity',
        ];
    }

    private function detailRoutes(): array
    {
        $urls = [];
        foreach (Patient::query()->limit(8)->get() as $row) {
            $urls[] = '/api/patients/'.$row->id;
        }
        foreach (Encounter::query()->limit(8)->get() as $row) {
            $urls[] = '/api/encounters/'.$row->id;
        }
        foreach (Facility::query()->limit(12)->get() as $row) {
            $urls[] = '/api/facilities/'.$row->id;
        }
        foreach (Department::query()->limit(8)->get() as $row) {
            $urls[] = '/api/departments/'.$row->id;
        }
        foreach (Invoice::query()->limit(8)->get() as $row) {
            $urls[] = '/api/invoices/'.$row->id;
        }
        foreach (Referral::query()->limit(5)->get() as $row) {
            $urls[] = '/api/referrals/'.$row->id;
        }
        foreach (AssistanceRequest::query()->limit(5)->get() as $row) {
            $urls[] = '/api/assistance-requests/'.$row->id;
        }
        foreach (Ambulance::query()->limit(5)->get() as $row) {
            $urls[] = '/api/ambulances/'.$row->id;
        }
        foreach (PriceList::query()->limit(5)->get() as $row) {
            $urls[] = '/api/price-lists/'.$row->id;
        }
        foreach (InventoryItem::query()->limit(8)->get() as $row) {
            $urls[] = '/api/inventory/items/'.$row->id;
        }
        foreach (InventoryStore::query()->limit(5)->get() as $row) {
            $urls[] = '/api/inventory/stores/'.$row->id;
        }
        foreach (InventoryBatch::query()->limit(5)->get() as $row) {
            $urls[] = '/api/inventory/batches/'.$row->id;
        }
        foreach (InventoryReceipt::query()->limit(5)->get() as $row) {
            $urls[] = '/api/inventory/receipts/'.$row->id;
        }
        foreach (InventoryTransfer::query()->limit(5)->get() as $row) {
            $urls[] = '/api/inventory/transfers/'.$row->id;
        }
        foreach (InventoryRequest::query()->limit(5)->get() as $row) {
            $urls[] = '/api/inventory/requests/'.$row->id;
        }
        foreach (InventoryIssue::query()->limit(5)->get() as $row) {
            $urls[] = '/api/inventory/issues/'.$row->id;
        }
        foreach (InventoryReturn::query()->limit(5)->get() as $row) {
            $urls[] = '/api/inventory/returns/'.$row->id;
        }
        foreach (InventoryAdjustment::query()->limit(5)->get() as $row) {
            $urls[] = '/api/inventory/adjustments/'.$row->id;
        }
        foreach (InventoryCount::query()->limit(5)->get() as $row) {
            $urls[] = '/api/inventory/counts/'.$row->id;
        }
        $hospitalId = $this->user('admin@riverside.test')->hospital_id;
        foreach (User::query()->where('hospital_id', $hospitalId)->limit(8)->get() as $row) {
            $urls[] = '/api/users/'.$row->id;
        }
        foreach (Role::query()->where('hospital_id', $hospitalId)->limit(8)->get() as $row) {
            $urls[] = '/api/roles/'.$row->id;
        }

        return $urls;
    }

    private function detailTemplates(): array
    {
        return [
            '/api/patients/{id}',
            '/api/encounters/{id}',
            '/api/facilities/{id}',
            '/api/hospitals/{id}',
            '/api/departments/{id}',
            '/api/invoices/{id}',
            '/api/referrals/{id}',
            '/api/assistance-requests/{id}',
            '/api/ambulances/{id}',
            '/api/price-lists/{id}',
            '/api/inventory/items/{id}',
            '/api/inventory/stores/{id}',
            '/api/inventory/batches/{id}',
            '/api/inventory/receipts/{id}',
            '/api/inventory/transfers/{id}',
            '/api/inventory/requests/{id}',
            '/api/inventory/issues/{id}',
            '/api/inventory/returns/{id}',
            '/api/inventory/adjustments/{id}',
            '/api/inventory/counts/{id}',
            '/api/users/{id}',
            '/api/roles/{id}',
        ];
    }

    private function spaPages(): array
    {
        return [
            '/admin',
            '/patients',
            '/encounters',
            '/reception',
            '/opd',
            '/emergency',
            '/wards',
            '/beds',
            '/laboratory',
            '/imaging',
            '/pharmacy',
            '/theatre',
            '/facilities',
            '/billing',
            '/billing/reports',
            '/pricing',
            '/reports',
            '/referrals',
            '/assistance',
            '/ambulances',
            '/inventory',
            '/inventory/items',
            '/inventory/stock',
            '/inventory/categories',
            '/inventory/units',
            '/inventory/suppliers',
            '/inventory/stores',
            '/inventory/locations',
            '/inventory/batches',
            '/inventory/movements',
            '/inventory/receipts',
            '/inventory/transfers',
            '/inventory/requests',
            '/inventory/issues',
            '/inventory/returns',
            '/inventory/adjustments',
            '/inventory/counts',
            '/admin/users',
            '/admin/roles',
            '/admin/hospitals',
            '/admin/departments',
            '/account/profile',
            '/account/security',
        ];
    }

    private function assertOk(string $url): void
    {
        try {
            $this->withoutExceptionHandling();
            $response = $this->getJson($url);
            $this->withExceptionHandling();
            if ($response->status() !== 200) {
                $this->failures[] = $url.' => HTTP '.$response->status().' '.$this->clip($response->content());
            }
        } catch (\Throwable $exception) {
            $this->withExceptionHandling();
            $this->failures[] = $url.' => '.$exception::class.': '.$exception->getMessage().' @ '.$exception->getFile().':'.$exception->getLine();
        }
    }

    private function assertNoFailures(): void
    {
        $this->assertSame([], $this->failures, implode("\n", $this->failures));
    }

    private function clip(?string $content): string
    {
        $content = trim((string) $content);

        return strlen($content) > 400 ? substr($content, 0, 400).'…' : $content;
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
