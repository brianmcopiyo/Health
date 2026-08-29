<?php

namespace Tests\Unit;

use App\Support\Reports\PdfEngine;
use App\Support\Reports\ReportValue;
use App\Support\Reports\XlsxEngine;
use Tests\TestCase;

class ReportEngineTest extends TestCase
{
    public function test_pdf_encodes_punctuation_without_question_marks(): void
    {
        $pdf = PdfEngine::render($this->document());

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringNotContainsString('???', $pdf);
        $this->assertStringContainsString('Riverside', $pdf);
        $this->assertStringContainsString('Emergency', $pdf);
        $this->assertStringContainsString('Executive summary', $pdf);
        $this->assertStringContainsString('Visual analysis', $pdf);
        $this->assertStringContainsString('Detailed records', $pdf);
        $this->assertStringContainsString('No data available for the selected period.', $pdf);
        $this->assertStringContainsString('Page 1 of', $pdf);
        $this->assertStringContainsString('MediaBox [0 0 792 612]', $pdf);
    }

    public function test_excel_workbook_has_summary_and_module_sheets(): void
    {
        $binary = XlsxEngine::render($this->document());
        $this->assertStringStartsWith('PK', $binary);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $summary = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertStringContainsString('Summary', $workbook);
        $this->assertStringContainsString('Executive summary', $workbook);
        $this->assertStringContainsString('Detailed records', $workbook);
        $this->assertStringContainsString('Reporting period', $summary);
        $this->assertStringContainsString('Department', $summary);
        $this->assertStringContainsString('autoFilter', $this->sheetXml($binary, 'xl/worksheets/sheet5.xml'));
    }

    public function test_value_formatting_covers_nulls_and_types(): void
    {
        $this->assertSame('—', ReportValue::format(null, 'text'));
        $this->assertSame('—', ReportValue::format('', 'number'));
        $this->assertSame('1,250', ReportValue::number(1250));
        $this->assertSame('12.50', ReportValue::currency(12.5));
        $this->assertSame('40%', ReportValue::percent(40));
        $this->assertSame('Completed', ReportValue::format('completed', 'status'));
        $this->assertSame('29 Aug 2026', ReportValue::date('2026-08-29'));
    }

    private function sheetXml(string $binary, string $name): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $xml = (string) $zip->getFromName($name);
        $zip->close();
        @unlink($tmp);

        return $xml;
    }

    private function document(): array
    {
        $headers = [
            ['title' => 'Patient', 'key' => 'patient', 'format' => 'text', 'align' => 'left'],
            ['title' => 'MRN', 'key' => 'mrn', 'format' => 'text', 'align' => 'left'],
            ['title' => 'Type', 'key' => 'type', 'format' => 'status', 'align' => 'left'],
            ['title' => 'Status', 'key' => 'status', 'format' => 'status', 'align' => 'left'],
            ['title' => 'Department', 'key' => 'department', 'format' => 'text', 'align' => 'left'],
            ['title' => 'Clinician', 'key' => 'clinician', 'format' => 'text', 'align' => 'left'],
            ['title' => 'Total', 'key' => 'total', 'format' => 'currency', 'align' => 'right'],
            ['title' => 'Opened', 'key' => 'when', 'format' => 'date', 'align' => 'left'],
        ];

        $rows = [];
        for ($i = 1; $i <= 40; $i++) {
            $rows[] = [
                ['raw' => 'Kwame Mensah '.$i, 'text' => 'Kwame Mensah '.$i, 'format' => 'text'],
                ['raw' => 'RGH-'.$i, 'text' => 'RGH-'.$i, 'format' => 'text'],
                ['raw' => 'emergency', 'text' => 'Emergency', 'format' => 'status'],
                ['raw' => $i % 7 === 0 ? null : 'completed', 'text' => $i % 7 === 0 ? '—' : 'Completed', 'format' => 'status'],
                ['raw' => 'Emergency', 'text' => 'Emergency', 'format' => 'text'],
                ['raw' => 'Dr. Abena Osei', 'text' => 'Dr. Abena Osei', 'format' => 'text'],
                ['raw' => $i % 5 === 0 ? null : 1250 + $i, 'text' => $i % 5 === 0 ? '—' : number_format(1250 + $i, 2), 'format' => 'currency'],
                ['raw' => '2026-08-29T10:00:00+00:00', 'text' => '29 Aug 2026', 'format' => 'date'],
            ];
        }

        return [
            'meta' => [
                'title' => 'Emergency',
                'kind' => 'Module report',
                'organization' => [
                    'name' => 'Riverside General Hospital',
                    'code' => 'RGH',
                    'city' => 'Accra',
                    'region' => 'Greater Accra',
                    'address' => '12 Harbour Road',
                    'phone' => '030 000 0000',
                ],
                'period' => [
                    'from' => '2026-07-31',
                    'to' => '2026-08-29',
                    'label' => '31 Jul 2026 – 29 Aug 2026',
                ],
                'generated_at' => '29 Aug 2026 12:00',
                'filters' => [
                    ['label' => 'Department', 'value' => 'Emergency'],
                    ['label' => 'Status', 'value' => 'Completed'],
                ],
                'modules' => ['emergency'],
            ],
            'sections' => [
                [
                    'key' => 'summary',
                    'title' => 'Executive summary',
                    'blocks' => [
                        ['type' => 'narrative', 'text' => 'Riverside General Hospital Emergency report for 31 Jul 2026 – 29 Aug 2026.'],
                        ['type' => 'kpis', 'items' => [
                            ['title' => 'Total', 'display' => '128', 'hint' => 'Visits in range', 'tone' => null],
                            ['title' => 'Completed', 'display' => '96', 'hint' => '75% of the filtered set', 'tone' => 'ok'],
                            ['title' => 'Pending', 'display' => '22', 'hint' => 'Waiting or in progress', 'tone' => 'warn'],
                            ['title' => 'Cancelled', 'display' => '10', 'hint' => 'Cancelled in range', 'tone' => 'danger'],
                        ]],
                    ],
                ],
                [
                    'key' => 'analysis',
                    'title' => 'Visual analysis',
                    'blocks' => [
                        ['type' => 'chart', 'title' => 'Daily encounters', 'chart' => 'line', 'items' => [
                            ['label' => '1 Aug', 'value' => 4],
                            ['label' => '2 Aug', 'value' => 7],
                            ['label' => '3 Aug', 'value' => 3],
                        ]],
                        ['type' => 'chart', 'title' => 'Status mix', 'chart' => 'donut', 'items' => [
                            ['label' => 'Completed', 'value' => 96],
                            ['label' => 'Pending', 'value' => 22],
                            ['label' => 'Cancelled', 'value' => 10],
                        ]],
                    ],
                ],
                [
                    'key' => 'lists',
                    'title' => 'Lists and exceptions',
                    'blocks' => [
                        ['type' => 'list', 'title' => 'Attention items', 'items' => [
                            ['title' => 'Waiting', 'value' => '8', 'tone' => 'warn'],
                        ]],
                        ['type' => 'empty', 'title' => 'Recommendations', 'message' => 'No data available for the selected period.'],
                    ],
                ],
                [
                    'key' => 'detail',
                    'title' => 'Detailed records',
                    'blocks' => [
                        [
                            'type' => 'table',
                            'title' => 'Emergency visits',
                            'headers' => $headers,
                            'rows' => $rows,
                            'total' => 40,
                            'wide' => true,
                        ],
                    ],
                ],
            ],
        ];
    }
}
