<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->unsignedInteger('patient_seq')->default(0);
            $table->unsignedInteger('invoice_seq')->default(0);
        });

        Schema::table('patient_allergies', function (Blueprint $table) {
            $table->boolean('is_current')->default(true)->after('severity');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUuid('patient_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
        });

        DB::statement('UPDATE payments SET patient_id = (SELECT patient_id FROM invoices WHERE invoices.id = payments.invoice_id)');

        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('auditable_type');
            $table->uuid('auditable_id');
            $table->string('action');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['hospital_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_id', 'created_at']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->index(['hospital_id', 'created_at'], 'patients_hospital_created_idx');
            $table->index(['hospital_id', 'status', 'created_at'], 'patients_hospital_status_created_idx');
            $table->index(['hospital_id', 'last_name', 'first_name'], 'patients_hospital_name_idx');
            $table->index(['hospital_id', 'phone'], 'patients_hospital_phone_idx');
        });

        Schema::table('encounters', function (Blueprint $table) {
            $table->index(['hospital_id', 'created_at'], 'encounters_hospital_created_idx');
            $table->index(['hospital_id', 'type', 'status', 'created_at'], 'encounters_hospital_type_status_idx');
            $table->index(['hospital_id', 'patient_id', 'created_at'], 'encounters_hospital_patient_idx');
            $table->index(['clinician_id', 'status'], 'encounters_clinician_status_idx');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->index(['hospital_id', 'module_key', 'status', 'created_at'], 'orders_hospital_module_status_idx');
            $table->index(['hospital_id', 'patient_id', 'created_at'], 'orders_hospital_patient_idx');
            $table->index(['encounter_id', 'status'], 'orders_encounter_status_idx');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->index(['hospital_id', 'status', 'created_at'], 'rx_hospital_status_created_idx');
            $table->index(['hospital_id', 'patient_id'], 'rx_hospital_patient_idx');
            $table->index(['encounter_id'], 'rx_encounter_idx');
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->index(['prescription_id'], 'rx_items_prescription_idx');
            $table->index(['medication_id'], 'rx_items_medication_idx');
        });

        Schema::table('dispensings', function (Blueprint $table) {
            $table->index(['hospital_id', 'patient_id', 'dispensed_at'], 'dispense_hospital_patient_idx');
            $table->index(['prescription_id'], 'dispense_prescription_idx');
        });

        Schema::table('bed_assignments', function (Blueprint $table) {
            $table->index(['hospital_id', 'status', 'created_at'], 'beds_hospital_status_created_idx');
            $table->index(['facility_id', 'status'], 'beds_facility_status_idx');
            $table->index(['patient_id', 'status'], 'beds_patient_status_idx');
            $table->index(['encounter_id'], 'beds_encounter_idx');
            $table->index(['nurse_id', 'status'], 'beds_nurse_status_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['hospital_id', 'status', 'created_at'], 'invoices_hospital_status_created_idx');
            $table->index(['encounter_id', 'status'], 'invoices_encounter_status_idx');
            $table->index(['hospital_id', 'patient_id'], 'invoices_hospital_patient_idx');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->index(['invoice_id'], 'invoice_items_invoice_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['hospital_id', 'patient_id', 'received_at'], 'payments_hospital_patient_idx');
            $table->index(['invoice_id'], 'payments_invoice_idx');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->index(['from_hospital_id', 'status', 'created_at'], 'referrals_from_status_idx');
            $table->index(['to_hospital_id', 'status', 'created_at'], 'referrals_to_status_idx');
            $table->index(['patient_id'], 'referrals_patient_idx');
            $table->index(['receiving_patient_id'], 'referrals_receiving_patient_idx');
            $table->index(['encounter_id'], 'referrals_encounter_idx');
        });

        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->index(['from_hospital_id', 'status', 'created_at'], 'assist_from_status_idx');
            $table->index(['to_hospital_id', 'status', 'created_at'], 'assist_to_status_idx');
            $table->index(['patient_id'], 'assist_patient_idx');
        });

        Schema::table('ambulance_trips', function (Blueprint $table) {
            $table->index(['hospital_id', 'status', 'created_at'], 'trips_hospital_status_idx');
            $table->index(['ambulance_id', 'status'], 'trips_ambulance_status_idx');
            $table->index(['patient_id'], 'trips_patient_idx');
            $table->index(['referral_id'], 'trips_referral_idx');
        });

        Schema::table('ambulances', function (Blueprint $table) {
            $table->index(['hospital_id', 'status'], 'ambulances_hospital_status_idx');
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->index(['hospital_id', 'status'], 'facilities_hospital_status_idx');
            $table->index(['hospital_id', 'facility_type_id', 'status'], 'facilities_hospital_type_status_idx');
            $table->index(['hospital_id', 'department_id'], 'facilities_hospital_dept_idx');
            $table->index(['parent_id'], 'facilities_parent_idx');
        });

        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->index(['hospital_id', 'user_id', 'status'], 'staff_hospital_user_status_idx');
            $table->index(['facility_id', 'status'], 'staff_facility_status_idx');
        });

        Schema::table('vitals', function (Blueprint $table) {
            $table->index(['hospital_id', 'patient_id', 'recorded_at'], 'vitals_hospital_patient_idx');
            $table->index(['encounter_id', 'recorded_at'], 'vitals_encounter_idx');
        });

        Schema::table('clinical_notes', function (Blueprint $table) {
            $table->index(['hospital_id', 'patient_id', 'recorded_at'], 'notes_hospital_patient_idx');
            $table->index(['encounter_id', 'recorded_at'], 'notes_encounter_idx');
        });

        Schema::table('diagnoses', function (Blueprint $table) {
            $table->index(['hospital_id', 'patient_id'], 'dx_hospital_patient_idx');
            $table->index(['encounter_id'], 'dx_encounter_idx');
        });

        Schema::table('care_plans', function (Blueprint $table) {
            $table->index(['hospital_id', 'patient_id'], 'plans_hospital_patient_idx');
            $table->index(['encounter_id'], 'plans_encounter_idx');
        });

        Schema::table('medications', function (Blueprint $table) {
            $table->index(['hospital_id', 'name'], 'meds_hospital_name_idx');
        });

        Schema::table('clinical_services', function (Blueprint $table) {
            $table->index(['hospital_id', 'category', 'is_active'], 'services_hospital_category_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['hospital_id', 'name'], 'users_hospital_name_idx');
            $table->index(['role_id'], 'users_role_idx');
        });

        Schema::table('encounter_clinicians', function (Blueprint $table) {
            $table->index(['user_id', 'encounter_id'], 'encounter_clinicians_user_idx');
        });

        Schema::table('patient_allergies', function (Blueprint $table) {
            $table->index(['patient_id', 'is_current'], 'allergies_patient_current_idx');
        });

        Schema::table('patient_conditions', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'conditions_patient_status_idx');
        });

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS patients_hospital_national_id_unique ON patients (hospital_id, national_id) WHERE national_id IS NOT NULL AND national_id != ''");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS bed_assignments_one_active_facility ON bed_assignments (facility_id) WHERE status = 'active'");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS bed_assignments_one_active_patient ON bed_assignments (patient_id) WHERE status = 'active'");
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS invoice_items_source_unique ON invoice_items (invoice_id, source_type, source_id) WHERE source_type IS NOT NULL AND source_id IS NOT NULL');

        $counts = DB::table('hospitals')->select('id')->get();
        foreach ($counts as $hospital) {
            $patients = DB::table('patients')->where('hospital_id', $hospital->id)->count();
            $invoices = DB::table('invoices')->where('hospital_id', $hospital->id)->count();
            DB::table('hospitals')->where('id', $hospital->id)->update([
                'patient_seq' => $patients,
                'invoice_seq' => $invoices,
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS patients_hospital_national_id_unique');
        DB::statement('DROP INDEX IF EXISTS bed_assignments_one_active_facility');
        DB::statement('DROP INDEX IF EXISTS bed_assignments_one_active_patient');
        DB::statement('DROP INDEX IF EXISTS invoice_items_source_unique');

        Schema::dropIfExists('audit_events');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
        });

        Schema::table('patient_allergies', function (Blueprint $table) {
            $table->dropColumn('is_current');
        });

        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropColumn(['patient_seq', 'invoice_seq']);
        });
    }
};
