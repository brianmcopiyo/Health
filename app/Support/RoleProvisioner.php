<?php

namespace App\Support;

use App\Models\Hospital;
use App\Models\Permission;
use App\Models\Role;

class RoleProvisioner
{
    public static function permissionDefinitions(): array
    {
        $items = [
            ['name' => 'Manage everything', 'action' => 'manage', 'subject' => 'all', 'group' => 'System'],
            ['name' => 'View hospitals', 'action' => 'read', 'subject' => 'Hospital', 'group' => 'Network'],
            ['name' => 'Manage hospitals', 'action' => 'manage', 'subject' => 'Hospital', 'group' => 'Network'],
            ['name' => 'Respond to referrals', 'action' => 'respond', 'subject' => 'Referral', 'group' => 'Referrals'],
            ['name' => 'Respond to assistance', 'action' => 'respond', 'subject' => 'AssistanceRequest', 'group' => 'Assistance'],
            ['name' => 'Dispatch ambulances', 'action' => 'dispatch', 'subject' => 'Ambulance', 'group' => 'Ambulance'],
        ];

        $modules = [
            'Patient' => 'Patients',
            'Reception' => 'Reception',
            'Opd' => 'OPD',
            'Ward' => 'Wards',
            'Bed' => 'Beds',
            'Laboratory' => 'Laboratory',
            'Pharmacy' => 'Pharmacy',
            'Imaging' => 'Imaging',
            'Theatre' => 'Theatre',
            'Emergency' => 'Emergency',
            'Ambulance' => 'Ambulance',
            'Referral' => 'Referrals',
            'AssistanceRequest' => 'Assistance',
            'Invoice' => 'Billing',
            'Report' => 'Reports',
            'Department' => 'Departments',
            'Facility' => 'Facilities',
            'User' => 'Access',
            'Role' => 'Access',
        ];

        foreach ($modules as $subject => $group) {
            foreach (['read' => 'View', 'create' => 'Create', 'update' => 'Update', 'manage' => 'Manage'] as $action => $label) {
                $items[] = [
                    'name' => $label.' '.$group,
                    'action' => $action,
                    'subject' => $subject,
                    'group' => $group,
                ];
            }
        }

        return $items;
    }

    public static function roleDefinitions(): array
    {
        return [
            'administrator' => ['Administrator', 'Hospital administrator for roles, departments, and access', 'admin', 'hospital'],
            'doctor' => ['Doctor', 'Consultation, referrals, and clinical follow-up', 'opd', [
                'read.Patient', 'update.Patient',
                'read.Opd', 'create.Opd', 'update.Opd', 'manage.Opd',
                'read.Emergency',
                'read.Ward', 'read.Bed',
                'read.Laboratory', 'read.Imaging',
                'read.Referral', 'create.Referral', 'update.Referral', 'respond.Referral',
                'read.AssistanceRequest', 'create.AssistanceRequest',
                'read.Report',
            ]],
            'nurse' => ['Nurse', 'Wards, beds, and inpatient status', 'wards', [
                'read.Patient', 'update.Patient',
                'read.Ward', 'update.Ward',
                'read.Bed', 'create.Bed', 'update.Bed', 'manage.Bed',
                'read.Emergency', 'update.Emergency',
                'read.Referral',
                'read.Ambulance',
            ]],
            'lab-staff' => ['Lab Staff', 'Laboratory operations', 'laboratory', [
                'read.Patient',
                'read.Laboratory', 'create.Laboratory', 'update.Laboratory', 'manage.Laboratory',
            ]],
            'pharmacy-staff' => ['Pharmacy Staff', 'Pharmacy and chemist operations', 'pharmacy', [
                'read.Patient',
                'read.Pharmacy', 'create.Pharmacy', 'update.Pharmacy', 'manage.Pharmacy',
                'read.AssistanceRequest',
            ]],
            'reception' => ['Reception', 'Registration, intake, and referral desk', 'reception', [
                'read.Patient', 'create.Patient', 'update.Patient', 'manage.Patient',
                'read.Reception', 'create.Reception', 'update.Reception', 'manage.Reception',
                'create.Opd', 'create.Emergency',
                'read.Referral', 'create.Referral',
                'read.AssistanceRequest', 'create.AssistanceRequest',
                'read.Ambulance',
            ]],
            'ambulance-team' => ['Ambulance Team', 'Fleet, dispatch, and trip history', 'ambulances', [
                'read.Patient',
                'read.Ambulance', 'create.Ambulance', 'update.Ambulance', 'manage.Ambulance', 'dispatch.Ambulance',
                'read.Referral', 'update.Referral',
                'read.Emergency',
            ]],
            'facility-manager' => ['Facility Manager', 'Departments, capacity, and facility configuration', 'facilities', [
                'read.Facility', 'create.Facility', 'update.Facility', 'manage.Facility',
                'read.Department', 'create.Department', 'update.Department', 'manage.Department',
                'read.Ward', 'create.Ward', 'update.Ward', 'manage.Ward',
                'read.Bed', 'create.Bed', 'update.Bed', 'manage.Bed',
                'read.Theatre', 'update.Theatre',
                'read.Emergency', 'update.Emergency',
                'read.Referral', 'respond.Referral',
                'read.AssistanceRequest', 'create.AssistanceRequest', 'update.AssistanceRequest', 'respond.AssistanceRequest',
                'read.Report',
            ]],
            'imaging-staff' => ['Imaging Staff', 'Imaging and diagnostics', 'imaging', [
                'read.Patient',
                'read.Imaging', 'create.Imaging', 'update.Imaging', 'manage.Imaging',
            ]],
            'theatre-staff' => ['Theatre Staff', 'Operating theatre board', 'theatre', [
                'read.Patient',
                'read.Theatre', 'create.Theatre', 'update.Theatre', 'manage.Theatre',
            ]],
            'emergency-staff' => ['Emergency Staff', 'Emergency unit operations', 'emergency', [
                'read.Patient', 'update.Patient',
                'read.Emergency', 'create.Emergency', 'update.Emergency', 'manage.Emergency',
                'read.Ambulance',
                'read.Referral',
            ]],
            'billing-clerk' => ['Billing Clerk', 'Invoices and billing reports', 'billing', [
                'read.Patient',
                'read.Invoice', 'create.Invoice', 'update.Invoice', 'manage.Invoice',
                'read.Report',
            ]],
        ];
    }

    public static function seedPermissions(): void
    {
        foreach (self::permissionDefinitions() as $permission) {
            Permission::query()->firstOrCreate(
                ['action' => $permission['action'], 'subject' => $permission['subject']],
                $permission
            );
        }
    }

    public static function seedPlatformAdmin(): Role
    {
        $role = Role::query()->firstOrCreate(
            ['hospital_id' => null, 'slug' => 'platform-admin'],
            [
                'name' => 'Platform Admin',
                'description' => 'Full access across the hospital network',
                'workspace' => 'admin-hospitals',
                'is_system' => true,
            ]
        );

        $manageAll = Permission::query()->where('action', 'manage')->where('subject', 'all')->first();
        $role->permissions()->sync([$manageAll->id]);

        return $role;
    }

    public static function seedFor(Hospital $hospital): array
    {
        $permissionMap = Permission::query()->get()->keyBy(fn (Permission $permission) => $permission->action.'.'.$permission->subject);
        $hospitalKeys = $permissionMap->keys()
            ->reject(fn (string $key) => in_array($key, ['manage.all', 'read.Hospital', 'manage.Hospital'], true))
            ->values();

        $roles = [];

        foreach (self::roleDefinitions() as $slug => $config) {
            [$name, $description, $workspace, $keys] = $config;
            $permissionKeys = $keys === 'hospital' ? $hospitalKeys : collect($keys);

            $role = Role::query()->updateOrCreate(
                ['hospital_id' => $hospital->id, 'slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'workspace' => $workspace,
                    'is_system' => true,
                ]
            );

            $ids = $permissionKeys
                ->map(fn (string $key) => $permissionMap[$key]->id)
                ->all();

            $role->permissions()->sync($ids);
            $roles[$slug] = $role;
        }

        return $roles;
    }
}
