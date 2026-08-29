<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\VolumeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DatabasePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $seeder = new VolumeSeeder;
        $seeder->patients = 800;
        $seeder->run();
    }

    public function test_patient_lists_are_paginated_and_searchable(): void
    {
        Sanctum::actingAs($this->user('reception@riverside.test'));

        $page = $this->getJson('/api/patients?per_page=25')->assertOk();
        $this->assertCount(25, $page->json('data'));
        $this->assertGreaterThan(25, $page->json('total'));
        $this->assertSame(1, $page->json('current_page'));

        $search = $this->getJson('/api/patients?q=Patton&per_page=25')->assertOk();
        $this->assertNotEmpty($search->json('data'));
        collect($search->json('data'))->each(function (array $patient) {
            $this->assertTrue(
                str_starts_with($patient['last_name'], 'Patton')
                || str_starts_with($patient['first_name'], 'Patience')
                || str_starts_with($patient['mrn'], 'Pat')
            );
        });
    }

    public function test_hot_queries_use_indexes(): void
    {
        $hospitalId = Hospital::query()->where('code', 'RGH')->value('id');
        $quoted = "'".$hospitalId."'";

        $patientPlan = $this->explain("SELECT id FROM patients WHERE hospital_id = {$quoted} AND last_name LIKE 'Patton%' ORDER BY last_name LIMIT 25");
        $this->assertStringContainsString('INDEX', $patientPlan);

        $encounterPlan = $this->explain("SELECT id FROM encounters WHERE hospital_id = {$quoted} AND type = 'opd' AND status = 'waiting' ORDER BY created_at DESC LIMIT 25");
        $this->assertStringContainsString('INDEX', $encounterPlan);

        $orderPlan = $this->explain("SELECT id FROM service_orders WHERE hospital_id = {$quoted} AND module_key = 'laboratory' AND status = 'requested' ORDER BY created_at DESC LIMIT 25");
        $this->assertStringContainsString('INDEX', $orderPlan);

        $invoicePlan = $this->explain("SELECT id FROM invoices WHERE hospital_id = {$quoted} AND status = 'draft' ORDER BY created_at DESC LIMIT 25");
        $this->assertStringContainsString('INDEX', $invoicePlan);
    }

    public function test_patient_index_avoids_n_plus_one_queries(): void
    {
        Sanctum::actingAs($this->user('reception@riverside.test'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/patients?compact=1&per_page=25')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(8, $count);
    }

    public function test_dashboard_and_filters_stay_within_tenant(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));
        $dashboard = $this->getJson('/api/reports')->assertOk()->json();
        $this->assertGreaterThan(100, $dashboard['patients']['total']);

        $lakesideHospital = Hospital::query()->where('code', 'LMC')->firstOrFail();
        $lakeside = Patient::withoutGlobalScope('hospital')->where('hospital_id', $lakesideHospital->id)->first()
            ?? Patient::withoutGlobalScope('hospital')->create([
                'hospital_id' => $lakesideHospital->id,
                'mrn' => 'LMC-0099',
                'first_name' => 'Other',
                'last_name' => 'Campus',
                'status' => 'active',
            ]);

        $ids = $this->jsonList($this->getJson('/api/patients?per_page=100')->assertOk())->pluck('id');
        $this->assertFalse($ids->contains($lakeside->id));

        $this->getJson('/api/encounters?type=opd&open=1&per_page=25')->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);
        $this->getJson('/api/service-orders?module=laboratory&status=requested&per_page=25')->assertOk();
        $this->getJson('/api/invoices?status=draft&per_page=25')->assertOk();
    }

    public function test_patient_allergy_updates_preserve_history(): void
    {
        Sanctum::actingAs($this->user('reception@riverside.test'));
        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();
        $before = $patient->allergies()->count();

        $this->putJson('/api/patients/'.$patient->id, [
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'allergies' => [['allergen' => 'Latex', 'severity' => 'moderate']],
        ])->assertOk();

        $this->assertGreaterThan($before, $patient->allergies()->count());
        $this->assertSame(1, $patient->allergies()->where('is_current', true)->count());
        $this->assertTrue($patient->allergies()->where('is_current', false)->exists());
        $this->assertSame('Latex', $patient->fresh()->currentAllergies()->first()->allergen);
    }

    public function test_hospital_delete_is_blocked_when_clinical_records_exist(): void
    {
        Sanctum::actingAs($this->user('platform@health.test'));
        $hospital = Hospital::query()->where('code', 'RGH')->firstOrFail();

        $this->deleteJson('/api/hospitals/'.$hospital->id)
            ->assertStatus(422);
    }

    private function explain(string $sql): string
    {
        return collect(DB::select('EXPLAIN QUERY PLAN '.$sql))->pluck('detail')->implode(' | ');
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
