<?php

namespace App\Support\Exports;

use App\Models\User;
use App\Support\Audit;
use App\Support\Reports\ReportValue;
use App\Support\Reports\XlsxEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DatasetExport
{
    public const LIMIT = 5000;

    public const RECORD_LIMIT = 200;

    public static function download(User $user, string $dataset, Request $request): Response
    {
        $format = strtolower((string) $request->string('format', 'xlsx'));
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 422, 'Unsupported export format.');

        $definition = ExportCatalog::definition($dataset);
        abort_unless($definition, 404, 'Unknown export.');
        $allowed = ! empty($definition['authorize']) && is_callable($definition['authorize'])
            ? (bool) $definition['authorize']($user, $request)
            : $user->hasPermission('read', $definition['subject']);
        abort_unless($allowed, 403, 'This action is unauthorized.');

        $document = $definition['kind'] === 'report'
            ? self::reportDocument($user, $definition, $request)
            : self::datasetDocument($user, $definition, $request);

        if ($user->hospital) {
            Audit::exported($user->hospital, [
                'dataset' => $dataset,
                'format' => $format,
                'rows' => $document['meta']['row_count'] ?? 0,
            ]);
        }

        $filename = self::filename($document, $format);

        if ($format === 'xlsx') {
            return response(XlsxEngine::render($document), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        return response(ListPdfEngine::render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private static function datasetDocument(User $user, array $definition, Request $request): array
    {
        $query = $definition['query']($request, $user);
        if ($ids = self::ids($request)) {
            $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids);
        }

        if (ExportRelations::supports($query->getModel()::class)) {
            return self::treeDocument($user, $definition, $request, $query);
        }

        return self::listDocument($user, $definition, $request, $query);
    }

    private static function listDocument(User $user, array $definition, Request $request, $query): array
    {
        $total = (clone $query)->toBase()->getCountForPagination();
        $rows = $query->limit(self::LIMIT)->get()->map($definition['map'])->all();

        $filters = self::filters($request, $definition);
        $headers = self::headers($definition['columns']);
        $table = self::table($definition['title'], $headers, $rows, $total);

        return self::document($user, $definition, $filters, [
            [
                'key' => $definition['key'],
                'title' => $definition['title'],
                'blocks' => [$table],
            ],
        ], $total);
    }

    private static function treeDocument(User $user, array $definition, Request $request, $query): array
    {
        $total = (clone $query)->toBase()->getCountForPagination();
        $models = $query->limit(ExportTree::PARENT_LIMIT)->get();
        $filters = self::filters($request, $definition);
        if ($total > ExportTree::PARENT_LIMIT) {
            $filters[] = ['label' => 'Records included', 'value' => ExportTree::PARENT_LIMIT.' of '.$total];
        }

        $headers = self::headers($definition['columns']);
        $lines = [];
        $last = count($models) - 1;
        foreach ($models as $index => $model) {
            $lines[] = [
                'kind' => 'parent',
                'cells' => self::rowCells($headers, ($definition['map'])($model)),
            ];
            self::appendGroups($lines, ExportTree::node($model, $user)['groups'] ?? []);
            if ($index < $last) {
                $lines[] = ['kind' => 'gap'];
            }
        }

        $width = max(1, count($headers));
        foreach ($lines as $line) {
            if (($line['kind'] ?? '') === 'headers') {
                $width = max($width, count($line['headers'] ?? []));
            } elseif (isset($line['cells'])) {
                $width = max($width, count($line['cells']));
            }
        }

        return self::document($user, $definition, $filters, [[
            'key' => $definition['key'],
            'title' => $definition['title'],
            'blocks' => [[
                'type' => 'hierarchy',
                'headers' => $headers,
                'width' => $width,
                'lines' => $lines,
                'empty' => 'No records match the current filters.',
            ]],
        ]], $total, null, null, 'hierarchy');
    }

    private static function appendGroups(array &$lines, array $groups): void
    {
        foreach ($groups as $group) {
            $rows = $group['rows'] ?? [];
            $records = $group['records'] ?? [];
            if ($rows === [] && $records === []) {
                continue;
            }

            $headers = self::headers($group['columns'] ?? []);
            $mapped = [];
            foreach ($rows as $row) {
                $mapped[] = self::rowCells($headers, $row);
            }

            $lines[] = ['kind' => 'group', 'title' => $group['title'] ?? 'Records'];
            $lines[] = ['kind' => 'headers', 'headers' => $headers];

            if ($records !== []) {
                foreach ($mapped as $index => $cells) {
                    $lines[] = ['kind' => 'row', 'cells' => $cells];
                    if (isset($records[$index])) {
                        self::appendGroups($lines, $records[$index]['groups'] ?? []);
                    }
                }
                for ($index = count($mapped); $index < count($records); $index++) {
                    $lines[] = [
                        'kind' => 'parent',
                        'cells' => [[
                            'raw' => $records[$index]['title'] ?? '',
                            'text' => (string) ($records[$index]['title'] ?? ''),
                            'format' => 'text',
                        ]],
                    ];
                    self::appendGroups($lines, $records[$index]['groups'] ?? []);
                }
                continue;
            }

            foreach ($mapped as $cells) {
                $lines[] = ['kind' => 'row', 'cells' => $cells];
            }
        }
    }

    private static function rowCells(array $headers, array $row): array
    {
        $cells = [];
        foreach ($headers as $header) {
            $raw = $row[$header['key']] ?? null;
            $cells[] = [
                'raw' => $raw,
                'text' => ReportValue::format($raw, $header['format']),
                'format' => $header['format'],
            ];
        }

        return $cells;
    }

    private static function reportDocument(User $user, array $definition, Request $request): array
    {
        $payload = $definition['report']($request, $user);
        $filters = self::filters($request, $definition);
        $blocks = [];
        foreach ($payload['tables'] ?? [] as $table) {
            $headers = self::headers($table['columns']);
            $blocks[] = self::table($table['title'], $headers, $table['rows'] ?? [], count($table['rows'] ?? []));
        }

        $from = $payload['range']['from'] ?? $request->input('from');
        $to = $payload['range']['to'] ?? $request->input('to');

        return self::document($user, $definition, $filters, [
            [
                'key' => $definition['key'],
                'title' => $definition['title'],
                'blocks' => $blocks,
            ],
        ], (int) ($payload['summary']['invoices'] ?? $payload['summary']['sales_count'] ?? 0), $from, $to);
    }

    private static function document(User $user, array $definition, array $filters, array $sections, int $rowCount, ?string $from = null, ?string $to = null, string $layout = 'list'): array
    {
        $hospital = $user->hospital;
        $from = $from ?: request()->input('from');
        $to = $to ?: request()->input('to');
        $generated = now()->timezone(config('app.timezone'))->format('j M Y H:i');

        return [
            'meta' => [
                'title' => $definition['title'],
                'kind' => $definition['label'] ?? 'Data export',
                'organization' => [
                    'name' => $hospital?->name ?? 'Hospital',
                    'code' => $hospital?->code ?? 'HMS',
                    'city' => $hospital?->city,
                    'region' => $hospital?->region,
                    'address' => $hospital?->address,
                    'phone' => $hospital?->phone,
                ],
                'period' => [
                    'from' => $from,
                    'to' => $to,
                    'label' => $from || $to ? trim(($from ?: '…').' to '.($to ?: '…')) : 'Current register',
                ],
                'generated_at' => $generated,
                'layout' => $layout,
                'filters' => $filters,
                'modules' => [$definition['key']],
                'row_count' => $rowCount,
            ],
            'sections' => $sections,
        ];
    }

    private static function headers(array $columns): array
    {
        return array_map(fn ($column) => [
            'title' => $column['title'],
            'key' => $column['key'],
            'format' => $column['format'] ?? 'text',
            'align' => in_array($column['format'] ?? 'text', ['number', 'currency', 'percent'], true) ? 'right' : 'left',
        ], $columns);
    }

    private static function table(string $title, array $headers, array $rows, int $total, string $empty = 'No records match the current filters.'): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $header) {
                $raw = $row[$header['key']] ?? null;
                $cells[] = [
                    'raw' => $raw,
                    'text' => ReportValue::format($raw, $header['format']),
                    'format' => $header['format'],
                ];
            }
            $mapped[] = $cells;
        }

        return [
            'type' => 'table',
            'title' => $title,
            'headers' => $headers,
            'rows' => $mapped,
            'empty' => $empty,
            'total' => $total,
            'wide' => count($headers) >= 7,
        ];
    }

    private static function filters(Request $request, array $definition): array
    {
        $labels = $definition['filter_labels'] ?? [];
        $items = [];
        foreach ($labels as $key => $label) {
            $value = $request->input($key);
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $items[] = ['label' => $label, 'value' => (string) $value];
        }

        if ($ids = self::ids($request)) {
            $items[] = ['label' => 'Selected records', 'value' => (string) count($ids)];
        }

        return $items;
    }

    private static function ids(Request $request): array
    {
        $ids = $request->input('ids', []);
        if (is_string($ids)) {
            $ids = array_filter(explode(',', $ids));
        }

        return array_values(array_filter((array) $ids));
    }

    private static function filename(array $document, string $format): string
    {
        $code = $document['meta']['organization']['code'] ?? 'HMS';
        $title = $document['meta']['title'] ?? 'export';
        $stamp = now()->format('Ymd-Hi');

        return Str::slug($code.'-'.$title.'-'.$stamp).'.'.$format;
    }
}
