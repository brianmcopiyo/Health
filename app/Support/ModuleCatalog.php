<?php

namespace App\Support;

use App\Models\User;

class ModuleCatalog
{
    public static function all(): array
    {
        $sections = config('hms.navigation.sections', []);

        return [
            [
                'key' => 'admin',
                'title' => 'Dashboard',
                'icon' => 'tabler-layout-dashboard',
                'to' => 'admin',
                'subject' => null,
                'section' => 'home',
                'rank' => 1,
                'heading' => $sections['home']['title'] ?? 'Workspace',
            ],
            [
                'key' => 'patients',
                'title' => 'Patients',
                'icon' => 'tabler-users',
                'to' => 'patients',
                'subject' => 'Patient',
                'section' => 'care',
                'rank' => 1,
                'heading' => $sections['care']['title'] ?? 'Care',
            ],
            [
                'key' => 'reception',
                'title' => 'Reception',
                'icon' => 'tabler-desk',
                'to' => 'reception',
                'subject' => 'Reception',
                'section' => 'care',
                'rank' => 2,
                'heading' => $sections['care']['title'] ?? 'Care',
            ],
            [
                'key' => 'opd',
                'title' => 'OPD',
                'icon' => 'tabler-stethoscope',
                'to' => 'opd',
                'subject' => 'Opd',
                'section' => 'clinical',
                'rank' => 1,
                'heading' => $sections['clinical']['title'] ?? 'Clinical',
                'facility_type' => 'consultation-room',
                'encounter_type' => 'opd',
            ],
            [
                'key' => 'emergency',
                'title' => 'Emergency',
                'icon' => 'tabler-emergency-bed',
                'to' => 'emergency',
                'subject' => 'Emergency',
                'section' => 'clinical',
                'rank' => 2,
                'heading' => $sections['clinical']['title'] ?? 'Clinical',
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
                'section' => 'inpatient',
                'rank' => 1,
                'heading' => $sections['inpatient']['title'] ?? 'Inpatient',
                'facility_type' => 'ward',
            ],
            [
                'key' => 'beds',
                'title' => 'Beds',
                'icon' => 'tabler-bed',
                'to' => 'beds',
                'subject' => 'Bed',
                'section' => 'inpatient',
                'rank' => 2,
                'heading' => $sections['inpatient']['title'] ?? 'Inpatient',
                'facility_type' => 'bed',
                'assignments' => true,
            ],
            [
                'key' => 'laboratory',
                'title' => 'Laboratory',
                'icon' => 'tabler-test-pipe',
                'to' => 'laboratory',
                'subject' => 'Laboratory',
                'section' => 'diagnostics',
                'rank' => 1,
                'heading' => $sections['diagnostics']['title'] ?? 'Diagnostics',
                'facility_type' => 'lab',
                'orders' => true,
            ],
            [
                'key' => 'imaging',
                'title' => 'Imaging',
                'icon' => 'tabler-scan',
                'to' => 'imaging',
                'subject' => 'Imaging',
                'section' => 'diagnostics',
                'rank' => 2,
                'heading' => $sections['diagnostics']['title'] ?? 'Diagnostics',
                'facility_type' => 'imaging',
                'orders' => true,
            ],
            [
                'key' => 'pharmacy',
                'title' => 'Pharmacy',
                'icon' => 'tabler-pill',
                'to' => 'pharmacy',
                'subject' => 'Pharmacy',
                'section' => 'pharmacy',
                'rank' => 1,
                'heading' => $sections['pharmacy']['title'] ?? 'Pharmacy',
                'facility_type' => 'pharmacy',
                'orders' => true,
            ],
            [
                'key' => 'inventory',
                'title' => 'Inventory',
                'icon' => 'tabler-packages',
                'to' => 'inventory',
                'subject' => 'Inventory',
                'section' => 'pharmacy',
                'rank' => 2,
                'heading' => $sections['pharmacy']['title'] ?? 'Pharmacy',
            ],
            [
                'key' => 'theatre',
                'title' => 'Theatre',
                'icon' => 'tabler-cut',
                'to' => 'theatre',
                'subject' => 'Theatre',
                'section' => 'theatre',
                'rank' => 1,
                'heading' => $sections['theatre']['title'] ?? 'Theatre',
                'facility_type' => 'theatre',
                'orders' => true,
            ],
            [
                'key' => 'referrals',
                'title' => 'Referrals',
                'icon' => 'tabler-transfer',
                'to' => 'referrals',
                'subject' => 'Referral',
                'section' => 'network',
                'rank' => 1,
                'heading' => $sections['network']['title'] ?? 'Network',
            ],
            [
                'key' => 'assistance',
                'title' => 'Assistance',
                'icon' => 'tabler-heartbeat',
                'to' => 'assistance',
                'subject' => 'AssistanceRequest',
                'section' => 'network',
                'rank' => 2,
                'heading' => $sections['network']['title'] ?? 'Network',
            ],
            [
                'key' => 'ambulances',
                'title' => 'Ambulances',
                'icon' => 'tabler-ambulance',
                'to' => 'ambulances',
                'subject' => 'Ambulance',
                'section' => 'network',
                'rank' => 3,
                'heading' => $sections['network']['title'] ?? 'Network',
            ],
            [
                'key' => 'billing',
                'title' => 'Billing',
                'icon' => 'tabler-receipt',
                'to' => 'billing',
                'subject' => 'Invoice',
                'section' => 'finance',
                'rank' => 1,
                'heading' => $sections['finance']['title'] ?? 'Finance',
            ],
            [
                'key' => 'pricing',
                'title' => 'Pricing',
                'icon' => 'tabler-tags',
                'to' => 'pricing',
                'subject' => 'PriceList',
                'section' => 'finance',
                'rank' => 2,
                'heading' => $sections['finance']['title'] ?? 'Finance',
            ],
            [
                'key' => 'billing-reports',
                'title' => 'Sales reports',
                'icon' => 'tabler-chart-dots',
                'to' => 'billing-reports',
                'subject' => 'Invoice',
                'section' => 'finance',
                'rank' => 3,
                'heading' => $sections['finance']['title'] ?? 'Finance',
            ],
            [
                'key' => 'reports',
                'title' => 'Reports',
                'icon' => 'tabler-chart-bar',
                'to' => 'reports',
                'subject' => 'Report',
                'section' => 'finance',
                'rank' => 4,
                'heading' => $sections['finance']['title'] ?? 'Finance',
            ],
            [
                'key' => 'departments',
                'title' => 'Departments',
                'icon' => 'tabler-building',
                'to' => 'admin-departments',
                'subject' => 'Department',
                'section' => 'admin',
                'rank' => 1,
                'heading' => $sections['admin']['title'] ?? 'Administration',
            ],
            [
                'key' => 'facilities',
                'title' => 'Facilities',
                'icon' => 'tabler-building-community',
                'to' => 'facilities',
                'subject' => 'Facility',
                'section' => 'admin',
                'rank' => 2,
                'heading' => $sections['admin']['title'] ?? 'Administration',
            ],
            [
                'key' => 'users',
                'title' => 'Users',
                'icon' => 'tabler-user-cog',
                'to' => 'admin-users',
                'subject' => 'User',
                'section' => 'admin',
                'rank' => 3,
                'heading' => $sections['admin']['title'] ?? 'Administration',
            ],
            [
                'key' => 'roles',
                'title' => 'Roles',
                'icon' => 'tabler-shield',
                'to' => 'admin-roles',
                'subject' => 'Role',
                'section' => 'admin',
                'rank' => 4,
                'heading' => $sections['admin']['title'] ?? 'Administration',
            ],
            [
                'key' => 'hospitals',
                'title' => 'Hospitals',
                'icon' => 'tabler-building-skyscraper',
                'to' => 'admin-hospitals',
                'subject' => 'Hospital',
                'section' => 'admin',
                'rank' => 5,
                'heading' => $sections['admin']['title'] ?? 'Administration',
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
        return collect(self::visibleFor($user))
            ->pluck('key')
            ->values()
            ->all();
    }

    public static function homeRoute(User $user): string
    {
        $workspace = $user->role?->workspace;

        if ($workspace) {
            $module = collect(self::all())->firstWhere('to', $workspace);
            if ($module && self::canAccess($user, $module)) {
                return $workspace;
            }
        }

        foreach (self::visibleFor($user) as $module) {
            if ($module['key'] !== 'admin') {
                return $module['to'];
            }
        }

        return collect(self::visibleFor($user))->first()['to'] ?? 'not-authorized';
    }

    public static function navigation(User $user): array
    {
        $items = [];

        foreach (self::groupsFor($user) as $group) {
            if ($group['heading']) {
                $items[] = ['heading' => $group['heading']];
            }

            foreach ($group['items'] as $module) {
                $items[] = [
                    'title' => $module['title'],
                    'icon' => ['icon' => $module['icon']],
                    'to' => $module['to'],
                    'action' => 'read',
                    'subject' => $module['subject'],
                    'section' => $module['section'],
                ];
            }
        }

        return $items;
    }

    public static function groupsFor(User $user): array
    {
        $sections = config('hms.navigation.sections', []);
        $workspaceRank = (int) config('hms.navigation.workspace_section_rank', 15);
        $workspace = $user->role?->workspace;
        $homeSection = collect(self::all())->firstWhere('to', $workspace)['section'] ?? null;

        $grouped = [];

        foreach (self::visibleFor($user) as $module) {
            $section = $module['section'];
            if (! isset($grouped[$section])) {
                $meta = $sections[$section] ?? ['title' => $module['heading'] ?? null, 'rank' => 99];
                $rank = (int) ($meta['rank'] ?? 99);
                if ($homeSection === $section && ! in_array($section, ['home', 'admin'], true)) {
                    $rank = $workspaceRank;
                }
                $grouped[$section] = [
                    'heading' => $meta['title'] ?? null,
                    'section' => $section,
                    'rank' => $rank,
                    'items' => [],
                ];
            }

            $grouped[$section]['items'][] = $module;
        }

        return collect($grouped)
            ->filter(fn (array $group) => count($group['items']) > 0)
            ->sortBy(fn (array $group) => [$group['rank'], $group['section']])
            ->map(function (array $group) {
                $group['items'] = collect($group['items'])->sortBy('rank')->values()->all();

                return $group;
            })
            ->values()
            ->all();
    }

    public static function visibleFor(User $user): array
    {
        $always = config('hms.navigation.always_visible', ['admin']);
        $platform = config('hms.navigation.platform_keys', ['admin', 'reports', 'users', 'roles', 'hospitals']);

        return collect(self::all())
            ->filter(function (array $module) use ($user, $always, $platform) {
                if ($user->isPlatformAdmin()) {
                    return in_array($module['key'], $platform, true);
                }

                if (in_array($module['key'], $always, true) || empty($module['subject'])) {
                    return true;
                }

                return $user->hasPermission('read', $module['subject']);
            })
            ->values()
            ->all();
    }

    private static function canAccess(User $user, array $module): bool
    {
        if (in_array($module['key'], config('hms.navigation.always_visible', ['admin']), true) || empty($module['subject'])) {
            return true;
        }

        return $user->hasPermission('read', $module['subject']);
    }
}
