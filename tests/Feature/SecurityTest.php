<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Encounter;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Support\FieldCrypt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/patients')->assertUnauthorized();
    }

    public function test_login_does_not_expose_password_hashes(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@riverside.test',
            'password' => 'password',
        ])->assertOk();

        $this->assertArrayNotHasKey('password', $response->json());
        $this->assertArrayNotHasKey('password', $response->json('userData'));
        $this->assertNotEmpty($response->json('accessToken'));
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'lockout@riverside.test',
                'password' => 'wrong',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'lockout@riverside.test',
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_cross_tenant_patient_access_is_hidden(): void
    {
        Sanctum::actingAs($this->user('doctor@lakeside.test'));
        $patient = Patient::withoutGlobalScopes()->where('mrn', 'RGH-0001')->firstOrFail();

        $this->getJson('/api/patients/'.$patient->id)->assertNotFound();
        $this->getJson('/api/encounters/'.Encounter::withoutGlobalScopes()->where('patient_id', $patient->id)->value('id'))
            ->assertNotFound();
    }

    public function test_lab_cannot_update_unrelated_encounters_or_charts(): void
    {
        $ama = Patient::query()->where('mrn', 'RGH-0002')->firstOrFail();
        $kojo = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();
        $opd = Encounter::query()->where('patient_id', $kojo->id)->where('type', 'opd')->firstOrFail();

        Sanctum::actingAs($this->user('lab@riverside.test'));

        $this->getJson('/api/patients/'.$ama->id)->assertForbidden();
        $this->patchJson('/api/encounters/'.$opd->id, ['notes' => 'unauthorized'])->assertForbidden();
        $this->getJson('/api/patients/'.$ama->id.'/export')->assertForbidden();
    }

    public function test_platform_admin_cannot_list_clinical_records(): void
    {
        Sanctum::actingAs($this->user('platform@health.test'));

        $this->assertSame([], $this->getJson('/api/patients')->assertOk()->json('data'));
        $this->getJson('/api/patients/'.Patient::withoutGlobalScopes()->where('mrn', 'RGH-0001')->value('id'))
            ->assertNotFound();
    }

    public function test_sensitive_fields_are_encrypted_at_rest_and_searchable(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();

        $this->putJson('/api/patients/'.$patient->id, [
            'national_id' => 'GHA-998877',
            'notes' => 'Confidential clinical summary',
        ])->assertOk();

        $row = DB::table('patients')->where('id', $patient->id)->first();
        $this->assertTrue(str_starts_with((string) $row->phone, FieldCrypt::PREFIX));
        $this->assertTrue(str_starts_with((string) $row->address, FieldCrypt::PREFIX));
        $this->assertTrue(str_starts_with((string) $row->national_id, FieldCrypt::PREFIX));
        $this->assertTrue(str_starts_with((string) $row->notes, FieldCrypt::PREFIX));
        $this->assertStringNotContainsString('GHA-998877', (string) $row->national_id);
        $this->assertStringNotContainsString('Confidential', (string) $row->notes);

        $chart = $this->getJson('/api/patients/'.$patient->id)->assertOk()->json();
        $this->assertSame('555-1001', $chart['phone']);
        $this->assertSame('GHA-998877', $chart['national_id']);
        $this->assertSame('Confidential clinical summary', $chart['notes']);

        $matches = collect($this->getJson('/api/patients?q=555-1001')->assertOk()->json('data'))->pluck('mrn');
        $this->assertTrue($matches->contains('RGH-0001'));

        $nid = collect($this->getJson('/api/patients?q=GHA-998877')->assertOk()->json('data'))->pluck('mrn');
        $this->assertTrue($nid->contains('RGH-0001'));
    }

    public function test_viewing_a_chart_is_audited_without_copying_clinical_payload(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $patient = Patient::query()->where('mrn', 'RGH-0001')->firstOrFail();

        $this->getJson('/api/patients/'.$patient->id)->assertOk();

        $event = AuditEvent::query()
            ->where('auditable_id', $patient->id)
            ->where('action', 'viewed')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($this->user('doctor@riverside.test')->id, $event->actor_id);
        $this->assertSame($this->user('doctor@riverside.test')->hospital_id, $event->hospital_id);
        $this->assertNotNull($event->ip_address);
        $this->assertSame('RGH-0001', $event->payload['mrn'] ?? null);
        $this->assertArrayNotHasKey('notes', $event->payload ?? []);
        $this->assertArrayNotHasKey('national_id', $event->payload ?? []);
    }

    public function test_mass_assignment_cannot_change_tenant(): void
    {
        Sanctum::actingAs($this->user('reception@riverside.test'));
        $foreign = Hospital::query()->where('name', 'Lakeside Medical Center')->firstOrFail();

        $created = $this->postJson('/api/patients', [
            'first_name' => 'Kwame',
            'last_name' => 'Mensah',
            'hospital_id' => $foreign->id,
        ])->assertCreated()->json();

        $this->assertSame($this->user('reception@riverside.test')->hospital_id, Patient::query()->find($created['id'])->hospital_id);
    }

    public function test_clinical_files_are_private_encrypted_and_authorized(): void
    {
        Storage::fake('clinical');
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $patient = Patient::query()->where('mrn', 'RGH-0002')->firstOrFail();

        $upload = $this->post('/api/patients/'.$patient->id.'/documents', [
            'file' => UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n%test"),
        ], ['Accept' => 'application/json'])->assertCreated()->json();

        $files = Storage::disk('clinical')->allFiles();
        $this->assertNotEmpty($files);
        $this->assertTrue(str_starts_with(Storage::disk('clinical')->get($files[0]), FieldCrypt::PREFIX));

        $this->getJson('/api/documents/'.$upload['id'].'/download')->assertOk();

        Sanctum::actingAs($this->user('lab@riverside.test'));
        $this->getJson('/api/documents/'.$upload['id'].'/download')->assertForbidden();

        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $this->post('/api/patients/'.$patient->id.'/documents', [
            'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_security_headers_are_present(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_encryption_key_rotation_keeps_historical_records_readable(): void
    {
        $key1 = 'base64:'.base64_encode(str_repeat('1', 32));
        $key2 = 'base64:'.base64_encode(str_repeat('2', 32));

        config([
            'hms.encryption.key' => $key1,
            'hms.encryption.key_id' => 'k1',
            'hms.encryption.previous_keys' => '',
        ]);

        $cipher = FieldCrypt::encrypt('historical-note');
        $this->assertTrue(str_contains($cipher, ':k1:'));

        config([
            'hms.encryption.key' => $key2,
            'hms.encryption.key_id' => 'k2',
            'hms.encryption.previous_keys' => 'k1='.$key1,
        ]);

        $this->assertSame('historical-note', FieldCrypt::decrypt($cipher));
        $rotated = FieldCrypt::reencrypt($cipher);
        $this->assertTrue(str_contains($rotated, ':k2:'));
        $this->assertSame('historical-note', FieldCrypt::decrypt($rotated));
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
