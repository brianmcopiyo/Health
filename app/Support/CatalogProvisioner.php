<?php

namespace App\Support;

use App\Models\ClinicalService;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\Medication;

class CatalogProvisioner
{
    public static function seedFor(Hospital $hospital): void
    {
        $departments = Department::query()->where('hospital_id', $hospital->id)->get()->keyBy('slug');

        $services = [
            ['OPD Consultation', 'OPD-CON', 'consultation', 'opd', 150],
            ['Emergency Consultation', 'ER-CON', 'consultation', 'emergency', 250],
            ['Inpatient admission', 'ADM-DAY', 'admission', 'wards', 400],
            ['Full blood count', 'LAB-FBC', 'laboratory', 'laboratory', 80],
            ['Malaria RDT', 'LAB-RDT', 'laboratory', 'laboratory', 40],
            ['Chest X-ray', 'IMG-CXR', 'imaging', 'imaging', 200],
            ['CT scan', 'IMG-CT', 'imaging', 'imaging', 800],
            ['Theatre procedure', 'TH-PROC', 'procedure', 'theatre', 1200],
            ['Ambulance transfer', 'AMB-TRP', 'ambulance', 'ambulance', 500],
        ];

        foreach ($services as [$name, $code, $category, $dept, $price]) {
            ClinicalService::query()->updateOrCreate(
                ['hospital_id' => $hospital->id, 'code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'department_id' => $departments[$dept]->id ?? null,
                    'unit_price' => $price,
                    'is_active' => true,
                ]
            );
        }

        $meds = [
            ['Aspirin', 'tablet', '75mg', 'ASA-75', 8, 240],
            ['Amoxicillin', 'capsule', '500mg', 'AMX-500', 12, 180],
            ['Paracetamol', 'tablet', '500mg', 'PCM-500', 4, 400],
            ['ORS', 'sachet', '20.5g', 'ORS-20', 6, 120],
            ['Morphine', 'ampoule', '10mg', 'MOR-10', 35, 40],
        ];

        foreach ($meds as [$name, $form, $strength, $sku, $price, $stock]) {
            Medication::query()->updateOrCreate(
                ['hospital_id' => $hospital->id, 'sku' => $sku],
                [
                    'name' => $name,
                    'form' => $form,
                    'strength' => $strength,
                    'unit_price' => $price,
                    'stock_qty' => $stock,
                    'reorder_level' => 20,
                ]
            );
        }

        InventoryProvisioner::seedFor($hospital);
    }
}
