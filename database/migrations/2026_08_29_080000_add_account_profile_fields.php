<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('license_number');
            $table->string('availability', 20)->default('available')->after('avatar_path');
            $table->json('preferences')->nullable()->after('availability');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('expires_at');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'availability', 'preferences']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
