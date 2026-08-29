<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('action');
            $table->string('subject');
            $table->string('group')->nullable();
            $table->timestamps();
            $table->unique(['action', 'subject']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['hospital_id', 'slug']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('hospital_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignUuid('role_id')->nullable()->after('hospital_id')->constrained()->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
        });

        Schema::create('facility_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('facility_type_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('status')->default('available');
            $table->unsignedInteger('capacity')->default(1);
            $table->unsignedInteger('current_utilization')->default(0);
            $table->text('resource_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('assistance_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('from_hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('to_hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ambulances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_code');
            $table->string('vehicle_type')->default('van');
            $table->string('status')->default('available');
            $table->unsignedInteger('capacity')->default(2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'vehicle_code']);
        });

        Schema::create('ambulance_staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ambulance_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('assignment_role')->default('medic');
            $table->timestamps();
            $table->unique(['ambulance_id', 'user_id']);
        });

        Schema::create('ambulance_trips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ambulance_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('driver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin');
            $table->string('destination');
            $table->foreignUuid('destination_hospital_id')->nullable()->constrained('hospitals')->nullOnDelete();
            $table->string('status')->default('dispatched');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('from_hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('to_hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('patient_name');
            $table->string('patient_reference')->nullable();
            $table->text('reason');
            $table->foreignUuid('required_facility_type_id')->nullable()->constrained('facility_types')->nullOnDelete();
            $table->unsignedInteger('required_capacity')->default(1);
            $table->foreignUuid('destination_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignUuid('ambulance_trip_id')->nullable()->constrained('ambulance_trips')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('ambulance_trips');
        Schema::dropIfExists('ambulance_staff');
        Schema::dropIfExists('ambulances');
        Schema::dropIfExists('assistance_requests');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('facility_types');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hospital_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['phone', 'job_title']);
        });
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('hospitals');
    }
};
