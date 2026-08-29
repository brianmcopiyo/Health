<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('workspace')->nullable()->after('description');
        });

        Schema::create('hospital_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'hospital_id']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('module_key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'slug']);
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->foreignUuid('department_id')->nullable()->after('parent_id')->constrained()->nullOnDelete();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('mrn');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('sex')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['hospital_id', 'mrn']);
        });

        Schema::create('encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('waiting');
            $table->string('chief_complaint')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bed_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('facility_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module_key');
            $table->string('item_name');
            $table->string('status')->default('pending');
            $table->text('result')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number');
            $table->string('status')->default('draft');
            $table->unsignedInteger('total')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'number']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_amount')->default(0);
            $table->unsignedInteger('amount')->default(0);
            $table->timestamps();
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->foreignUuid('patient_id')->nullable()->after('to_hospital_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
        });
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('service_orders');
        Schema::dropIfExists('bed_assignments');
        Schema::dropIfExists('encounters');
        Schema::dropIfExists('patients');
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::dropIfExists('departments');
        Schema::dropIfExists('hospital_user');
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('workspace');
        });
    }
};
