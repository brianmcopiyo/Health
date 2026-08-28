<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->string('national_id')->nullable()->after('email');
            $table->string('blood_group')->nullable()->after('national_id');
            $table->string('marital_status')->nullable()->after('blood_group');
            $table->string('occupation')->nullable()->after('marital_status');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relation')->nullable()->after('emergency_contact_phone');
            $table->string('next_of_kin_name')->nullable()->after('emergency_contact_relation');
            $table->string('next_of_kin_phone')->nullable()->after('next_of_kin_name');
            $table->string('next_of_kin_relation')->nullable()->after('next_of_kin_phone');
            $table->foreignId('source_patient_id')->nullable()->after('hospital_id')->constrained('patients')->nullOnDelete();
            $table->text('notes')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('job_title')->constrained()->nullOnDelete();
            $table->string('specialty')->nullable()->after('department_id');
            $table->string('license_number')->nullable()->after('specialty');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('kind')->default('clinical')->after('module_key');
        });

        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('allergen');
            $table->string('reaction')->nullable();
            $table->string('severity')->default('moderate');
            $table->foreignId('noted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('noted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->date('diagnosed_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assignment_role')->default('nurse');
            $table->string('shift')->default('day');
            $table->string('status')->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clinical_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('category');
            $table->unsignedInteger('unit_price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::table('encounters', function (Blueprint $table) {
            $table->foreignId('parent_encounter_id')->nullable()->after('facility_id')->constrained('encounters')->nullOnDelete();
            $table->foreignId('referral_id')->nullable()->after('parent_encounter_id')->constrained('referrals')->nullOnDelete();
            $table->foreignId('ambulance_trip_id')->nullable()->after('referral_id')->constrained('ambulance_trips')->nullOnDelete();
            $table->timestamp('admitted_at')->nullable()->after('completed_at');
            $table->timestamp('discharged_at')->nullable()->after('admitted_at');
        });

        Schema::create('encounter_clinicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('care_role')->default('attending');
            $table->timestamps();
            $table->unique(['encounter_id', 'user_id', 'care_role']);
        });

        Schema::create('vitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->decimal('temperature', 5, 1)->nullable();
            $table->unsignedInteger('pulse')->nullable();
            $table->unsignedInteger('respiration')->nullable();
            $table->unsignedInteger('systolic')->nullable();
            $table->unsignedInteger('diastolic')->nullable();
            $table->unsignedInteger('spo2')->nullable();
            $table->decimal('weight', 6, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('progress');
            $table->text('body');
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('kind')->default('primary');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('care_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('encounter_id')->constrained('clinical_services')->nullOnDelete();
            $table->string('order_type')->nullable()->after('module_key');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('form')->default('tablet');
            $table->string('strength')->nullable();
            $table->string('sku');
            $table->unsignedInteger('unit_price')->default(0);
            $table->integer('stock_qty')->default(0);
            $table->integer('reorder_level')->default(10);
            $table->timestamps();
            $table->unique(['hospital_id', 'sku']);
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescribed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('prescribed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained()->restrictOnDelete();
            $table->string('dose');
            $table->string('frequency');
            $table->string('duration')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('dispensings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medication_id')->constrained()->restrictOnDelete();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('invoice_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->foreignId('service_id')->nullable()->after('source_id')->constrained('clinical_services')->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('method')->default('cash');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->foreignId('encounter_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
            $table->foreignId('referring_clinician_id')->nullable()->after('encounter_id')->constrained('users')->nullOnDelete();
            $table->foreignId('required_service_id')->nullable()->after('required_facility_type_id')->constrained('clinical_services')->nullOnDelete();
            $table->foreignId('receiving_patient_id')->nullable()->after('patient_id')->constrained('patients')->nullOnDelete();
            $table->foreignId('receiving_encounter_id')->nullable()->after('encounter_id')->constrained('encounters')->nullOnDelete();
            $table->foreignId('counter_referral_id')->nullable()->constrained('referrals')->nullOnDelete();
        });

        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('to_hospital_id')->constrained()->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
            $table->foreignId('facility_type_id')->nullable()->after('encounter_id')->constrained()->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->after('facility_type_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1)->after('type');
        });

        Schema::table('ambulance_trips', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('ambulance_id')->constrained()->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
            $table->foreignId('referral_id')->nullable()->after('encounter_id')->constrained()->nullOnDelete();
            $table->string('pickup_location')->nullable()->after('origin');
            $table->foreignId('destination_facility_id')->nullable()->after('destination_hospital_id')->constrained('facilities')->nullOnDelete();
            $table->foreignId('receiving_encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->text('handover_notes')->nullable();
            $table->timestamp('handover_at')->nullable();
        });

        Schema::table('bed_assignments', function (Blueprint $table) {
            $table->foreignId('ward_id')->nullable()->after('facility_id')->constrained('facilities')->nullOnDelete();
            $table->foreignId('nurse_id')->nullable()->after('assigned_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bed_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ward_id');
            $table->dropConstrainedForeignId('nurse_id');
        });
        Schema::table('ambulance_trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
            $table->dropConstrainedForeignId('encounter_id');
            $table->dropConstrainedForeignId('referral_id');
            $table->dropConstrainedForeignId('destination_facility_id');
            $table->dropConstrainedForeignId('receiving_encounter_id');
            $table->dropColumn(['pickup_location', 'handover_notes', 'handover_at']);
        });
        Schema::table('assistance_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
            $table->dropConstrainedForeignId('encounter_id');
            $table->dropConstrainedForeignId('facility_type_id');
            $table->dropConstrainedForeignId('facility_id');
            $table->dropColumn('quantity');
        });
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('encounter_id');
            $table->dropConstrainedForeignId('referring_clinician_id');
            $table->dropConstrainedForeignId('required_service_id');
            $table->dropConstrainedForeignId('receiving_patient_id');
            $table->dropConstrainedForeignId('receiving_encounter_id');
            $table->dropConstrainedForeignId('counter_referral_id');
        });
        Schema::dropIfExists('payments');
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn(['source_type', 'source_id']);
        });
        Schema::dropIfExists('dispensings');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medications');
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn(['order_type', 'requested_at', 'collected_at', 'scheduled_at', 'completed_at']);
        });
        Schema::dropIfExists('care_plans');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('vitals');
        Schema::dropIfExists('encounter_clinicians');
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_encounter_id');
            $table->dropConstrainedForeignId('referral_id');
            $table->dropConstrainedForeignId('ambulance_trip_id');
            $table->dropColumn(['admitted_at', 'discharged_at']);
        });
        Schema::dropIfExists('clinical_services');
        Schema::dropIfExists('staff_assignments');
        Schema::dropIfExists('patient_conditions');
        Schema::dropIfExists('patient_allergies');
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['specialty', 'license_number']);
        });
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_patient_id');
            $table->dropColumn([
                'email', 'national_id', 'blood_group', 'marital_status', 'occupation',
                'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
                'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relation', 'notes',
            ]);
        });
    }
};
