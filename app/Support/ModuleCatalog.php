<?php

namespace App\Support;

use App\Models\User;

class ModuleCatalog
{
    public static function all(): array
    {
        return [
            [
                'key' => 'reception',
                'title' => 'Reception',
                'icon' => 'tabler-desk',
                'to' => 'reception',
                'subject' => 'Reception',
                'heading' => 'Front desk',
            ],
            [
                'key' => 'patients',
                'title' => 'Patients',
                'icon' => 'tabler-users',
                'to' => 'patients',
                'subject' => 'Patient',
                'heading' => 'Front desk',
            ],
            [
                'key' => 'opd',
                'title' => 'OPD',
                'icon' => 'tabler-stethoscope',
                'to' => 'opd',
                'subject' => 'Opd',
                'heading' => 'Clinical',
                'facility_type' => 'consultation-room',
                'encounter_type' => 'opd',
            ],
            [
                'key' => 'emergency',
                'title' => 'Emergency',
                'icon' => 'tabler-emergency-bed',
                'to' => 'emergency',
                'subject' => 'Emergency',
                'heading' => 'Clinical',
                'facility_type' => 'emergency-unit',
                'encounter_type' => 'emergency',
                'orders' => true,
            ],
            [
                'key' => 'wards',
                'title' => 'Wards',
                'icon' => 'tabler-building-hospital',
                'to' => 'wards',
                'subject' => 'Ward',
                'heading' => 'Inpatient',
                'facility_type' => 'ward',
            ],
            [
                'key' => 'beds',
                'title' => 'Beds',
                'icon' => 'tabler-bed',
                'to' => 'beds',
                'subject' => 'Bed',
                'heading' => 'Inpatient',
                'facility_type' => 'bed',
                'assignments' => true,
            ],
            [
                'key' => 'theatre',
                'title' => 'Theatre',
                'icon' => 'tabler-cut',
                'to' => 'theatre',
                'subject' => 'Theatre',
                'heading' => 'Inpatient',
                'facility_type' => 'theatre',
                'orders' => true,
            ],
            [
                'key' => 'laboratory',
                'title' => 'Laboratory',
                'icon' => 'tabler-test-pipe',
                'to' => 'laboratory',
                'subject' => 'Laboratory',
                'heading' => 'Diagnostics',
                'facility_type' => 'lab',
                'orders' => true,
            ],
            [
                'key' => 'imaging',
                'title' => 'Imaging',
                'icon' => 'tabler-scan',
                'to' => 'imaging',
                'subject' => 'Imaging',
                'heading' => 'Diagnostics',
                'facility_type' => 'imaging',
                'orders' => true,
            ],
            [
                'key' => 'pharmacy',
                'title' => 'Pharmacy',
                'icon' => 'tabler-pill',
                'to' => 'pharmacy',
                'subject' => 'Pharmacy',
                'heading' => 'Pharmacy',
                'facility_type' => 'pharmacy',
                'orders' => true,
            ],
            [
                'key' => 'referrals',
                'title' => 'Referrals',
                'icon' => 'tabler-transfer',
                'to' => 'referrals',
                'subject' => 'Referral',
                'heading' => 'Network',
            ],
            [
                'key' => 'assistance',
                'title' => 'Assistance',
                'icon' => 'tabler-heartbeat',
                'to' => 'assistance',
                'subject' => 'AssistanceRequest',
                'heading' => 'Network',
            ],
            [
                'key' => 'ambulances',
                'title' => 'Ambulances',
                'icon' => 'tabler-ambulance',
                'to' => 'ambulances',
                'subject' => 'Ambulance',
                'heading' => 'Network',
            ],
            [
                'key' => 'billing',
                'title' => 'Billing',
                'icon' => 'tabler-receipt',
                'to' => 'billing',
                'subject' => 'Invoice',
                'heading' => 'Finance',
            ],
            [
                'key' => 'reports',
                'title' => 'Reports',
                'icon' => 'tabler-chart-bar',
                'to' => 'reports',
                'subject' => 'Report',
                'heading' => 'Finance',
            ],
            [
                'key' => 'admin',
                'title' => 'Overview',
                'icon' => 'tabler-layout-dashboard',
                'to' => 'admin',
                'subject' => 'User',
                'heading' => 'Administration',
            ],
            [
                'key' => 'departments',
                'title' => 'Departments',
                'icon' => 'tabler-building',
                'to' => 'admin-departments',
                'subject' => 'Department',
                'heading' => 'Administration',
            ],
            [
                'key' => 'facilities',
                'title' => 'Facilities',
                'icon' => 'tabler-building-community',
                'to' => 'facilities',
                'subject' => 'Facility',
                'heading' => 'Administration',
            ],
            [
                'key' => 'users',
                'title' => 'Users',
                'icon' => 'tabler-user-cog',
                'to' => 'admin-users',
                'subject' => 'User',
                'heading' => 'Administration',
            ],
            [
                'key' => 'roles',
                'title' => 'Roles',
                'icon' => 'tabler-shield',
                'to' => 'admin-roles',
                'subject' => 'Role',
                'heading' => 'Administration',
            ],
            [
                'key' => 'hospitals',
                'title' => 'Hospitals',
                'icon' => 'tabler-building-skyscraper',
                'to' => 'admin-hospitals',
                'subject' => 'Hospital',
                'heading' => 'Administration',
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return collect(self::all())->firstWhere('key', $key);
    }

    public static function findBySubject(string $subject): ?array
    {
        return collect(self::all())->firstWhere('subject', $subject);
    }

    public static function workspaces(): array
    {
        return collect(self::all())->map(fn (array $module) => [
            'value' => $module['to'],
            'title' => $module['title'],
        ])->unique('value')->values()->all();
    }

    public static function keysFor(User $user): array
    {
        return collect(self::all())
            ->filter(fn (array $module) => $user->hasPermission('read', $module['subject']))
            ->pluck('key')
            ->values()
            ->all();
    }

    public static function homeRoute(User $user): string
    {
        $workspace = $user->role?->workspace;

        if ($workspace) {
            $module = collect(self::all())->firstWhere('to', $workspace);
            if ($module && $user->hasPermission('read', $module['subject'])) {
                return $workspace;
            }
        }

        foreach (self::all() as $module) {
            if ($user->hasPermission('read', $module['subject'])) {
                return $module['to'];
            }
        }

        return 'not-authorized';
    }

    public static function navigation(User $user): array
    {
        $modules = $user->isPlatformAdmin()
            ? collect(self::all())->whereIn('key', ['hospitals', 'users', 'roles', 'reports'])
            : collect(self::all())->filter(fn (array $module) => $user->hasPermission('read', $module['subject']));

        $items = [];
        $currentHeading = null;

        foreach ($modules as $module) {
            if ($currentHeading !== $module['heading']) {
                $currentHeading = $module['heading'];
                $items[] = ['heading' => $currentHeading];
            }

            $items[] = [
                'title' => $module['title'],
                'icon' => ['icon' => $module['icon']],
                'to' => $module['to'],
                'action' => 'read',
                'subject' => $module['subject'],
            ];
        }

        return $items;
    }
}
