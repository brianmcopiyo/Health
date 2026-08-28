<?php

namespace Database\Seeders;

use App\Models\BedAssignment;
use App\Models\CarePlan;
use App\Models\ClinicalNote;
use App\Models\ClinicalService;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\Dispensing;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientCondition;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Referral;
use App\Models\ServiceOrder;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Models\Vital;
use App\Support\ChargeLedger;
use App\Support\HospitalSequence;
use Illuminate\Database\Seeder;

class ClinicalJourneySeeder extends Seeder
{
    public function run(): void
    {
        $riverside = Hospital::query()->where('code', 'RGH')->first();
        $lakeside = Hospital::query()->where('code', 'LMC')->first();
        $doctor = User::query()->where('email', 'doctor@riverside.test')->first();
        $nurse = User::query()->where('email', 'nurse@riverside.test')->first();
        $lab = User::query()->where('email', 'lab@riverside.test')->first();
        $pharmacy = User::query()->where('email', 'pharmacy@riverside.test')->first();
        $imaging = User::query()->where('email', 'imaging@riverside.test')->first();
        $emergency = User::query()->where('email', 'emergency@riverside.test')->first();
        $billing = User::query()->where('email', 'billing@riverside.test')->first();

        $opd = Department::query()->where('hospital_id', $riverside->id)->where('slug', 'opd')->first();
        $wards = Department::query()->where('hospital_id', $riverside->id)->where('slug', 'wards')->first();
        $erDept = Department::query()->where('hospital_id', $riverside->id)->where('slug', 'emergency')->first();
        $ward = Facility::query()->where('hospital_id', $riverside->id)->where('code', 'WARD-A')->first();
        $bed1 = Facility::query()->where('hospital_id', $riverside->id)->where('code', 'BED-1')->first();
        $consult = Facility::query()->where('hospital_id', $riverside->id)->where('code', 'CON-3')->first();
        $erBay = Facility::query()->where('hospital_id', $riverside->id)->where('code', 'ER-1')->first();

        $doctor->update(['department_id' => $opd?->id, 'specialty' => 'Cardiology', 'license_number' => 'MDC-2041']);
        $nurse->update(['department_id' => $wards?->id, 'specialty' => 'Inpatient nursing']);
        $emergency->update(['department_id' => $erDept?->id, 'specialty' => 'Emergency medicine']);

        StaffAssignment::query()->create([
            'hospital_id' => $riverside->id,
            'user_id' => $nurse->id,
            'department_id' => $wards?->id,
            'facility_id' => $ward?->id,
            'assignment_role' => 'charge-nurse',
            'shift' => 'day',
            'status' => 'active',
            'starts_at' => now()->startOfDay(),
        ]);

        $kojo = Patient::query()->create([
            'hospital_id' => $riverside->id,
            'mrn' => 'RGH-0001',
            'first_name' => 'Kojo',
            'last_name' => 'Appiah',
            'sex' => 'male',
            'date_of_birth' => '1978-03-12',
            'phone' => '555-1001',
            'blood_group' => 'O+',
            'address' => '14 Market Street, Riverside',
            'next_of_kin_name' => 'Efua Appiah',
            'next_of_kin_phone' => '555-1099',
            'next_of_kin_relation' => 'spouse',
            'status' => 'active',
        ]);
        PatientAllergy::query()->create([
            'hospital_id' => $riverside->id,
            'patient_id' => $kojo->id,
            'allergen' => 'Penicillin',
            'reaction' => 'Rash',
            'severity' => 'moderate',
            'noted_by' => $doctor->id,
            'noted_at' => now()->subDays(2),
        ]);
        PatientCondition::query()->create([
            'hospital_id' => $riverside->id,
            'patient_id' => $kojo->id,
            'name' => 'Hypertension',
            'status' => 'active',
            'diagnosed_on' => '2021-06-01',
            'recorded_by' => $doctor->id,
        ]);

        $ama = Patient::query()->create([
            'hospital_id' => $riverside->id,
            'mrn' => 'RGH-0002',
            'first_name' => 'Ama',
            'last_name' => 'Serwaa',
            'sex' => 'female',
            'date_of_birth' => '1992-11-04',
            'phone' => '555-1002',
            'blood_group' => 'A+',
            'next_of_kin_name' => 'Kofi Serwaa',
            'next_of_kin_phone' => '555-1088',
            'next_of_kin_relation' => 'brother',
            'status' => 'admitted',
        ]);

        $yaw = Patient::query()->create([
            'hospital_id' => $riverside->id,
            'mrn' => 'RGH-0003',
            'first_name' => 'Yaw',
            'last_name' => 'Nkrumah',
            'sex' => 'male',
            'date_of_birth' => '1985-07-21',
            'phone' => '555-1003',
            'status' => 'transferred',
        ]);

        $this->seedOpdJourney($riverside, $kojo, $doctor, $lab, $imaging, $pharmacy, $billing, $opd, $consult);
        $this->seedInpatientJourney($riverside, $ama, $emergency, $nurse, $doctor, $erDept, $wards, $erBay, $ward, $bed1);
        $this->seedReferralJourney($riverside, $lakeside, $yaw, $emergency, $doctor, $erDept, $erBay);

        HospitalSequence::sync($riverside);
        HospitalSequence::sync($lakeside);
    }

    private function seedOpdJourney($hospital, Patient $patient, $doctor, $lab, $imaging, $pharmacy, $billing, $opd, $consult): void
    {
        $encounter = Encounter::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'department_id' => $opd?->id,
            'clinician_id' => $doctor->id,
            'facility_id' => $consult?->id,
            'type' => 'opd',
            'status' => 'completed',
            'chief_complaint' => 'Chest pain on exertion',
            'started_at' => now()->subHours(4),
            'completed_at' => now()->subHour(),
        ]);
        $encounter->addClinician($doctor->id);
        ChargeLedger::forService($encounter, 'OPD-CON', 'encounter', $encounter->id);

        Vital::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'recorded_by' => $doctor->id,
            'recorded_at' => now()->subHours(4),
            'temperature' => 36.8,
            'pulse' => 92,
            'respiration' => 20,
            'systolic' => 148,
            'diastolic' => 94,
            'spo2' => 97,
        ]);

        ClinicalNote::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'author_id' => $doctor->id,
            'type' => 'progress',
            'body' => 'Exertional chest pain with known hypertension. ECG pending imaging. Plan: FBC, chest X-ray, start antiplatelet.',
            'recorded_at' => now()->subHours(3),
        ]);

        Diagnosis::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'code' => 'I20.9',
            'name' => 'Angina pectoris',
            'kind' => 'primary',
            'recorded_by' => $doctor->id,
            'recorded_at' => now()->subHours(3),
        ]);

        CarePlan::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'title' => 'Cardiac workup and antiplatelet therapy',
            'body' => 'Outpatient cardiac review in 7 days. Continue blood pressure control.',
            'status' => 'active',
            'created_by' => $doctor->id,
        ]);

        $fbc = ClinicalService::query()->where('hospital_id', $hospital->id)->where('code', 'LAB-FBC')->first();
        $cxr = ClinicalService::query()->where('hospital_id', $hospital->id)->where('code', 'IMG-CXR')->first();
        $aspirin = Medication::query()->where('hospital_id', $hospital->id)->where('sku', 'ASA-75')->first();

        $labOrder = ServiceOrder::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'service_id' => $fbc?->id,
            'ordered_by' => $doctor->id,
            'completed_by' => $lab->id,
            'module_key' => 'laboratory',
            'order_type' => 'laboratory',
            'item_name' => 'Full blood count',
            'status' => 'completed',
            'result' => 'Hb 13.4 g/dL · WBC 7.1 · Platelets 248',
            'requested_at' => now()->subHours(4),
            'collected_at' => now()->subHours(3),
            'completed_at' => now()->subHours(2),
        ]);
        ChargeLedger::forService($encounter, 'LAB-FBC', 'order', $labOrder->id);

        $imgOrder = ServiceOrder::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'service_id' => $cxr?->id,
            'ordered_by' => $doctor->id,
            'completed_by' => $imaging->id,
            'module_key' => 'imaging',
            'order_type' => 'imaging',
            'item_name' => 'Chest X-ray',
            'status' => 'completed',
            'result' => 'No acute consolidation. Cardiomediastinal silhouette within limits.',
            'requested_at' => now()->subHours(4),
            'scheduled_at' => now()->subHours(3),
            'completed_at' => now()->subHours(2),
        ]);
        ChargeLedger::forService($encounter, 'IMG-CXR', 'order', $imgOrder->id);

        $rx = Prescription::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'prescribed_by' => $doctor->id,
            'verified_by' => $pharmacy->id,
            'status' => 'dispensed',
            'notes' => 'Start aspirin 75mg daily',
            'prescribed_at' => now()->subHours(2),
            'verified_at' => now()->subHours(1),
            'dispensed_at' => now()->subMinutes(40),
        ]);
        $item = PrescriptionItem::query()->create([
            'prescription_id' => $rx->id,
            'medication_id' => $aspirin->id,
            'dose' => '75mg',
            'frequency' => 'once daily',
            'duration' => '30 days',
            'quantity' => 30,
            'instructions' => 'After food',
        ]);
        $aspirin->adjustStock(-30);
        Dispensing::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'prescription_id' => $rx->id,
            'prescription_item_id' => $item->id,
            'medication_id' => $aspirin->id,
            'dispensed_by' => $pharmacy->id,
            'quantity' => 30,
            'dispensed_at' => now()->subMinutes(40),
        ]);
        ChargeLedger::post($encounter, 'dispensing', $item->id, $aspirin->label(), $aspirin->unit_price, 30);

        $invoice = ChargeLedger::openInvoice($encounter);
        $invoice->update(['status' => 'issued', 'issued_at' => now()->subMinutes(20), 'number' => 'RGH-INV-0001']);
        Payment::query()->create([
            'hospital_id' => $hospital->id,
            'invoice_id' => $invoice->id,
            'patient_id' => $patient->id,
            'amount' => 80,
            'method' => 'mobile_money',
            'received_by' => $billing->id,
            'received_at' => now()->subMinutes(15),
        ]);
    }

    private function seedInpatientJourney($hospital, Patient $patient, $emergency, $nurse, $doctor, $erDept, $wards, $erBay, $ward, $bed): void
    {
        $er = Encounter::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'department_id' => $erDept?->id,
            'clinician_id' => $emergency->id,
            'facility_id' => $erBay?->id,
            'type' => 'emergency',
            'status' => 'completed',
            'chief_complaint' => 'Severe abdominal pain and vomiting',
            'started_at' => now()->subHours(8),
            'completed_at' => now()->subHours(6),
        ]);
        $er->addClinician($emergency->id);
        ChargeLedger::forService($er, 'ER-CON', 'encounter', $er->id);

        Vital::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $er->id,
            'recorded_by' => $emergency->id,
            'recorded_at' => now()->subHours(8),
            'temperature' => 38.2,
            'pulse' => 110,
            'respiration' => 22,
            'systolic' => 102,
            'diastolic' => 68,
            'spo2' => 96,
        ]);

        $admission = Encounter::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'department_id' => $wards?->id,
            'clinician_id' => $doctor->id,
            'facility_id' => $ward?->id,
            'parent_encounter_id' => $er->id,
            'type' => 'admission',
            'status' => 'in_progress',
            'chief_complaint' => 'Query appendicitis, for observation',
            'started_at' => now()->subHours(6),
            'admitted_at' => now()->subHours(6),
        ]);
        $admission->addClinician($doctor->id);
        $admission->addClinician($nurse->id, 'nurse');
        ChargeLedger::forService($admission, 'ADM-DAY', 'encounter', $admission->id);

        ClinicalNote::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $admission->id,
            'author_id' => $nurse->id,
            'type' => 'nursing',
            'body' => 'Admitted to Ward A. IV fluids running. Pain score 6/10. NPO pending surgical review.',
            'recorded_at' => now()->subHours(5),
        ]);

        BedAssignment::query()->create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'encounter_id' => $admission->id,
            'facility_id' => $bed->id,
            'ward_id' => $ward?->id,
            'assigned_by' => $nurse->id,
            'nurse_id' => $nurse->id,
            'status' => 'active',
            'assigned_at' => now()->subHours(6),
        ]);
    }

    private function seedReferralJourney($riverside, $lakeside, Patient $patient, $emergency, $doctor, $erDept, $erBay): void
    {
        $encounter = Encounter::query()->create([
            'hospital_id' => $riverside->id,
            'patient_id' => $patient->id,
            'department_id' => $erDept?->id,
            'clinician_id' => $emergency->id,
            'facility_id' => $erBay?->id,
            'type' => 'emergency',
            'status' => 'transferred',
            'chief_complaint' => 'Polytrauma after road traffic collision',
            'started_at' => now()->subHours(3),
        ]);
        $encounter->addClinician($emergency->id);
        $encounter->addClinician($doctor->id);

        $icu = Facility::withoutGlobalScope('hospital')
            ->where('hospital_id', $lakeside->id)
            ->where('code', 'ICU-1')
            ->first();
        $wardTypeId = Facility::query()->where('hospital_id', $riverside->id)->where('code', 'WARD-A')->value('facility_type_id');

        Referral::query()->create([
            'from_hospital_id' => $riverside->id,
            'to_hospital_id' => $lakeside->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'referring_clinician_id' => $doctor->id,
            'patient_name' => $patient->fullName(),
            'patient_reference' => $patient->mrn,
            'reason' => 'Requires intensive monitoring after trauma',
            'required_facility_type_id' => $wardTypeId,
            'required_capacity' => 1,
            'destination_facility_id' => $icu?->id,
            'status' => 'pending',
            'created_by' => $doctor->id,
        ]);
    }
}
