<?php

use App\Support\FieldCrypt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('phone_index', 64)->nullable();
            $table->string('phone_tail_index', 64)->nullable();
            $table->string('email_index', 64)->nullable();
            $table->string('national_id_index', 64)->nullable();
            $table->timestamp('retention_until')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->index(['hospital_id', 'phone_index'], 'patients_phone_index_idx');
            $table->index(['hospital_id', 'phone_tail_index'], 'patients_phone_tail_idx');
            $table->index(['hospital_id', 'email_index'], 'patients_email_index_idx');
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('action');
            $table->string('user_agent')->nullable()->after('ip_address');
        });

        Schema::create('clinical_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('clinical');
            $table->string('path');
            $table->text('original_name');
            $table->string('mime', 120);
            $table->unsignedInteger('size');
            $table->string('checksum', 64);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->index(['hospital_id', 'patient_id']);
        });

        DB::statement('DROP INDEX IF EXISTS patients_hospital_national_id_unique');
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS patients_national_id_index_unique ON patients (hospital_id, national_id_index) WHERE national_id_index IS NOT NULL");

        $this->encryptPatients();
        $this->encryptTable('clinical_notes', ['body']);
        $this->encryptTable('care_plans', ['body']);
        $this->encryptTable('diagnoses', ['name', 'code']);
        $this->encryptTable('patient_allergies', ['allergen', 'reaction']);
        $this->encryptTable('patient_conditions', ['name', 'notes']);
        $this->encryptTable('vitals', ['notes']);
        $this->encryptTable('service_orders', ['result', 'notes']);
        $this->encryptTable('encounters', ['chief_complaint', 'notes']);
        $this->encryptTable('referrals', ['reason', 'response_notes', 'patient_name']);
        $this->encryptTable('ambulance_trips', ['notes', 'handover_notes']);
        $this->encryptTable('prescriptions', ['notes']);
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_documents');
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'phone_index', 'phone_tail_index', 'email_index', 'national_id_index',
                'retention_until', 'archived_at', 'purged_at',
            ]);
        });
    }

    private function encryptPatients(): void
    {
        DB::table('patients')->orderBy('id')->chunkById(50, function ($rows) {
            foreach ($rows as $row) {
                $update = [];
                foreach (['phone', 'email', 'national_id', 'address', 'emergency_contact_name', 'emergency_contact_phone', 'next_of_kin_name', 'next_of_kin_phone', 'notes'] as $column) {
                    $value = $row->{$column} ?? null;
                    if ($value === null || $value === '' || FieldCrypt::isEncrypted($value)) {
                        continue;
                    }
                    $update[$column] = FieldCrypt::encrypt($value);
                }
                $phone = FieldCrypt::isEncrypted($row->phone ?? null) ? FieldCrypt::decrypt($row->phone) : ($row->phone ?? null);
                $email = FieldCrypt::isEncrypted($row->email ?? null) ? FieldCrypt::decrypt($row->email) : ($row->email ?? null);
                $nid = FieldCrypt::isEncrypted($row->national_id ?? null) ? FieldCrypt::decrypt($row->national_id) : ($row->national_id ?? null);
                $update['phone_index'] = FieldCrypt::blindIndex(FieldCrypt::normalizePhone($phone));
                $update['phone_tail_index'] = FieldCrypt::blindIndex(FieldCrypt::phoneTail(FieldCrypt::normalizePhone($phone)));
                $update['email_index'] = FieldCrypt::blindIndex(FieldCrypt::normalizeEmail($email));
                $update['national_id_index'] = FieldCrypt::blindIndex(FieldCrypt::normalizeNationalId($nid));
                DB::table('patients')->where('id', $row->id)->update($update);
            }
        });
    }

    private function encryptTable(string $table, array $columns): void
    {
        DB::table($table)->orderBy('id')->chunkById(50, function ($rows) use ($table, $columns) {
            foreach ($rows as $row) {
                $update = [];
                foreach ($columns as $column) {
                    $value = $row->{$column} ?? null;
                    if ($value === null || $value === '' || FieldCrypt::isEncrypted($value)) {
                        continue;
                    }
                    $update[$column] = FieldCrypt::encrypt($value);
                }
                if ($update) {
                    DB::table($table)->where('id', $row->id)->update($update);
                }
            }
        });
    }
};
