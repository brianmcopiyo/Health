<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropCode('hospitals', ['code']);
        $this->dropCode('facilities', ['hospital_id', 'code']);
        $this->dropCode('inventory_suppliers', ['hospital_id', 'code']);
        $this->dropCode('inventory_stores', ['hospital_id', 'code']);
        $this->dropCode('inventory_locations', ['store_id', 'code']);
        $this->dropCode('price_lists', ['hospital_id', 'code']);
        $this->dropCode('service_packages', ['hospital_id', 'code']);
    }

    public function down(): void
    {
        $this->restoreCode('hospitals', unique: true);
        $this->restoreCode('facilities', composite: ['hospital_id', 'code']);
        $this->restoreCode('inventory_suppliers', composite: ['hospital_id', 'code']);
        $this->restoreCode('inventory_stores', composite: ['hospital_id', 'code']);
        $this->restoreCode('inventory_locations', composite: ['store_id', 'code']);
        $this->restoreCode('price_lists', composite: ['hospital_id', 'code']);
        $this->restoreCode('service_packages', composite: ['hospital_id', 'code']);
    }

    private function dropCode(string $table, array $unique): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'code')) {
            return;
        }

        foreach ([$unique, ['code']] as $index) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropUnique($index);
                });
                break;
            } catch (\Throwable) {
                // Index name may differ between databases.
            }
        }

        if (Schema::hasColumn($table, 'code')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('code');
            });
        }
    }

    private function restoreCode(string $table, bool $unique = false, ?array $composite = null): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'code')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($unique, $composite) {
            $blueprint->string('code')->nullable()->after('name');
            if ($composite) {
                $blueprint->unique($composite);
            } elseif ($unique) {
                $blueprint->unique('code');
            }
        });
    }
};
