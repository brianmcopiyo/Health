<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('rate')->default(0);
            $table->boolean('is_inclusive')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('unit_price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('service_package_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('package_id')->constrained('service_packages')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('clinical_services')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('kind', 30)->default('self_pay');
            $table->foreignUuid('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('tax_inclusive')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('price_list_id')->constrained()->cascadeOnDelete();
            $table->string('billable_type', 40);
            $table->uuid('billable_id');
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->unsignedInteger('unit_price');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['price_list_id', 'billable_type', 'billable_id']);
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30);
            $table->string('scope', 30)->default('service');
            $table->string('billable_type', 40)->nullable();
            $table->uuid('billable_id')->nullable();
            $table->string('service_category', 40)->nullable();
            $table->foreignUuid('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('price_list_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('min_quantity')->nullable();
            $table->unsignedInteger('max_quantity')->nullable();
            $table->unsignedInteger('value');
            $table->unsignedInteger('min_price')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->string('field', 40)->default('unit_price');
            $table->unsignedInteger('old_price')->nullable();
            $table->unsignedInteger('new_price')->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignUuid('price_list_id')->nullable()->after('encounter_id')->constrained()->nullOnDelete();
            $table->string('payer_type', 30)->default('self_pay')->after('price_list_id');
            $table->unsignedInteger('discount_total')->default(0)->after('total');
            $table->unsignedInteger('tax_total')->default(0)->after('discount_total');
            $table->boolean('tax_inclusive')->default(false)->after('tax_total');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('billable_type', 40)->nullable()->after('service_id');
            $table->uuid('billable_id')->nullable()->after('billable_type');
            $table->unsignedInteger('list_price')->nullable()->after('unit_amount');
            $table->unsignedInteger('discount_amount')->default(0)->after('list_price');
            $table->unsignedInteger('discount_percent')->default(0)->after('discount_amount');
            $table->unsignedInteger('tax_amount')->default(0)->after('discount_percent');
            $table->unsignedInteger('tax_rate')->default(0)->after('tax_amount');
            $table->foreignUuid('price_list_id')->nullable()->after('tax_rate')->constrained()->nullOnDelete();
            $table->foreignUuid('pricing_rule_id')->nullable()->after('price_list_id')->constrained()->nullOnDelete();
            $table->boolean('is_override')->default(false)->after('pricing_rule_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('method');
            $table->string('status', 20)->default('completed')->after('reference');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('method', 30);
            $table->string('reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['reference', 'status']);
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_rule_id');
            $table->dropConstrainedForeignId('price_list_id');
            $table->dropColumn([
                'billable_type', 'billable_id', 'list_price', 'discount_amount', 'discount_percent',
                'tax_amount', 'tax_rate', 'is_override',
            ]);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_list_id');
            $table->dropColumn(['payer_type', 'discount_total', 'tax_total', 'tax_inclusive']);
        });
        Schema::dropIfExists('price_histories');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('service_package_items');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('tax_rates');
    }
};
