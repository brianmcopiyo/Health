<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_update_own_profile_fields(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));
        $department = Department::query()->where('module_key', 'emergency')->firstOrFail();

        $this->putJson('/api/auth/profile', [
            'name' => 'Nurse Amina',
            'phone' => '0244000111',
            'job_title' => 'Charge nurse',
            'specialty' => 'Critical care',
            'license_number' => 'NMC-441',
            'department_id' => $department->id,
            'availability' => 'busy',
            'preferences' => ['invoices' => false, 'referrals' => true],
            'role_id' => Role::query()->where('slug', 'administrator')->value('id'),
            'hospital_id' => Hospital::query()->where('code', 'LMC')->value('id'),
        ])->assertOk()
            ->assertJsonPath('userData.fullName', 'Nurse Amina')
            ->assertJsonPath('userData.jobTitle', 'Charge nurse')
            ->assertJsonPath('userData.availability', 'busy')
            ->assertJsonPath('userData.role', 'nurse')
            ->assertJsonPath('userData.hospitalName', 'Riverside General Hospital')
            ->assertJsonPath('userData.preferences.invoices', false)
            ->assertJsonPath('profile.license_number', 'NMC-441');

        $nurse = $this->user('nurse@riverside.test')->fresh();
        $this->assertSame($department->id, $nurse->department_id);
        $this->assertSame('nurse', $nurse->role->slug);
    }

    public function test_department_must_belong_to_current_hospital(): void
    {
        Sanctum::actingAs($this->user('admin@riverside.test'));
        $foreign = Department::withoutGlobalScopes()
            ->where('hospital_id', Hospital::query()->where('code', 'LMC')->value('id'))
            ->firstOrFail();

        $this->putJson('/api/auth/profile', [
            'department_id' => $foreign->id,
        ])->assertStatus(422);
    }

    public function test_nurse_cannot_update_other_users(): void
    {
        Sanctum::actingAs($this->user('nurse@riverside.test'));
        $admin = $this->user('admin@riverside.test');

        $this->putJson('/api/users/'.$admin->id, [
            'name' => 'Hijacked',
            'email' => $admin->email,
            'role_id' => $admin->role_id,
        ])->assertForbidden();
    }

    public function test_password_change_requires_current_password_and_revokes_other_sessions(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'doctor@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'doctor@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $doctor = $this->user('doctor@riverside.test');
        $this->assertGreaterThanOrEqual(2, $doctor->tokens()->count());

        $this->withToken($login->json('accessToken'))
            ->postJson('/api/auth/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-pass-99',
                'password_confirmation' => 'new-pass-99',
            ])->assertStatus(422);

        $this->withToken($login->json('accessToken'))
            ->postJson('/api/auth/password', [
                'current_password' => 'password',
                'password' => 'new-pass-99',
                'password_confirmation' => 'new-pass-99',
            ])->assertOk();

        $this->assertTrue(Hash::check('new-pass-99', $doctor->fresh()->password));
        $this->assertSame(1, $doctor->tokens()->count());
        $this->assertNotNull($doctor->tokens()->where('id', $doctor->tokens()->first()->id)->first());
    }

    public function test_sessions_are_limited_to_the_authenticated_user(): void
    {
        $adminLogin = $this->postJson('/api/auth/login', [
            'email' => 'admin@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $nurseLogin = $this->postJson('/api/auth/login', [
            'email' => 'nurse@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $nurseToken = $this->user('nurse@riverside.test')->tokens()->first();

        $this->asToken($adminLogin->json('accessToken'))
            ->getJson('/api/auth/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_current', true);

        $this->asToken($adminLogin->json('accessToken'))
            ->deleteJson('/api/auth/sessions/'.$nurseToken->id)
            ->assertForbidden();

        $this->asToken($nurseLogin->json('accessToken'))
            ->deleteJson('/api/auth/sessions/'.$nurseToken->id)
            ->assertOk()
            ->assertJsonPath('signed_out', true);
    }

    public function test_activity_is_limited_to_own_tenant_events(): void
    {
        $riverside = $this->user('admin@riverside.test');
        $lakeside = $this->user('admin@lakeside.test');
        $locum = $this->user('locum@health.test');

        $foreign = AuditEvent::query()->create([
            'hospital_id' => $lakeside->hospital_id,
            'actor_id' => $riverside->id,
            'auditable_type' => User::class,
            'auditable_id' => $riverside->id,
            'action' => 'updated',
        ]);
        $own = AuditEvent::query()->create([
            'hospital_id' => $riverside->hospital_id,
            'actor_id' => $riverside->id,
            'auditable_type' => User::class,
            'auditable_id' => $riverside->id,
            'action' => 'updated',
        ]);
        $lakesideOwn = AuditEvent::query()->create([
            'hospital_id' => $lakeside->hospital_id,
            'actor_id' => $lakeside->id,
            'auditable_type' => User::class,
            'auditable_id' => $lakeside->id,
            'action' => 'updated',
        ]);
        $locumRiverside = AuditEvent::query()->create([
            'hospital_id' => $riverside->hospital_id,
            'actor_id' => $locum->id,
            'auditable_type' => User::class,
            'auditable_id' => $locum->id,
            'action' => 'updated',
        ]);
        $locumLakeside = AuditEvent::query()->create([
            'hospital_id' => $lakeside->hospital_id,
            'actor_id' => $locum->id,
            'auditable_type' => User::class,
            'auditable_id' => $locum->id,
            'action' => 'updated',
        ]);

        Sanctum::actingAs($riverside);
        $riversideIds = collect($this->getJson('/api/auth/activity')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($riversideIds->contains($own->id));
        $this->assertFalse($riversideIds->contains($foreign->id));
        $this->assertFalse($riversideIds->contains($lakesideOwn->id));

        Sanctum::actingAs($lakeside);
        $lakesideIds = collect($this->getJson('/api/auth/activity')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($lakesideIds->contains($lakesideOwn->id));
        $this->assertFalse($lakesideIds->contains($own->id));

        Sanctum::actingAs($locum);
        $locumIds = collect($this->getJson('/api/auth/activity')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($locumIds->contains($locumRiverside->id));
        $this->assertTrue($locumIds->contains($locumLakeside->id));
        $this->assertFalse($locumIds->contains($own->id));
    }

    public function test_avatar_can_be_uploaded_and_removed(): void
    {
        Storage::fake('avatars');
        Sanctum::actingAs($this->user('admin@riverside.test'));

        $this->get('/api/auth/avatar')->assertNotFound();

        $this->post('/api/auth/avatar', [
            'file' => UploadedFile::fake()->image('portrait.jpg', 120, 120),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('userData.hasAvatar', true);

        $this->get('/api/auth/avatar')->assertOk();
        Storage::disk('avatars')->assertExists($this->user('admin@riverside.test')->fresh()->avatar_path);

        $this->deleteJson('/api/auth/avatar')
            ->assertOk()
            ->assertJsonPath('userData.hasAvatar', false);
    }

    public function test_login_keeps_existing_sessions(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'admin@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'admin@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame(2, $this->user('admin@riverside.test')->tokens()->count());
    }

    public function test_email_cannot_be_changed(): void
    {
        Sanctum::actingAs($this->user('lab@riverside.test'));

        $this->postJson('/api/auth/email', [
            'email' => 'lab.updated@riverside.test',
            'current_password' => 'password',
        ])->assertStatus(405);

        $this->putJson('/api/auth/profile', [
            'email' => 'lab.updated@riverside.test',
        ])->assertStatus(422);

        $this->assertSame('lab@riverside.test', $this->user('lab@riverside.test')->email);

        Sanctum::actingAs($this->user('admin@riverside.test'));
        $lab = $this->user('lab@riverside.test');

        $this->putJson('/api/users/'.$lab->id, [
            'name' => $lab->name,
            'email' => 'lab.updated@riverside.test',
        ])->assertStatus(422);

        $this->assertSame('lab@riverside.test', $lab->fresh()->email);
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($token);
    }
}
