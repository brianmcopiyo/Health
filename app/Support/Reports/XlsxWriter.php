<?php

namespace App\Support\Reports;

class XlsxWriter
{
    public static function build(array $document): string
    {
        if (isset($document['meta'], $document['sections'])) {
            return XlsxEngine::render($document);
        }

        return XlsxEngine::render([
            'meta' => [
                'title' => 'Report',
                'kind' => 'Report',
                'organization' => [],
                'period' => ['label' => '', 'from' => '', 'to' => ''],
                'generated_at' => '',
                'filters' => [],
            ],
            'sections' => [[
                'title' => 'Detail',
                'blocks' => [[
                    'type' => 'table',
                    'title' => 'Records',
                    'headers' => [],
                    'rows' => [],
                    'empty' => 'No data available for the selected period.',
                ]],
            ]],
        ]);
    }
}
