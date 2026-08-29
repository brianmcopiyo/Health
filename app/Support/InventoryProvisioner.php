<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitConversion;
use App\Models\Medication;
use Illuminate\Support\Str;

class InventoryProvisioner
{
    public static function seedFor(Hospital $hospital): void
    {
        $units = [];
        foreach ([
            ['Tablet', 'tab'],
            ['Capsule', 'cap'],
            ['Ampoule', 'amp'],
            ['Sachet', 'sachet'],
            ['Piece', 'pc'],
            ['Box', 'box'],
        ] as [$name, $symbol]) {
            $units[$symbol] = InventoryUnit::query()->firstOrCreate(
                ['hospital_id' => $hospital->id, 'symbol' => $symbol],
                ['name' => $name, 'is_active' => true]
            );
        }

        InventoryUnitConversion::query()->firstOrCreate(
            ['from_unit_id' => $units['box']->id, 'to_unit_id' => $units['tab']->id],
            ['hospital_id' => $hospital->id, 'factor' => 10]
        );

        $categories = [];
        foreach (['Medicines', 'Medical supplies', 'Consumables', 'Equipment'] as $index => $name) {
            $categories[$name] = InventoryCategory::query()->firstOrCreate(
                ['hospital_id' => $hospital->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $index, 'is_active' => true]
            );
        }

        InventorySupplier::query()->firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'NMS'],
            ['name' => 'National Medical Stores', 'is_active' => true]
        );

        $pharmacyDept = Department::query()->where('hospital_id', $hospital->id)->where('slug', 'pharmacy')->first();
        $wardsDept = Department::query()->where('hospital_id', $hospital->id)->where('slug', 'wards')->first();
        $pharmacyFacility = Facility::query()->where('hospital_id', $hospital->id)->where('code', 'PHARM-1')->first();

        $pharmacy = InventoryStore::query()->firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'PHARM'],
            [
                'name' => 'Main Pharmacy',
                'type' => 'pharmacy',
                'department_id' => $pharmacyDept?->id,
                'facility_id' => $pharmacyFacility?->id,
                'is_default' => true,
                'is_active' => true,
            ]
        );

        $pharmacy->locations()->firstOrCreate(
            ['code' => 'SHELF-A'],
            ['hospital_id' => $hospital->id, 'name' => 'Pharmacy shelf A', 'is_active' => true]
        );

        InventoryStore::query()->firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'STORE'],
            ['name' => 'Central Store', 'type' => 'warehouse', 'is_default' => false, 'is_active' => true]
        );

        InventoryStore::query()->firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'WARD'],
            [
                'name' => 'Ward Store',
                'type' => 'ward',
                'department_id' => $wardsDept?->id,
                'is_default' => false,
                'is_active' => true,
            ]
        );

        $formUnit = [
            'tablet' => $units['tab']->id,
            'capsule' => $units['cap']->id,
            'ampoule' => $units['amp']->id,
            'sachet' => $units['sachet']->id,
        ];

        $ledger = app(InventoryLedger::class);

        foreach (Medication::query()->withoutGlobalScope('hospital')->where('hospital_id', $hospital->id)->get() as $medication) {
            $item = InventoryItem::query()->updateOrCreate(
                ['hospital_id' => $hospital->id, 'sku' => $medication->sku],
                [
                    'category_id' => $categories['Medicines']->id,
                    'unit_id' => $formUnit[$medication->form] ?? $units['pc']->id,
                    'medication_id' => $medication->id,
                    'kind' => 'medicine',
                    'name' => $medication->name,
                    'form' => $medication->form,
                    'strength' => $medication->strength,
                    'unit_price' => $medication->unit_price,
                    'cost_price' => $medication->unit_price,
                    'reorder_level' => $medication->reorder_level,
                    'tracks_batch' => true,
                    'tracks_expiry' => true,
                    'is_controlled' => $medication->sku === 'MOR-10',
                    'is_active' => true,
                ]
            );

            $onHand = (float) $item->balances()->sum('quantity');
            $target = (float) $medication->stock_qty;
            if ($target > $onHand) {
                $batch = $item->batches()->firstOrCreate(
                    ['store_id' => $pharmacy->id, 'batch_number' => 'OPEN-'.$medication->sku],
                    [
                        'hospital_id' => $hospital->id,
                        'expiry_date' => now()->addYear()->toDateString(),
                        'quantity' => 0,
                        'unit_cost' => $item->cost_price,
                        'status' => 'available',
                        'received_at' => now(),
                    ]
                );
                $ledger->post([
                    'item_id' => $item->id,
                    'store_id' => $pharmacy->id,
                    'batch_id' => $batch->id,
                    'type' => 'opening',
                    'quantity' => $target - $onHand,
                    'unit_cost' => $item->cost_price,
                    'notes' => 'Opening pharmacy stock',
                ]);
            }
        }

        foreach ([
            ['Surgical gloves', 'SUP-GLV', 'supply', 'Medical supplies', 15, 80],
            ['IV giving set', 'CON-IV', 'consumable', 'Consumables', 25, 40],
            ['Pulse oximeter', 'EQ-POX', 'equipment', 'Equipment', 4500, 2],
        ] as [$name, $sku, $kind, $category, $price, $stock]) {
            $item = InventoryItem::query()->firstOrCreate(
                ['hospital_id' => $hospital->id, 'sku' => $sku],
                [
                    'category_id' => $categories[$category]->id,
                    'unit_id' => $units['pc']->id,
                    'kind' => $kind,
                    'name' => $name,
                    'unit_price' => $price,
                    'cost_price' => $price,
                    'reorder_level' => 5,
                    'tracks_batch' => $kind !== 'equipment',
                    'tracks_expiry' => $kind !== 'equipment',
                    'is_controlled' => false,
                    'is_active' => true,
                ]
            );

            if ((float) $item->balances()->sum('quantity') > 0) {
                continue;
            }

            $batchId = null;
            if ($item->tracks_batch) {
                $batch = $item->batches()->firstOrCreate(
                    ['store_id' => $pharmacy->id, 'batch_number' => 'OPEN-'.$sku],
                    [
                        'hospital_id' => $hospital->id,
                        'expiry_date' => $item->tracks_expiry ? now()->addMonths(18)->toDateString() : null,
                        'quantity' => 0,
                        'unit_cost' => $item->cost_price,
                        'status' => 'available',
                        'received_at' => now(),
                    ]
                );
                $batchId = $batch->id;
            }

            $ledger->post([
                'item_id' => $item->id,
                'store_id' => $pharmacy->id,
                'batch_id' => $batchId,
                'type' => 'opening',
                'quantity' => $stock,
                'unit_cost' => $item->cost_price,
                'notes' => 'Opening store stock',
            ]);
        }
    }
}
