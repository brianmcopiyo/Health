<?php

namespace Tests\Feature;

use App\Models\Ambulance;
use App\Models\AssistanceRequest;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_returns_token_and_abilities(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@riverside.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('userData.role', 'administrator')
            ->assertJsonPath('userData.hospitalName', 'Riverside General Hospital')
            ->assertJsonPath('userData.homeRoute', 'admin')
            ->assertJsonStructure(['accessToken', 'userData', 'userAbilityRules', 'navigation']);
    }

    public function test_invalid_login_is_rejected(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'admin@riverside.test',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_nurse_cannot_manage_users(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_hospital_admin_cannot_see_other_hospital_users(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $emails = collect($this->getJson('/api/users')->assertOk()->json())->pluck('email');

        $this->assertTrue($emails->contains('doctor@riverside.test'));
        $this->assertFalse($emails->contains('admin@lakeside.test'));
        $this->assertFalse($emails->contains('platform@health.test'));
    }

    public function test_facility_lists_are_tenant_scoped(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));
        $ids = collect($this->getJson('/api/facilities')->assertOk()->json())->pluck('id');
        $lakesideFacility = Facility::withoutGlobalScope('hospital')->where('hospital_id', $this->hospital('LMC')->id)->first();

        $this->assertTrue($ids->contains(Facility::query()->where('code', 'WARD-A')->first()->id));
        $this->assertFalse($ids->contains($lakesideFacility->id));
        $this->getJson('/api/facilities/'.$lakesideFacility->id)->assertNotFound();
    }

    public function test_facility_status_and_capacity_rules(): void
    {
        Sanctum::actingAs($this->user('manager@riverside.test'));
        $facility = Facility::query()->where('code', 'CON-3')->first();

        $this->patchJson('/api/facilities/'.$facility->id.'/status', [
            'status' => 'maintenance',
            'current_utilization' => 0,
        ])->assertOk()->assertJsonPath('status', 'maintenance');

        $this->patchJson('/api/facilities/'.$facility->id.'/status', [
            'status' => 'available',
            'current_utilization' => 9,
        ])->assertStatus(422);
    }

    public function test_reception_cannot_create_facilities(): void
    {
        Sanctum::actingAs($this->user('reception@riverside.test'));

        $this->postJson('/api/facilities', [
            'facility_type_id' => 1,
            'name' => 'Overflow Ward',
            'code' => 'WARD-X',
            'capacity' => 4,
        ])->assertForbidden();
    }

    public function test_eligible_referral_hospitals_require_available_capacity(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $wardTypeId = Facility::query()->where('code', 'WARD-A')->first()->facility_type_id;

        $response = $this->getJson('/api/referrals/eligible-hospitals?facility_type_id='.$wardTypeId.'&required_capacity=1')
            ->assertOk();

        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($this->hospital('LMC')->id));
        $this->assertFalse($ids->contains($this->hospital('RGH')->id));
    }

    public function test_referral_is_rejected_without_capacity(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $theatreTypeId = Facility::withoutGlobalScope('hospital')
            ->where('hospital_id', $this->hospital('LMC')->id)
            ->where('code', 'TH-1')
            ->first()
            ->facility_type_id;

        Facility::withoutGlobalScope('hospital')
            ->where('hospital_id', $this->hospital('LMC')->id)
            ->where('code', 'TH-1')
            ->update(['status' => 'occupied', 'current_utilization' => 1, 'capacity' => 1]);

        $this->postJson('/api/referrals', [
            'to_hospital_id' => $this->hospital('LMC')->id,
            'patient_name' => 'Test Patient',
            'reason' => 'Surgery',
            'required_facility_type_id' => $theatreTypeId,
            'required_capacity' => 1,
        ])->assertStatus(422);
    }

    public function test_referral_workflow_and_isolation(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $wardTypeId = Facility::query()->where('code', 'WARD-A')->first()->facility_type_id;

        $create = $this->postJson('/api/referrals', [
            'to_hospital_id' => $this->hospital('LMC')->id,
            'patient_name' => 'Ama Serwaa',
            'patient_reference' => 'RGH-100',
            'reason' => 'ICU overflow',
            'required_facility_type_id' => $wardTypeId,
            'required_capacity' => 1,
        ])->assertCreated();

        $referralId = $create->json('id');

        Sanctum::actingAs($this->user('nurse@riverside.test'));
        $this->patchJson('/api/referrals/'.$referralId.'/status', ['status' => 'accepted'])
            ->assertForbidden();

        Sanctum::actingAs($this->user('doctor@lakeside.test'));
        $icu = Facility::withoutGlobalScope('hospital')
            ->where('hospital_id', $this->hospital('LMC')->id)
            ->where('code', 'ICU-1')
            ->first();
        $before = $icu->current_utilization;

        $this->patchJson('/api/referrals/'.$referralId.'/status', [
            'status' => 'accepted',
            'destination_facility_id' => $icu->id,
            'response_notes' => 'Bed reserved',
        ])->assertOk()->assertJsonPath('status', 'accepted');

        $this->assertSame($before + 1, $icu->fresh()->current_utilization);
    }

    public function test_assistance_request_workflow(): void
    {
        Sanctum::actingAs($this->user('manager@riverside.test'));

        $create = $this->postJson('/api/assistance-requests', [
            'to_hospital_id' => $this->hospital('LMC')->id,
            'type' => 'equipment',
            'title' => 'Ventilator support',
            'description' => 'Need two ventilators overnight',
        ])->assertCreated();

        $id = $create->json('id');

        Sanctum::actingAs($this->user('admin@lakeside.test'));
        $this->patchJson('/api/assistance-requests/'.$id.'/status', [
            'status' => 'accepted',
            'response_notes' => 'We can send one unit',
        ])->assertOk()->assertJsonPath('status', 'accepted');
    }

    public function test_origin_hospital_cannot_accept_incoming_assistance_for_others(): void
    {
        $request = AssistanceRequest::query()->first();
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $this->patchJson('/api/assistance-requests/'.$request->id.'/status', [
            'status' => 'accepted',
        ])->assertForbidden();
    }

    public function test_ambulance_dispatch_and_trip_history(): void
    {
        Sanctum::actingAs($this->user('ambulance@riverside.test'));
        $ambulance = Ambulance::query()->where('vehicle_code', 'AMB-01')->first();

        $dispatch = $this->postJson('/api/ambulances/'.$ambulance->id.'/dispatch', [
            'origin' => 'Riverside ER',
            'destination' => 'Lakeside Medical Center',
            'destination_hospital_id' => $this->hospital('LMC')->id,
        ])->assertCreated();

        $this->assertSame('on_trip', $ambulance->fresh()->status);

        $this->postJson('/api/ambulances/'.$ambulance->id.'/dispatch', [
            'origin' => 'Riverside ER',
            'destination' => 'Elsewhere',
        ])->assertStatus(422);

        $tripId = $dispatch->json('id');
        $this->patchJson('/api/ambulance-trips/'.$tripId.'/status', ['status' => 'en_route'])->assertOk();
        $this->patchJson('/api/ambulance-trips/'.$tripId.'/status', ['status' => 'completed'])->assertOk();
        $this->assertSame('available', $ambulance->fresh()->status);

        $trips = $this->getJson('/api/ambulance-trips')->assertOk()->json();
        $this->assertNotEmpty($trips);
    }

    public function test_lakeside_cannot_dispatch_riverside_ambulance(): void
    {
        Sanctum::actingAs($this->user('admin@lakeside.test'));
        $ambulance = Ambulance::withoutGlobalScope('hospital')->where('vehicle_code', 'AMB-01')->first();

        $this->postJson('/api/ambulances/'.$ambulance->id.'/dispatch', [
            'origin' => 'Lakeside',
            'destination' => 'Riverside',
        ])->assertNotFound();
    }

    public function test_platform_admin_can_create_hospitals(): void
    {
        Sanctum::actingAs($this->user('platform@health.test'));

        $this->postJson('/api/hospitals', [
            'name' => 'Hilltop Clinic',
            'code' => 'HTC',
            'city' => 'Hilltop',
        ])->assertCreated()->assertJsonPath('code', 'HTC');
    }

    public function test_hospital_admin_cannot_create_hospitals(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $this->postJson('/api/hospitals', [
            'name' => 'Shadow Clinic',
            'code' => 'SHC',
        ])->assertForbidden();
    }

    public function test_dashboard_is_available_to_operational_roles(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $this->getJson('/api/reports')->assertOk()->assertJsonStructure(['facilities', 'referrals', 'assistance', 'ambulances', 'patients']);
    }

    public function test_role_workspace_and_navigation_are_scoped(): void
    {
        $nurse = $this->postJson('/api/auth/login', [
            'email' => 'nurse@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame('wards', $nurse->json('userData.homeRoute'));
        $titles = collect($nurse->json('navigation'))->pluck('title')->filter();
        $this->assertTrue($titles->contains('Wards'));
        $this->assertTrue($titles->contains('Beds'));
        $this->assertFalse($titles->contains('Laboratory'));
        $this->assertFalse($titles->contains('Users'));
        $this->assertFalse($titles->contains('Pharmacy'));

        $lab = $this->postJson('/api/auth/login', [
            'email' => 'lab@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame('laboratory', $lab->json('userData.homeRoute'));
        $labTitles = collect($lab->json('navigation'))->pluck('title')->filter();
        $this->assertTrue($labTitles->contains('Laboratory'));
        $this->assertFalse($labTitles->contains('Wards'));
        $this->assertFalse($labTitles->contains('Users'));
    }

    public function test_module_access_is_denied_outside_role_permissions(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));
        $this->getJson('/api/modules/wards')->assertOk();
        $this->getJson('/api/modules/laboratory')->assertForbidden();
        $this->getJson('/api/departments')->assertForbidden();
        $this->getJson('/api/reports')->assertForbidden();

        Sanctum::actingAs($this->user('lab@riverside.test'));
        $this->getJson('/api/modules/laboratory')->assertOk();
        $this->getJson('/api/modules/pharmacy')->assertForbidden();
        $this->getJson('/api/patients')->assertOk();
    }

    public function test_locum_user_has_different_roles_per_hospital(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'locum@health.test',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame('doctor', $login->json('userData.role'));
        $this->assertSame('opd', $login->json('userData.homeRoute'));
        $this->assertCount(2, $login->json('userData.memberships'));

        Sanctum::actingAs($this->user('locum@health.test'));
        $this->getJson('/api/modules/opd')->assertOk();
        $this->getJson('/api/modules/pharmacy')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();

        $switched = $this->postJson('/api/auth/switch-hospital', [
            'hospital_id' => $this->hospital('LMC')->id,
        ])->assertOk();

        $this->assertSame('nurse', $switched->json('userData.role'));
        $this->assertSame('wards', $switched->json('userData.homeRoute'));
        $titles = collect($switched->json('navigation'))->pluck('title')->filter();
        $this->assertTrue($titles->contains('Wards'));
        $this->assertFalse($titles->contains('OPD'));

        $locum = $this->user('locum@health.test')->fresh()->load('role.permissions');
        Sanctum::actingAs($locum);

        $this->getJson('/api/modules/wards')->assertOk();
        $this->getJson('/api/modules/opd')->assertForbidden();

        $riversideWard = Facility::withoutGlobalScope('hospital')
            ->where('hospital_id', $this->hospital('RGH')->id)
            ->where('code', 'WARD-A')
            ->first();
        $lakesideWard = Facility::withoutGlobalScope('hospital')
            ->where('hospital_id', $this->hospital('LMC')->id)
            ->where('code', 'WARD-A')
            ->first();
        $ids = collect($this->getJson('/api/modules/wards')->json('facilities'))->pluck('id');
        $this->assertTrue($ids->contains($lakesideWard->id));
        $this->assertFalse($ids->contains($riversideWard->id));
    }

    public function test_patients_are_tenant_isolated_and_reception_can_register(): void
    {
        Sanctum::actingAs($this->user('reception@riverside.test'));
        $created = $this->postJson('/api/patients', [
            'first_name' => 'Akua',
            'last_name' => 'Mensah',
            'sex' => 'female',
        ])->assertCreated();

        $this->assertStringStartsWith('RGH-', $created->json('mrn'));
        $ids = collect($this->getJson('/api/patients')->assertOk()->json())->pluck('id');
        $this->assertTrue($ids->contains($created->json('id')));

        Sanctum::actingAs($this->user('admin@lakeside.test'));
        $lakesideIds = collect($this->getJson('/api/patients')->assertOk()->json())->pluck('id');
        $this->assertFalse($lakesideIds->contains($created->json('id')));
    }

    public function test_bed_assignment_updates_utilization(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));
        $bed = Facility::query()->where('code', 'BED-8')->first();
        $patient = \App\Models\Patient::query()->first();
        $before = $bed->current_utilization;

        $assignment = $this->postJson('/api/bed-assignments', [
            'patient_id' => $patient->id,
            'facility_id' => $bed->id,
        ])->assertCreated();

        $this->assertSame($before + 1, $bed->fresh()->current_utilization);

        $this->patchJson('/api/bed-assignments/'.$assignment->json('id').'/discharge')
            ->assertOk()
            ->assertJsonPath('status', 'discharged');

        $this->assertSame($before, $bed->fresh()->current_utilization);
    }

    public function test_administrator_can_load_every_workspace_module(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        foreach (['opd', 'emergency', 'wards', 'beds', 'theatre', 'laboratory', 'imaging', 'pharmacy'] as $module) {
            $this->getJson('/api/modules/'.$module)
                ->assertOk()
                ->assertJsonStructure(['module', 'stats', 'facilities']);
        }

        $this->getJson('/api/patients')->assertOk();
        $this->getJson('/api/encounters?type=opd')->assertOk();
        $this->getJson('/api/encounters?type=emergency')->assertOk();
        $this->getJson('/api/facilities')->assertOk();
        $this->getJson('/api/facility-types')->assertOk();
        $this->getJson('/api/departments')->assertOk();
        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/users/directory')->assertOk();
        $this->getJson('/api/roles')->assertOk();
        $this->getJson('/api/referrals')->assertOk();
        $this->getJson('/api/assistance-requests')->assertOk();
        $this->getJson('/api/ambulances')->assertOk();
        $this->getJson('/api/ambulance-trips')->assertOk();
        $this->getJson('/api/invoices')->assertOk();
        $this->getJson('/api/reports')->assertOk();
    }

    public function test_nurse_navigation_excludes_admin_modules(): void
    {
        $payload = $this->postJson('/api/auth/login', [
            'email' => 'nurse@riverside.test',
            'password' => 'password',
        ])->assertOk()->json();

        $subjects = collect($payload['navigation'])->pluck('subject')->filter()->values();

        $this->assertTrue($subjects->contains('Ward'));
        $this->assertTrue($subjects->contains('Bed'));
        $this->assertFalse($subjects->contains('User'));
        $this->assertFalse($subjects->contains('Invoice'));
        $this->assertFalse($subjects->contains('Laboratory'));
    }

    public function test_hospital_admin_can_configure_departments_and_roles(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));
        $departments = $this->getJson('/api/departments')->assertOk()->json();
        $this->assertNotEmpty($departments);

        $role = $this->postJson('/api/roles', [
            'name' => 'Float Nurse',
            'workspace' => 'wards',
            'permission_ids' => [],
        ])->assertCreated();

        $this->assertSame($this->hospital('RGH')->id, $role->json('hospital_id'));

        Sanctum::actingAs($this->user('admin@lakeside.test'));
        $lakesideRoles = collect($this->getJson('/api/roles')->assertOk()->json())->pluck('id');
        $this->assertFalse($lakesideRoles->contains($role->json('id')));
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function hospital(string $code): Hospital
    {
        return Hospital::query()->where('code', $code)->firstOrFail();
    }
}
