<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Hospital;

class HospitalProvisioner
{
    public static function departmentDefinitions(): array
    {
        return [
            ['Reception', 'reception', 'reception'],
            ['Outpatient', 'opd', 'opd'],
            ['Emergency', 'emergency', 'emergency'],
            ['Wards', 'wards', 'wards'],
            ['Laboratory', 'laboratory', 'laboratory'],
            ['Pharmacy', 'pharmacy', 'pharmacy'],
            ['Imaging', 'imaging', 'imaging'],
            ['Theatre', 'theatre', 'theatre'],
            ['Billing', 'billing', 'billing'],
            ['Ambulance', 'ambulance', 'ambulances'],
        ];
    }

    public static function bootstrap(Hospital $hospital): array
    {
        $roles = RoleProvisioner::seedFor($hospital);
        self::seedDepartments($hospital);

        return $roles;
    }

    public static function seedDepartments(Hospital $hospital): void
    {
        foreach (self::departmentDefinitions() as [$name, $slug, $moduleKey]) {
            Department::query()->updateOrCreate(
                ['hospital_id' => $hospital->id, 'slug' => $slug],
                [
                    'name' => $name,
                    'module_key' => $moduleKey,
                    'kind' => in_array($slug, ['billing', 'ambulance', 'reception'], true) ? 'operational' : 'clinical',
                    'is_active' => true,
                ]
            );
        }

        CatalogProvisioner::seedFor($hospital);
    }
}
