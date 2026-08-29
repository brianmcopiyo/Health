<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'slug']);
        });

        Schema::create('inventory_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'symbol']);
        });

        Schema::create('inventory_unit_conversions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('from_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->foreignUuid('to_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->decimal('factor', 18, 6);
            $table->timestamps();
            $table->unique(['from_unit_id', 'to_unit_id']);
        });

        Schema::create('inventory_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('inventory_stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('type', 30)->default('warehouse');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['store_id', 'code']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->foreignUuid('unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->foreignUuid('medication_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30)->default('medicine');
            $table->string('name');
            $table->string('sku');
            $table->string('form')->nullable();
            $table->string('strength')->nullable();
            $table->unsignedInteger('unit_price')->default(0);
            $table->unsignedInteger('cost_price')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('tracks_batch')->default(true);
            $table->boolean('tracks_expiry')->default(true);
            $table->boolean('is_controlled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'sku']);
            $table->index(['hospital_id', 'kind', 'is_active']);
        });

        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('inventory_suppliers')->nullOnDelete();
            $table->string('batch_number');
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->unsignedInteger('unit_cost')->default(0);
            $table->string('status', 20)->default('available');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['item_id', 'store_id', 'batch_number']);
            $table->index(['store_id', 'status']);
            $table->index('expiry_date');
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['item_id', 'store_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 14, 3);
            $table->unsignedInteger('unit_cost')->default(0);
            $table->decimal('balance_after', 14, 3)->default(0);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->foreignUuid('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['item_id', 'store_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        Schema::create('inventory_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->foreignUuid('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('inventory_suppliers')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained('inventory_receipts')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->unsignedInteger('unit_cost')->default(0);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->foreignUuid('from_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('to_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->string('status', 20)->default('completed');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained('inventory_transfers')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });

        Schema::create('inventory_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->foreignUuid('from_store_id')->nullable()->constrained('inventory_stores')->nullOnDelete();
            $table->foreignUuid('to_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('requested');
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('inventory_requests')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });

        Schema::create('inventory_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->foreignUuid('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('to_store_id')->nullable()->constrained('inventory_stores')->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('request_id')->nullable()->constrained('inventory_requests')->nullOnDelete();
            $table->string('kind', 20)->default('department');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_issue_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('issue_id')->constrained('inventory_issues')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });

        Schema::create('inventory_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->foreignUuid('from_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('to_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('issue_id')->nullable()->constrained('inventory_issues')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('return_id')->constrained('inventory_returns')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->foreignUuid('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->string('reason', 40)->default('correction');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('direction', 10);
            $table->timestamps();
        });

        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->foreignUuid('store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->string('status', 20)->default('posted');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('count_id')->constrained('inventory_counts')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->decimal('system_quantity', 14, 3)->default(0);
            $table->decimal('counted_quantity', 14, 3);
            $table->decimal('variance', 14, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_counts');
        Schema::dropIfExists('inventory_adjustment_items');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_return_items');
        Schema::dropIfExists('inventory_returns');
        Schema::dropIfExists('inventory_issue_items');
        Schema::dropIfExists('inventory_issues');
        Schema::dropIfExists('inventory_request_items');
        Schema::dropIfExists('inventory_requests');
        Schema::dropIfExists('inventory_transfer_items');
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('inventory_receipt_items');
        Schema::dropIfExists('inventory_receipts');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('inventory_stores');
        Schema::dropIfExists('inventory_suppliers');
        Schema::dropIfExists('inventory_unit_conversions');
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('inventory_categories');
    }
};
