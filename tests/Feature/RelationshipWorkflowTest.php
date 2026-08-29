<?php

namespace Tests\Feature;

use App\Models\BedAssignment;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RelationshipWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_ward_occupancy_is_calculated_from_assigned_beds(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $ward = Facility::query()->where('code', 'WARD-A')->firstOrFail();
        $beds = Facility::query()->where('parent_id', $ward->id)->whereHas('type', fn ($query) => $query->where('slug', 'bed'))->get();

        $this->assertSame((int) $beds->sum('capacity'), $ward->capacity);
        $this->assertSame((int) $beds->sum('current_utilization'), $ward->current_utilization);

        $board = $this->getJson('/api/modules/wards')->assertOk()->json();
        $row = collect($board['facilities'])->firstWhere('code', 'WARD-A');
        $this->assertSame((int) $beds->sum('capacity'), $row['capacity']);
        $this->assertSame((int) $beds->sum('current_utilization'), $row['current_utilization']);
        $this->assertNotEmpty($row['beds']);
    }

    public function test_assigning_and_transferring_beds_updates_both_sides(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));

        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();
        $origin = Facility::query()->where('code', 'BED-6')->firstOrFail();
        $destination = Facility::query()->where('code', 'BED-7')->firstOrFail();
        $ward = Facility::query()->where('code', 'WARD-A')->firstOrFail();
        $used = $ward->current_utilization;

        $assignment = $this->postJson('/api/bed-assignments', [
            'patient_id' => $patient->id,
            'facility_id' => $origin->id,
        ])->assertCreated()->json();

        $this->assertSame($used + 1, $ward->fresh()->current_utilization);
        $this->assertSame('occupied', $origin->fresh()->status);
        $this->assertSame($ward->id, $origin->fresh()->parent_id);

        $moved = $this->patchJson('/api/bed-assignments/'.$assignment['id'].'/transfer', [
            'facility_id' => $destination->id,
        ])->assertCreated()->json();

        $this->assertSame('transferred', BedAssignment::query()->find($assignment['id'])->status);
        $this->assertSame('active', $moved['status']);
        $this->assertSame($destination->id, $moved['facility_id']);
        $this->assertSame('available', $origin->fresh()->status);
        $this->assertSame('occupied', $destination->fresh()->status);
        $this->assertSame($used + 1, $ward->fresh()->current_utilization);
        $this->assertSame(2, BedAssignment::query()->where('patient_id', $patient->id)->count());
    }

    public function test_moving_a_bed_between_wards_recalculates_both_wards(): void
    {
        Sanctum::actingAs($this->user('manager@riverside.test'));

        $icu = Facility::query()->where('code', 'ICU-1')->firstOrFail();
        $ward = Facility::query()->where('code', 'WARD-A')->firstOrFail();
        $bed = Facility::query()->where('code', 'BED-8')->firstOrFail();

        $this->putJson('/api/facilities/'.$bed->id, [
            'parent_id' => $icu->id,
        ])->assertOk();

        $this->assertSame($icu->id, $bed->fresh()->parent_id);
        $this->assertSame(
            (int) Facility::query()->where('parent_id', $ward->id)->whereHas('type', fn ($query) => $query->where('slug', 'bed'))->sum('current_utilization'),
            $ward->fresh()->current_utilization
        );
        $this->assertSame(
            (int) Facility::query()->where('parent_id', $icu->id)->whereHas('type', fn ($query) => $query->where('slug', 'bed'))->sum('capacity'),
            $icu->fresh()->capacity
        );

        $this->putJson('/api/facilities/'.$bed->id, [
            'parent_id' => null,
        ])->assertOk();

        $this->assertNull($bed->fresh()->parent_id);
        $this->assertFalse(
            Facility::query()->where('parent_id', $icu->id)->whereKey($bed->id)->exists()
        );
    }

    public function test_nurse_can_view_ward_and_bed_detail_with_related_records(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));

        $ward = Facility::query()->where('code', 'WARD-A')->firstOrFail();
        $bed = Facility::query()->where('code', 'BED-1')->firstOrFail();

        $wardPayload = $this->getJson('/api/facilities/'.$ward->id)->assertOk()->json();
        $this->assertNotEmpty($wardPayload['beds']);
        $this->assertNotEmpty($wardPayload['occupants']);
        $this->assertNotEmpty($wardPayload['history']);
        $this->assertTrue(collect($wardPayload['occupants'])->contains(fn ($row) => ($row['patient']['mrn'] ?? null) === 'RGH-0002'));

        $bedPayload = $this->getJson('/api/facilities/'.$bed->id)->assertOk()->json();
        $this->assertSame($ward->id, $bedPayload['parent_id']);
        $this->assertNotNull($bedPayload['assignment']);
        $this->assertSame('RGH-0002', $bedPayload['assignment']['patient']['mrn']);
        $this->assertNotEmpty($bedPayload['history']);
    }

    public function test_assigning_from_ward_context_updates_bed_and_ward_detail(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));

        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();
        $ward = Facility::query()->where('code', 'WARD-A')->firstOrFail();
        $bed = Facility::query()->where('code', 'BED-6')->firstOrFail();

        $this->postJson('/api/bed-assignments', [
            'patient_id' => $patient->id,
            'facility_id' => $bed->id,
        ])->assertCreated();

        $wardPayload = $this->getJson('/api/facilities/'.$ward->id)->assertOk()->json();
        $this->assertTrue(collect($wardPayload['occupants'])->contains(fn ($row) => ($row['facility_id'] ?? null) === $bed->id));

        $bedPayload = $this->getJson('/api/facilities/'.$bed->id)->assertOk()->json();
        $this->assertSame($ward->id, $bedPayload['parent_id']);
        $this->assertSame($patient->id, $bedPayload['assignment']['patient_id'] ?? $bedPayload['assignment']['patient']['id']);
    }

    public function test_department_and_user_show_related_staff_and_encounters(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $department = \App\Models\Department::query()->where('slug', 'wards')->firstOrFail();
        $payload = $this->getJson('/api/departments/'.$department->id)->assertOk()->json();
        $this->assertNotEmpty($payload['facilities']);
        $this->assertTrue(collect($payload['users'])->contains(fn ($user) => $user['email'] === 'nurse@riverside.test' || $user['name'] === 'Grace Adeyemi'));

        $nurse = $this->user('nurse@riverside.test');
        $this->assertArrayHasKey('encounters', $payload);

        $staff = $this->getJson('/api/users/'.$nurse->id)->assertOk()->json();
        $this->assertNotEmpty($staff['staff_assignments']);
        $this->assertArrayHasKey('activity', $staff);
        $this->assertArrayHasKey('permissions', $staff);
    }

    public function test_role_and_patient_detail_include_related_records(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $role = \App\Models\Role::query()->where('slug', 'nurse')->where('hospital_id', $this->user('admin@riverside.test')->hospital_id)->firstOrFail();
        $payload = $this->getJson('/api/roles/'.$role->id)->assertOk()->json();
        $this->assertNotEmpty($payload['permissions']);
        $this->assertTrue(collect($payload['users'])->contains(fn ($user) => $user['email'] === 'nurse@riverside.test'));

        $patient = Patient::query()->where('mrn', 'RGH-0002')->firstOrFail();
        $chart = $this->getJson('/api/patients/'.$patient->id)->assertOk()->json();
        $this->assertNotEmpty($chart['encounters']);
        $this->assertNotEmpty($chart['bed_assignments']);
        $this->assertArrayHasKey('referrals', $chart);
        $this->assertNotNull($chart['active_bed']);
    }

    public function test_patient_archive_and_pharmacy_stock_adjustment(): void
    {
        Sanctum::actingAs($this->user('reception@riverside.test'));
        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();
        $archived = $this->patchJson('/api/patients/'.$patient->id.'/archive')->assertOk()->json();
        $this->assertNotEmpty($archived['archived_at']);

        Sanctum::actingAs($this->user('pharmacy@riverside.test'));
        $medication = Medication::query()->firstOrFail();
        $this->patchJson('/api/medications/'.$medication->id, [
            'stock_qty' => $medication->stock_qty + 5,
        ])->assertOk()->assertJsonPath('stock_qty', $medication->stock_qty + 5);
    }

    public function test_administrator_can_create_a_ward_and_see_it_on_the_board(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $typeId = Facility::query()->where('code', 'WARD-A')->value('facility_type_id');
        $departmentId = \App\Models\Department::query()->where('slug', 'wards')->value('id');

        $ward = $this->postJson('/api/facilities', [
            'facility_type_id' => $typeId,
            'department_id' => $departmentId,
            'name' => 'Ward B',
            'code' => 'WARD-B',
            'capacity' => 6,
            'status' => 'available',
        ])->assertCreated()->json();

        $this->assertSame('WARD-B', $ward['code']);
        $this->assertSame('ward', $ward['type']['slug'] ?? null);

        $board = $this->getJson('/api/modules/wards')->assertOk()->json();
        $this->assertTrue(collect($board['facilities'])->contains(fn ($row) => $row['code'] === 'WARD-B'));

        $shown = $this->getJson('/api/facilities/'.$ward['id'])->assertOk()->json();
        $this->assertSame(6, $shown['capacity']);
        $this->assertEmpty($shown['beds']);
    }

    public function test_nurse_cannot_create_a_ward(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));

        $typeId = Facility::query()->where('code', 'WARD-A')->value('facility_type_id');
        $this->postJson('/api/facilities', [
            'facility_type_id' => $typeId,
            'name' => 'Overflow',
            'code' => 'WARD-N',
            'capacity' => 2,
        ])->assertForbidden();
    }

    public function test_nurse_can_list_beds_but_not_all_facilities(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));

        $this->getJson('/api/facilities')->assertForbidden();
        $this->getJson('/api/facilities?type=bed')->assertOk();
        $this->getJson('/api/facilities?type=ward')->assertOk();
    }

    public function test_lakeside_cannot_see_riverside_ward_beds(): void
    {
        Sanctum::actingAs($this->user('admin@lakeside.test'));
        $riversideWard = Facility::withoutGlobalScope('hospital')->where('code', 'WARD-A')->whereHas('hospital', fn ($query) => $query->where('code', 'RGH'))->firstOrFail();
        $this->getJson('/api/facilities/'.$riversideWard->id)->assertNotFound();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
