<?php

namespace Tests\Feature;

use App\Models\ClinicalService;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicalJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_patient_chart_includes_connected_timeline(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $patient = Patient::query()->where('mrn', 'RGH-0001')->first();

        $chart = $this->getJson('/api/patients/'.$patient->id)->assertOk()->json();

        $this->assertSame('Kojo Appiah', $chart['full_name']);
        $this->assertNotEmpty($chart['timeline']);
        $types = collect($chart['timeline'])->pluck('type');
        $this->assertTrue($types->contains('registration'));
        $this->assertTrue($types->contains('encounter'));
        $this->assertTrue($types->contains('diagnosis'));
        $this->assertTrue($types->contains('order'));
        $this->assertTrue($types->contains('result'));
        $this->assertTrue($types->contains('prescription'));
        $this->assertTrue($types->contains('dispense'));
        $this->assertTrue($types->contains('billing'));
    }

    public function test_opd_to_pharmacy_and_billing_journey(): void
    {
        Sanctum::actingAs($this->user('reception@riverside.test'));
        $patient = $this->postJson('/api/patients', [
            'first_name' => 'Akosua',
            'last_name' => 'Owusu',
            'sex' => 'female',
            'allergies' => [['allergen' => 'Ibuprofen', 'severity' => 'mild']],
        ])->assertCreated()->json();

        $visit = $this->postJson('/api/encounters', [
            'patient_id' => $patient['id'],
            'type' => 'opd',
            'chief_complaint' => 'Fever and cough',
        ])->assertCreated()->json();

        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $this->patchJson('/api/encounters/'.$visit['id'], ['status' => 'in_progress'])->assertOk();
        $this->postJson('/api/encounters/'.$visit['id'].'/vitals', [
            'temperature' => 38.6,
            'pulse' => 98,
            'spo2' => 96,
        ])->assertCreated();
        $this->postJson('/api/encounters/'.$visit['id'].'/diagnoses', [
            'name' => 'Acute bronchitis',
            'kind' => 'primary',
        ])->assertCreated();
        $this->postJson('/api/encounters/'.$visit['id'].'/notes', [
            'body' => 'Productive cough for three days. Order FBC and prescribe amoxicillin.',
        ])->assertCreated();

        $fbc = ClinicalService::query()->where('code', 'LAB-FBC')->first();
        $order = $this->postJson('/api/service-orders', [
            'module_key' => 'laboratory',
            'encounter_id' => $visit['id'],
            'service_id' => $fbc->id,
        ])->assertCreated()->json();
        $this->assertSame('requested', $order['status']);

        Sanctum::actingAs($this->user('lab@riverside.test'));
        $this->patchJson('/api/service-orders/'.$order['id'], ['status' => 'collected'])->assertOk();
        $this->patchJson('/api/service-orders/'.$order['id'], [
            'status' => 'completed',
            'result' => 'WBC 11.2 · viral picture',
        ])->assertOk();

        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $med = Medication::query()->where('sku', 'AMX-500')->first();
        $rx = $this->postJson('/api/prescriptions', [
            'encounter_id' => $visit['id'],
            'notes' => 'Five-day course',
            'items' => [[
                'medication_id' => $med->id,
                'dose' => '500mg',
                'frequency' => 'three times daily',
                'duration' => '5 days',
                'quantity' => 15,
            ]],
        ])->assertCreated()->json();

        Sanctum::actingAs($this->user('pharmacy@riverside.test'));
        $this->patchJson('/api/prescriptions/'.$rx['id'].'/status', ['status' => 'verified'])->assertOk();
        $this->patchJson('/api/prescriptions/'.$rx['id'].'/status', ['status' => 'dispensed'])
            ->assertOk()
            ->assertJsonPath('status', 'dispensed');

        Sanctum::actingAs($this->user('billing@riverside.test'));
        $invoice = $this->postJson('/api/invoices', [
            'encounter_id' => $visit['id'],
        ])->assertCreated()->json();
        $this->assertGreaterThan(0, $invoice['total']);
        $this->postJson('/api/invoices/'.$invoice['id'].'/payments', [
            'amount' => $invoice['total'],
            'method' => 'cash',
        ])->assertOk()->assertJsonPath('status', 'paid');

        $chart = $this->actingAsDoctorChart($patient['id']);
        $types = collect($chart['timeline'])->pluck('type');
        $this->assertTrue($types->contains('vitals'));
        $this->assertTrue($types->contains('dispense'));
        $this->assertTrue($types->contains('payment'));
    }

    public function test_admission_ward_bed_and_discharge_journey(): void
    {
        Sanctum::actingAs($this->user('emergency@riverside.test'));
        $patient = Patient::query()->where('mrn', 'RGH-0001')->first();
        $er = $this->postJson('/api/encounters', [
            'patient_id' => $patient->id,
            'type' => 'emergency',
            'chief_complaint' => 'Collapse',
        ])->assertCreated()->json();

        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $admission = $this->postJson('/api/encounters/'.$er['id'].'/admit', [
            'notes' => 'For observation',
        ])->assertCreated()->json();
        $this->assertSame('admission', $admission['type']);

        Sanctum::actingAs($this->user('nurse@riverside.test'));
        $bed = Facility::query()->where('code', 'BED-8')->first();
        $assignment = $this->postJson('/api/bed-assignments', [
            'patient_id' => $patient->id,
            'facility_id' => $bed->id,
            'encounter_id' => $admission['id'],
        ])->assertCreated()->json();
        $this->assertSame($admission['id'], $assignment['encounter_id']);

        $this->postJson('/api/encounters/'.$admission['id'].'/vitals', [
            'pulse' => 88,
            'systolic' => 130,
            'diastolic' => 80,
        ])->assertCreated();
        $this->postJson('/api/encounters/'.$admission['id'].'/notes', [
            'type' => 'nursing',
            'body' => 'Settled on Ward A. Pain controlled.',
        ])->assertCreated();

        $this->postJson('/api/encounters/'.$admission['id'].'/discharge')->assertOk();
        $this->assertSame('discharged', \App\Models\BedAssignment::query()->find($assignment['id'])->status);
        $this->assertSame('cleaning', $bed->fresh()->status);
    }

    public function test_referral_acceptance_creates_receiving_encounter(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $patient = Patient::query()->where('mrn', 'RGH-0003')->first();
        $encounter = Encounter::query()->where('patient_id', $patient->id)->where('type', 'emergency')->first();
        $wardTypeId = Facility::query()->where('code', 'WARD-A')->first()->facility_type_id;

        $create = $this->postJson('/api/referrals', [
            'to_hospital_id' => $this->hospital('LMC')->id,
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'reason' => 'ICU care',
            'required_facility_type_id' => $wardTypeId,
            'required_capacity' => 1,
        ])->assertCreated();

        Sanctum::actingAs($this->user('doctor@lakeside.test'));
        $icu = Facility::withoutGlobalScope('hospital')
            ->where('hospital_id', $this->hospital('LMC')->id)
            ->where('code', 'ICU-1')
            ->first();

        $accepted = $this->patchJson('/api/referrals/'.$create->json('id').'/status', [
            'status' => 'accepted',
            'destination_facility_id' => $icu->id,
        ])->assertOk()->json();

        $this->assertNotNull($accepted['receiving_encounter_id']);
        $this->assertNotNull($accepted['receiving_patient_id']);

        $receiving = Encounter::withoutGlobalScope('hospital')->find($accepted['receiving_encounter_id']);
        $this->assertSame('referral', $receiving->type);
        $this->assertSame($this->hospital('LMC')->id, $receiving->hospital_id);
    }

    public function test_referral_ambulance_dispatch_and_handover(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $patient = Patient::query()->where('mrn', 'RGH-0003')->first();
        $encounter = Encounter::query()->where('patient_id', $patient->id)->where('type', 'emergency')->first();
        $wardTypeId = Facility::query()->where('code', 'WARD-A')->first()->facility_type_id;

        $referral = $this->postJson('/api/referrals', [
            'to_hospital_id' => $this->hospital('LMC')->id,
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'reason' => 'ICU transfer by ambulance',
            'required_facility_type_id' => $wardTypeId,
            'required_capacity' => 1,
        ])->assertCreated()->json();

        Sanctum::actingAs($this->user('doctor@lakeside.test'));
        $icu = Facility::withoutGlobalScope('hospital')
            ->where('hospital_id', $this->hospital('LMC')->id)
            ->where('code', 'ICU-1')
            ->first();
        $this->patchJson('/api/referrals/'.$referral['id'].'/status', [
            'status' => 'accepted',
            'destination_facility_id' => $icu->id,
        ])->assertOk();

        Sanctum::actingAs($this->user('ambulance@riverside.test'));
        $ambulance = \App\Models\Ambulance::query()->where('vehicle_code', 'AMB-01')->first();
        $trip = $this->postJson('/api/ambulances/'.$ambulance->id.'/dispatch', [
            'origin' => 'Riverside ER',
            'pickup_location' => 'Emergency Bay',
            'destination' => 'Lakeside ICU',
            'destination_hospital_id' => $this->hospital('LMC')->id,
            'referral_id' => $referral['id'],
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
        ])->assertCreated()->json();

        $this->assertSame($patient->id, $trip['patient_id']);
        $this->assertSame($encounter->id, $trip['encounter_id']);
        $this->assertSame('in_transit', \App\Models\Referral::query()->find($referral['id'])->status);

        $this->patchJson('/api/ambulance-trips/'.$trip['id'].'/status', [
            'status' => 'en_route',
        ])->assertOk();
        $this->patchJson('/api/ambulance-trips/'.$trip['id'].'/status', [
            'status' => 'completed',
            'handover_notes' => 'Patient handed to Lakeside ER with chart copy.',
        ])->assertOk();

        $this->assertSame('completed', \App\Models\Referral::query()->find($referral['id'])->status);
        $this->assertSame('available', $ambulance->fresh()->status);
    }

    public function test_workspace_shows_role_specific_queues(): void
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $doctor = $this->getJson('/api/workspace')->assertOk()->json();
        $this->assertNotEmpty($doctor['my_encounters']);

        Sanctum::actingAs($this->user('lab@riverside.test'));
        $lab = $this->getJson('/api/workspace')->assertOk()->json();
        $this->assertIsArray($lab['lab_orders']);

        Sanctum::actingAs($this->user('pharmacy@riverside.test'));
        $pharmacy = $this->getJson('/api/workspace')->assertOk()->json();
        $this->assertIsArray($pharmacy['prescriptions']);

        Sanctum::actingAs($this->user('nurse@riverside.test'));
        $nurse = $this->getJson('/api/workspace')->assertOk()->json();
        $this->assertNotEmpty($nurse['ward_patients']);
    }

    private function actingAsDoctorChart(string $patientId): array
    {
        Sanctum::actingAs($this->user('doctor@riverside.test'));

        return $this->getJson('/api/patients/'.$patientId)->assertOk()->json();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function hospital(string $code)
    {
        return \App\Models\Hospital::query()->where('code', $code)->firstOrFail();
    }
}
