<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccountStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_records_last_login_and_returns_status(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame('active', $response->json('userData.status'));
        $this->assertNotNull($this->user('admin@riverside.test')->fresh()->last_login_at);
    }

    public function test_inactive_and_suspended_users_cannot_login(): void
    {
        $nurse = $this->user('nurse@riverside.test');
        $nurse->applyAccountStatus(AccountStatus::INACTIVE);

        $this->postJson('/api/auth/login', [
            'email' => 'nurse@riverside.test',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonPath('errors.email.0', 'This account is inactive');

        $nurse->applyAccountStatus(AccountStatus::SUSPENDED);

        $this->postJson('/api/auth/login', [
            'email' => 'nurse@riverside.test',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonPath('errors.email.0', 'This account is suspended');
    }

    public function test_inactive_user_token_is_rejected(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'nurse@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $this->user('nurse@riverside.test')->applyAccountStatus(AccountStatus::INACTIVE);

        $this->withToken($login->json('accessToken'))
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_administrator_can_create_update_and_filter_users(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));
        $role = Role::query()->where('slug', 'nurse')->where('hospital_id', $this->user('admin@riverside.test')->hospital_id)->firstOrFail();

        $created = $this->postJson('/api/users', [
            'name' => 'Float Nurse',
            'email' => 'float@riverside.test',
            'password' => 'password1',
            'role_id' => $role->id,
            'phone' => '0240001111',
            'status' => AccountStatus::ACTIVE,
        ])->assertCreated();

        $id = $created->json('id');
        $this->assertSame('active', $created->json('status'));

        $this->putJson('/api/users/'.$id, [
            'name' => 'Float Nurse Updated',
            'phone' => '0240002222',
            'status' => AccountStatus::INACTIVE,
        ])->assertOk()->assertJsonPath('name', 'Float Nurse Updated');

        $this->getJson('/api/users?status=inactive&role_id='.$role->id.'&q=Float&sort=name')
            ->assertOk()
            ->assertJsonFragment(['email' => 'float@riverside.test']);

        $this->getJson('/api/users/'.$id)
            ->assertOk()
            ->assertJsonPath('status', 'inactive')
            ->assertJsonPath('phone', '0240002222');
    }

    public function test_last_hospital_administrator_cannot_be_removed_or_deactivated(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));
        $admin = $this->user('admin@riverside.test');

        $this->deleteJson('/api/users/'.$admin->id)->assertStatus(422);
        $this->putJson('/api/users/'.$admin->id, ['status' => AccountStatus::INACTIVE])->assertStatus(422);

        $nurse = $this->user('nurse@riverside.test');
        $this->putJson('/api/users/'.$nurse->id, [
            'role_id' => $admin->role_id,
        ])->assertOk();

        Sanctum::actingAs($nurse->fresh());
        $this->deleteJson('/api/users/'.$admin->id)->assertOk();
    }

    public function test_last_platform_admin_cannot_lose_system_access(): void
    {
        Sanctum::actingAs($this->user('platform@health.test'));
        $platform = $this->user('platform@health.test');

        $this->deleteJson('/api/users/'.$platform->id)->assertStatus(422);
        $this->putJson('/api/users/'.$platform->id, ['status' => AccountStatus::SUSPENDED])->assertStatus(422);
    }

    public function test_role_permissions_can_be_assigned_and_system_role_is_protected(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $permission = \App\Models\Permission::query()->where('action', 'read')->where('subject', 'User')->firstOrFail();

        $role = $this->postJson('/api/roles', [
            'name' => 'Access Clerk',
            'workspace' => 'admin',
            'permission_ids' => [$permission->id],
        ])->assertCreated();

        $this->assertTrue(collect($role->json('permissions'))->contains('id', $permission->id));

        $system = Role::query()->where('slug', 'administrator')->where('hospital_id', $this->user('admin@riverside.test')->hospital_id)->firstOrFail();
        $this->deleteJson('/api/roles/'.$system->id)->assertStatus(422);

        $this->deleteJson('/api/roles/'.$role->json('id'))->assertOk();
    }

    public function test_health_permissions_include_granular_actions_and_not_agrovet_subjects(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $permissions = collect($this->getJson('/api/permissions')->assertOk()->json());
        $actions = $permissions->where('subject', 'User')->pluck('action')->all();

        foreach (['read', 'create', 'update', 'delete', 'approve', 'export', 'manage'] as $action) {
            $this->assertContains($action, $actions);
        }

        $this->assertFalse($permissions->contains('subject', 'Product'));
        $this->assertTrue($permissions->contains('subject', 'Patient'));
    }

    public function test_nurse_cannot_manage_users(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [
            'name' => 'Blocked',
            'email' => 'blocked@riverside.test',
            'password' => 'password1',
            'role_id' => $this->user('nurse@riverside.test')->role_id,
        ])->assertForbidden();
    }

    public function test_bulk_status_skips_self_and_updates_others(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));
        $admin = $this->user('admin@riverside.test');
        $nurse = $this->user('nurse@riverside.test');

        $this->postJson('/api/users/bulk-status', [
            'user_ids' => [$admin->id, $nurse->id],
            'status' => AccountStatus::INACTIVE,
        ])->assertOk()->assertJsonPath('updated', 1)->assertJsonPath('skipped', 1);

        $this->assertSame(AccountStatus::ACTIVE, $admin->fresh()->status);
        $this->assertSame(AccountStatus::INACTIVE, $nurse->fresh()->status);
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
