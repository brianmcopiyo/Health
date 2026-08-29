<?php

namespace App\Support\Reports;

use App\Models\Department;
use App\Models\Facility;
use App\Models\User;

class ReportComposer
{
    public static function make(User $user, ReportCriteria $criteria): array
    {
        $keys = $criteria->moduleKeys($user);
        $modules = [];
        $limit = count($keys) > 1 ? 250 : 2000;

        foreach ($keys as $key) {
            $moduleCriteria = $criteria->forSection($key);
            $builder = new ReportBuilder($user, $moduleCriteria);
            $payload = $builder->payload();
            $rows = $builder->exportRows($limit);
            $modules[] = self::module($payload, $rows);
        }

        $primary = $modules[0] ?? self::emptyModule($user, $criteria);

        return [
            'meta' => [
                'title' => count($modules) > 1 ? 'Hospital report' : ($primary['title'] ?? 'Report'),
                'kind' => count($modules) > 1 ? 'Complete operational report' : 'Module report',
                'organization' => $primary['organization'],
                'period' => $primary['period'],
                'generated_at' => $primary['generated_at'],
                'filters' => $primary['filters'],
                'modules' => array_column($modules, 'key'),
            ],
            'sections' => self::sections($modules, count($keys) > 1),
        ];
    }

    private static function module(array $payload, array $rows): array
    {
        $hospital = $payload['hospital'] ?? [];
        $range = $payload['range'] ?? ['from' => '', 'to' => ''];

        return [
            'key' => $payload['section'] ?? 'report',
            'title' => $payload['title'] ?? 'Report',
            'organization' => [
                'name' => $hospital['name'] ?? 'Hospital',
                'code' => $hospital['code'] ?? '',
                'city' => $hospital['city'] ?? '',
                'region' => $hospital['region'] ?? '',
                'address' => $hospital['address'] ?? '',
                'phone' => $hospital['phone'] ?? '',
            ],
            'period' => [
                'from' => $range['from'] ?? '',
                'to' => $range['to'] ?? '',
                'label' => ReportValue::period($range['from'] ?? '', $range['to'] ?? ''),
            ],
            'generated_at' => ReportValue::datetime($payload['generated_at'] ?? now()->toIso8601String()),
            'filters' => self::filters($payload['applied'] ?? []),
            'kpis' => self::kpis($payload['kpis'] ?? []),
            'comparisons' => $payload['comparisons'] ?? [],
            'charts' => self::charts($payload['charts'] ?? []),
            'exceptions' => $payload['exceptions'] ?? [],
            'activity' => $payload['activity'] ?? [],
            'table' => self::table($rows, $payload['table']['empty'] ?? 'No data available for the selected period.'),
        ];
    }

    private static function emptyModule(User $user, ReportCriteria $criteria): array
    {
        $probe = new ReportQuery($user, $criteria);

        return [
            'key' => $criteria->section,
            'title' => 'Report',
            'organization' => $probe->hospital(),
            'period' => [
                'from' => $criteria->from->toDateString(),
                'to' => $criteria->to->toDateString(),
                'label' => ReportValue::period($criteria->from->toDateString(), $criteria->to->toDateString()),
            ],
            'generated_at' => ReportValue::datetime(now()),
            'filters' => [],
            'kpis' => [],
            'comparisons' => [],
            'charts' => [],
            'exceptions' => [],
            'activity' => [],
            'table' => [
                'title' => 'Records',
                'headers' => [],
                'rows' => [],
                'empty' => 'No data available for the selected period.',
                'total' => 0,
            ],
        ];
    }

    private static function sections(array $modules, bool $complete): array
    {
        $sections = [];

        if ($complete) {
            $sections[] = [
                'key' => 'executive',
                'title' => 'Executive Summary',
                'blocks' => array_values(array_filter([
                    self::narrativeBlock($modules),
                    [
                        'type' => 'kpis',
                        'items' => self::digestKpis($modules),
                    ],
                ])),
            ];
        }

        foreach ($modules as $module) {
            $blocks = [];

            if ($module['kpis']) {
                $blocks[] = ['type' => 'kpis', 'items' => $module['kpis']];
            }

            if (! $complete && ($summary = self::moduleNarrative($module))) {
                array_unshift($blocks, $summary);
            }

            if ($module['comparisons']) {
                $blocks[] = [
                    'type' => 'list',
                    'title' => 'Versus prior period',
                    'variant' => 'comparisons',
                    'items' => array_map(fn (array $item) => [
                        'title' => $item['label'] ?? 'Metric',
                        'meta' => ReportValue::number($item['previous'] ?? 0).' prior',
                        'value' => ReportValue::number($item['current'] ?? 0),
                        'tone' => ((int) ($item['delta'] ?? 0)) > 0 ? 'ok' : (((int) ($item['delta'] ?? 0)) < 0 ? 'danger' : null),
                        'detail' => ((int) ($item['delta'] ?? 0) > 0 ? '+' : '').($item['delta'] ?? 0).'%',
                    ], $module['comparisons']),
                ];
            }

            foreach ($module['charts'] as $chart) {
                $blocks[] = $chart;
            }

            if ($module['exceptions']) {
                $blocks[] = [
                    'type' => 'list',
                    'title' => 'Attention items',
                    'variant' => 'exceptions',
                    'items' => array_map(fn (array $item) => [
                        'title' => $item['title'] ?? 'Item',
                        'value' => ReportValue::text($item['value'] ?? ''),
                        'tone' => $item['tone'] ?? null,
                    ], $module['exceptions']),
                ];
            }

            if ($module['activity']) {
                $blocks[] = [
                    'type' => 'list',
                    'title' => 'Recent activity',
                    'variant' => 'activity',
                    'items' => array_map(fn (array $item) => [
                        'title' => $item['title'] ?? 'Activity',
                        'meta' => $item['meta'] ?? '',
                        'value' => isset($item['status']) ? ReportCatalog::label((string) $item['status']) : '',
                        'tone' => $item['status'] ?? null,
                    ], $module['activity']),
                ];
            }

            $table = $module['table'];
            if (($table['rows'] ?? []) === []) {
                $blocks[] = [
                    'type' => 'empty',
                    'title' => $table['title'] ?? 'Records',
                    'message' => $table['empty'] ?? 'No data available for the selected period.',
                ];
            } else {
                $blocks[] = array_merge($table, ['type' => 'table']);
            }

            $sections[] = [
                'key' => $module['key'],
                'title' => $complete ? $module['title'] : 'Executive summary',
                'blocks' => $blocks,
            ];

            if (! $complete) {
                $rest = [];
                $first = array_shift($blocks);
                if ($first && ($first['type'] ?? '') === 'narrative') {
                    $second = array_shift($blocks);
                    $sections[count($sections) - 1]['blocks'] = array_values(array_filter([$first, $second]));
                } else {
                    $sections[count($sections) - 1]['blocks'] = $first ? [$first] : [];
                }

                $charts = array_values(array_filter($blocks, fn ($block) => ($block['type'] ?? '') === 'chart'));
                $lists = array_values(array_filter($blocks, fn ($block) => ($block['type'] ?? '') === 'list'));
                $tables = array_values(array_filter($blocks, fn ($block) => in_array($block['type'] ?? '', ['table', 'empty'], true)));

                if ($charts) {
                    $rest[] = ['key' => 'analysis', 'title' => 'Visual analysis', 'blocks' => $charts];
                }
                if ($lists) {
                    $rest[] = ['key' => 'attention', 'title' => 'Lists and exceptions', 'blocks' => $lists];
                }
                if ($tables) {
                    $rest[] = ['key' => 'detail', 'title' => 'Detailed records', 'blocks' => $tables];
                }

                return array_merge($sections, $rest);
            }
        }

        return $sections;
    }

    private static function narrativeBlock(array $modules): ?array
    {
        $primary = $modules[0] ?? null;
        if (! $primary) {
            return null;
        }

        $parts = [
            ($primary['organization']['name'] ?? 'The organization').' complete report for '.$primary['period']['label'].'.',
        ];

        $kpis = self::digestKpis($modules);
        foreach (array_slice($kpis, 0, 4) as $kpi) {
            $parts[] = ($kpi['title'] ?? 'Metric').' '.$kpi['display'].'.';
        }

        return [
            'type' => 'narrative',
            'text' => implode(' ', $parts),
        ];
    }

    private static function moduleNarrative(array $module): ?array
    {
        $kpis = $module['kpis'] ?? [];
        if ($kpis === []) {
            return null;
        }

        $parts = [
            ($module['organization']['name'] ?? 'The organization').' '.$module['title'].' report for '.$module['period']['label'].'.',
        ];
        foreach (array_slice($kpis, 0, 3) as $kpi) {
            $parts[] = ($kpi['title'] ?? 'Metric').' '.$kpi['display'].'.';
        }
        $delta = $module['comparisons'][0]['delta'] ?? null;
        if ($delta !== null) {
            $label = $module['comparisons'][0]['label'] ?? 'Volume';
            $parts[] = $label.' changed '.(((int) $delta > 0) ? '+' : '').$delta.'% versus the prior period.';
        }

        return [
            'type' => 'narrative',
            'text' => implode(' ', $parts),
        ];
    }

    private static function digestKpis(array $modules): array
    {
        $items = [];
        foreach ($modules as $module) {
            foreach (array_slice($module['kpis'] ?? [], 0, 2) as $kpi) {
                $items[] = $kpi + ['hint' => $module['title']];
            }
        }

        return array_slice($items, 0, 8);
    }

    private static function kpis(array $items): array
    {
        return array_map(function (array $kpi) {
            $format = ReportValue::infer((string) ($kpi['key'] ?? ''), $kpi['value'] ?? null);
            if (is_string($kpi['value'] ?? null) && ! is_numeric($kpi['value'])) {
                $format = 'text';
            }

            return [
                'key' => $kpi['key'] ?? '',
                'title' => $kpi['title'] ?? 'Metric',
                'value' => $kpi['value'] ?? null,
                'display' => ReportValue::format($kpi['value'] ?? null, $format),
                'hint' => $kpi['hint'] ?? '',
                'tone' => $kpi['tone'] ?? null,
                'format' => $format,
            ];
        }, $items);
    }

    private static function charts(array $charts): array
    {
        $out = [];
        foreach ($charts as $chart) {
            $items = array_values(array_filter(
                $chart['items'] ?? [],
                fn ($item) => is_array($item) && (isset($item['value']) || isset($item['label']))
            ));
            $numeric = array_map(fn ($item) => (float) ($item['value'] ?? 0), $items);
            $hasSignal = ($chart['type'] ?? '') === 'trend'
                ? $items !== []
                : array_sum($numeric) > 0;

            if (! $hasSignal) {
                continue;
            }

            $out[] = [
                'type' => 'chart',
                'key' => $chart['key'] ?? '',
                'title' => $chart['title'] ?? 'Chart',
                'chart' => self::chartKind($chart, $items),
                'items' => array_map(fn (array $item) => [
                    'label' => (string) ($item['label'] ?? ''),
                    'value' => (float) ($item['value'] ?? 0),
                    'date' => $item['date'] ?? null,
                ], $items),
            ];
        }

        return $out;
    }

    private static function chartKind(array $chart, array $items): string
    {
        $type = $chart['type'] ?? 'bars';
        $key = $chart['key'] ?? '';

        if ($type === 'trend') {
            return 'line';
        }

        $positive = count(array_filter($items, fn ($item) => (float) ($item['value'] ?? 0) > 0));
        if (in_array($key, ['status', 'types', 'sex', 'methods'], true) && $positive >= 2 && $positive <= 6) {
            return 'donut';
        }

        if (in_array($key, ['clinicians', 'departments', 'workload', 'roles'], true) || count($items) > 6) {
            return 'hbar';
        }

        return 'bar';
    }

    private static function table(array $rows, string $empty): array
    {
        $headers = array_map(function (array $header) {
            $key = (string) ($header['key'] ?? '');

            return [
                'title' => $header['title'] ?? ReportCatalog::label($key),
                'key' => $key,
                'format' => $header['format'] ?? ReportValue::infer($key, null),
                'align' => in_array($header['format'] ?? ReportValue::infer($key, null), ['number', 'currency', 'percent'], true) ? 'right' : 'left',
            ];
        }, $rows['headers'] ?? []);

        $mapped = [];
        foreach ($rows['items'] ?? [] as $item) {
            $cells = [];
            foreach ($headers as $header) {
                $raw = $item[$header['key']] ?? null;
                if (is_array($raw)) {
                    $raw = null;
                }
                $cells[] = [
                    'raw' => $raw,
                    'text' => ReportValue::format($raw, $header['format']),
                    'format' => $header['format'],
                ];
            }
            $mapped[] = $cells;
        }

        return [
            'title' => $rows['title'] ?? 'Records',
            'headers' => $headers,
            'rows' => $mapped,
            'empty' => $empty,
            'total' => (int) ($rows['total'] ?? count($mapped)),
            'wide' => count($headers) >= 7,
        ];
    }

    private static function filters(array $applied): array
    {
        $skip = ['from', 'to'];
        $items = [];

        foreach ($applied as $key => $value) {
            if (in_array($key, $skip, true) || $value === null || $value === '') {
                continue;
            }

            $items[] = [
                'label' => match ($key) {
                    'department_id' => 'Department',
                    'facility_id' => 'Facility',
                    'clinician_id' => 'Staff',
                    'patient_type' => 'Sex',
                    'kind' => 'Type',
                    default => ReportCatalog::label((string) $key),
                },
                'value' => self::filterValue((string) $key, (string) $value),
            ];
        }

        return $items;
    }

    private static function filterValue(string $key, string $value): string
    {
        if ($key === 'department_id') {
            return Department::query()->whereKey($value)->value('name') ?: $value;
        }

        if ($key === 'facility_id') {
            return Facility::query()->whereKey($value)->value('name') ?: $value;
        }

        if ($key === 'clinician_id') {
            return User::query()->whereKey($value)->value('name') ?: $value;
        }

        return ReportCatalog::label($value);
    }
}
