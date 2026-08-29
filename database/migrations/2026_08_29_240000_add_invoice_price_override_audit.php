<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedInteger('original_unit_price')->nullable()->after('list_price');
            $table->string('override_reason')->nullable()->after('is_override');
            $table->foreignUuid('overridden_by')->nullable()->after('override_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable()->after('overridden_by');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overridden_by');
            $table->dropColumn(['original_unit_price', 'override_reason', 'overridden_at']);
        });
    }
};
