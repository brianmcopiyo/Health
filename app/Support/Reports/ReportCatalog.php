<?php

namespace App\Support\Reports;

use App\Models\Ambulance;
use App\Models\AmbulanceTrip;
use App\Models\AssistanceRequest;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\ServiceOrder;
use App\Models\User;

class ReportCatalog
{
    public static function sections(): array
    {
        return [
            'overview' => [
                'title' => 'Overview',
                'icon' => 'chart',
                'subjects' => [],
                'filters' => ['from', 'to', 'department_id', 'facility_id'],
            ],
            'patients' => [
                'title' => 'Patients',
                'icon' => 'users',
                'subjects' => ['Patient'],
                'filters' => ['from', 'to', 'department_id', 'facility_id', 'clinician_id', 'status', 'patient_type'],
                'statuses' => Patient::STATUSES,
                'types' => self::options(['female', 'male', 'other']),
                'type_key' => 'patient_type',
                'type_label' => 'Sex',
            ],
            'encounters' => [
                'title' => 'Clinical',
                'icon' => 'stethoscope',
                'subjects' => ['Opd', 'Emergency', 'Ward', 'Reception', 'Theatre'],
                'filters' => ['from', 'to', 'department_id', 'facility_id', 'clinician_id', 'status', 'kind'],
                'statuses' => Encounter::STATUSES,
                'types' => self::options(Encounter::TYPES),
                'type_key' => 'kind',
                'type_label' => 'Encounter type',
            ],
            'opd' => [
                'title' => 'OPD',
                'icon' => 'desk',
                'subjects' => ['Opd'],
                'filters' => ['from', 'to', 'department_id', 'facility_id', 'clinician_id', 'status', 'kind'],
                'statuses' => Encounter::STATUSES,
                'types' => self::options(['opd', 'follow_up']),
                'type_key' => 'kind',
                'type_label' => 'Visit type',
            ],
            'emergency' => [
                'title' => 'Emergency',
                'icon' => 'emergency',
                'subjects' => ['Emergency'],
                'filters' => ['from', 'to', 'department_id', 'facility_id', 'clinician_id', 'status'],
                'statuses' => Encounter::STATUSES,
            ],
            'wards' => [
                'title' => 'Inpatient',
                'icon' => 'hospital',
                'subjects' => ['Ward'],
                'filters' => ['from', 'to', 'department_id', 'facility_id', 'clinician_id', 'status'],
                'statuses' => Encounter::STATUSES,
            ],
            'beds' => [
                'title' => 'Beds & Capacity',
                'icon' => 'bed',
                'subjects' => ['Bed', 'Ward', 'Facility'],
                'filters' => ['from', 'to', 'department_id', 'facility_id', 'status'],
                'statuses' => Facility::STATUSES,
            ],
            'laboratory' => [
                'title' => 'Laboratory',
                'icon' => 'flask',
                'subjects' => ['Laboratory'],
                'filters' => ['from', 'to', 'department_id', 'facility_id', 'clinician_id', 'status'],
                'statuses' => ServiceOrder::STATUSES,
            ],
            'imaging' => [
                'title' => 'Imaging',
                'icon' => 'scan',
                'subjects' => ['Imaging'],
                'filters' => ['from', 'to', 'department_id', 'facility_id', 'clinician_id', 'status'],
                'statuses' => ServiceOrder::STATUSES,
            ],
            'pharmacy' => [
                'title' => 'Pharmacy',
                'icon' => 'pill',
                'subjects' => ['Pharmacy'],
                'filters' => ['from', 'to', 'department_id', 'clinician_id', 'status'],
                'statuses' => Prescription::STATUSES,
            ],
            'theatre' => [
                'title' => 'Theatre',
                'icon' => 'cut',
                'subjects' => ['Theatre'],
                'filters' => ['from', 'to', 'department_id', 'facility_id', 'clinician_id', 'status'],
                'statuses' => ServiceOrder::STATUSES,
            ],
            'referrals' => [
                'title' => 'Referrals',
                'icon' => 'transfer',
                'subjects' => ['Referral'],
                'filters' => ['from', 'to', 'department_id', 'clinician_id', 'status', 'kind'],
                'statuses' => Referral::STATUSES,
                'types' => [
                    ['value' => 'outgoing', 'title' => 'Outgoing'],
                    ['value' => 'incoming', 'title' => 'Incoming'],
                ],
                'type_key' => 'kind',
                'type_label' => 'Direction',
            ],
            'assistance' => [
                'title' => 'Assistance',
                'icon' => 'community',
                'subjects' => ['AssistanceRequest'],
                'filters' => ['from', 'to', 'facility_id', 'status', 'kind'],
                'statuses' => AssistanceRequest::STATUSES,
                'types' => self::options(AssistanceRequest::TYPES),
                'type_key' => 'kind',
                'type_label' => 'Request type',
            ],
            'ambulances' => [
                'title' => 'Ambulances',
                'icon' => 'ambulance',
                'subjects' => ['Ambulance'],
                'filters' => ['from', 'to', 'facility_id', 'clinician_id', 'status', 'kind'],
                'statuses' => AmbulanceTrip::STATUSES,
                'types' => self::options(Ambulance::STATUSES),
                'type_key' => 'kind',
                'type_label' => 'Vehicle status',
            ],
            'billing' => [
                'title' => 'Billing',
                'icon' => 'receipt',
                'subjects' => ['Invoice'],
                'filters' => ['from', 'to', 'department_id', 'status', 'kind'],
                'statuses' => Invoice::STATUSES,
                'types' => self::options(['cash', 'card', 'mobile_money', 'insurance']),
                'type_key' => 'kind',
                'type_label' => 'Payment method',
            ],
            'staff' => [
                'title' => 'Staff & Operations',
                'icon' => 'shield',
                'subjects' => ['User', 'Department'],
                'filters' => ['from', 'to', 'department_id', 'facility_id', 'status'],
                'statuses' => ['active', 'ended'],
            ],
        ];
    }

    public static function definition(string $section): ?array
    {
        return self::sections()[$section] ?? null;
    }

    public static function allows(User $user, string $section): bool
    {
        $definition = self::definition($section);
        if (! $definition || ! $user->hasPermission('read', 'Report')) {
            return false;
        }

        $subjects = $definition['subjects'] ?? [];
        if ($subjects === []) {
            return true;
        }

        foreach ($subjects as $subject) {
            if ($user->hasPermission('read', $subject)) {
                return true;
            }
        }

        return false;
    }

    public static function tabs(User $user): array
    {
        $tabs = [];
        foreach (self::sections() as $key => $section) {
            if (! self::allows($user, $key)) {
                continue;
            }

            $tabs[] = [
                'key' => $key,
                'title' => $section['title'],
                'icon' => $section['icon'],
            ];
        }

        return $tabs;
    }

    public static function schema(string $section): array
    {
        $definition = self::definition($section) ?? self::definition('overview');

        return [
            'filters' => $definition['filters'] ?? ['from', 'to'],
            'statuses' => array_map(fn (string $value) => ['value' => $value, 'title' => self::label($value)], $definition['statuses'] ?? []),
            'types' => $definition['types'] ?? [],
            'type_key' => $definition['type_key'] ?? 'kind',
            'type_label' => $definition['type_label'] ?? 'Type',
        ];
    }

    public static function label(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    private static function options(array $values): array
    {
        return array_map(fn (string $value) => [
            'value' => $value,
            'title' => self::label($value),
        ], $values);
    }
}
