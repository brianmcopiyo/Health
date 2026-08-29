<?php

namespace App\Support\Reports;

use ZipArchive;

class XlsxEngine
{
    public static function render(array $document): string
    {
        $sheets = self::sheets($document);
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', self::workbook($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels(count($sheets)));
        $zip->addFromString('xl/styles.xml', self::styles());
        foreach (array_values($sheets) as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', self::sheet($sheet));
        }
        $zip->close();
        $binary = file_get_contents($tmp);
        @unlink($tmp);

        return $binary === false ? '' : $binary;
    }

    private static function sheets(array $document): array
    {
        $meta = $document['meta'] ?? [];
        $org = $meta['organization'] ?? [];
        $summary = [
            'name' => 'Summary',
            'freeze' => 8,
            'filter' => null,
            'rows' => [
                self::row([[($org['name'] ?? 'Hospital').' — '.($meta['title'] ?? 'Report'), 'title']], 3),
                self::row([[$meta['kind'] ?? 'Report', 'muted']]),
                self::row([['Reporting period', 'label'], [$meta['period']['label'] ?? '', 'text']]),
                self::row([['Generated', 'label'], [$meta['generated_at'] ?? '', 'text']]),
                self::row([['Organization', 'label'], [trim(($org['city'] ?? '').' '.($org['region'] ?? '')), 'text']]),
                self::row([]),
                self::row([['Applied filters', 'heading']], 2),
            ],
        ];

        $filters = $meta['filters'] ?? [];
        if ($filters === []) {
            $summary['rows'][] = self::row([['No additional filters were applied.', 'muted']]);
        } else {
            foreach ($filters as $filter) {
                $summary['rows'][] = self::row([[$filter['label'] ?? '', 'label'], [$filter['value'] ?? '', 'text']]);
            }
        }

        $sheets = [$summary];
        $used = ['summary' => true];

        $layout = $meta['layout'] ?? '';
        $simple = $layout === 'list';

        if ($layout === 'hierarchy') {
            $block = self::hierarchyBlock($document);
            $width = max(1, (int) ($block['width'] ?? 1), count($block['headers'] ?? []));
            $rows = [];
            $merges = [];
            if (($block['lines'] ?? []) === []) {
                $rows[] = self::row([[$block['empty'] ?? 'No records match the current filters.', 'muted']]);
            } else {
                self::appendHierarchyLine($rows, $merges, [
                    'kind' => 'headers',
                    'headers' => $block['headers'] ?? [],
                ], $width);
                foreach ($block['lines'] ?? [] as $line) {
                    self::appendHierarchyLine($rows, $merges, $line, $width);
                }
            }
            $sheets[] = [
                'name' => self::sheetName($meta['title'] ?? 'Records', $used),
                'freeze' => 1,
                'filter' => null,
                'merges' => $merges,
                'width' => $width,
                'rows' => $rows,
            ];

            return $sheets;
        }

        foreach ($document['sections'] ?? [] as $section) {
            $rows = [
                self::row([[$section['title'] ?? 'Section', 'title']], 3),
                self::row([]),
            ];
            $filterAt = null;
            foreach ($section['blocks'] ?? [] as $block) {
                $type = $block['type'] ?? '';
                if ($simple && ! in_array($type, ['table', 'empty'], true)) {
                    continue;
                }
                if ($type === 'narrative') {
                    $rows[] = self::row([[$block['text'] ?? '', 'muted']]);
                    $rows[] = self::row([]);
                    continue;
                }
                if ($type === 'kpis') {
                    $rows[] = self::row([['Key performance indicators', 'heading']], 2);
                    $rows[] = self::row([['Metric', 'header'], ['Value', 'header'], ['Detail', 'header']]);
                    foreach ($block['items'] ?? [] as $kpi) {
                        $rows[] = self::row([
                            [$kpi['title'] ?? '', 'text'],
                            [$kpi['value'] ?? $kpi['display'] ?? '', $kpi['format'] ?? 'text'],
                            [$kpi['hint'] ?? '', 'muted'],
                        ]);
                    }
                    $rows[] = self::row([]);
                    continue;
                }
                if ($type === 'chart') {
                    $rows[] = self::row([[$block['title'] ?? 'Chart', 'heading']], 2);
                    $rows[] = self::row([['Label', 'header'], ['Value', 'header']]);
                    foreach ($block['items'] ?? [] as $item) {
                        $rows[] = self::row([
                            [$item['label'] ?? '', 'text'],
                            [$item['value'] ?? 0, 'number'],
                        ]);
                    }
                    $rows[] = self::row([]);
                    continue;
                }
                if ($type === 'list') {
                    $rows[] = self::row([[$block['title'] ?? 'List', 'heading']], 2);
                    $rows[] = self::row([['Item', 'header'], ['Detail', 'header'], ['Value', 'header']]);
                    foreach ($block['items'] ?? [] as $item) {
                        $rows[] = self::row([
                            [$item['title'] ?? '', 'text'],
                            [$item['meta'] ?? $item['detail'] ?? '', 'muted'],
                            [$item['value'] ?? '', 'text'],
                        ]);
                    }
                    $rows[] = self::row([]);
                    continue;
                }
                if ($type === 'empty') {
                    $rows[] = self::row([[$block['title'] ?? 'Records', 'heading']], 2);
                    $rows[] = self::row([[$block['message'] ?? 'No data available for the selected period.', 'muted']]);
                    $rows[] = self::row([]);
                    continue;
                }
                if ($type === 'table') {
                    $rows[] = self::row([[$block['title'] ?? 'Records', 'heading']], 2);
                    $headers = $block['headers'] ?? [];
                    $rows[] = self::row(array_map(fn ($header) => [$header['title'] ?? '', 'header'], $headers));
                    $filterAt = count($rows);
                    foreach ($block['rows'] ?? [] as $line) {
                        $rows[] = self::row(array_map(fn ($cell) => [
                            $cell['raw'] ?? $cell['text'] ?? '',
                            $cell['format'] ?? 'text',
                        ], $line));
                    }
                    if (($block['rows'] ?? []) === []) {
                        $rows[] = self::row([[$block['empty'] ?? 'No data available for the selected period.', 'muted']]);
                    }
                    $rows[] = self::row([]);
                }
            }

            $sheets[] = [
                'name' => self::sheetName($section['title'] ?? 'Section', $used),
                'freeze' => 3,
                'filter' => $filterAt,
                'rows' => $rows,
            ];
        }

        return $sheets;
    }

    private static function hierarchyBlock(array $document): array
    {
        foreach ($document['sections'] ?? [] as $section) {
            foreach ($section['blocks'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'hierarchy') {
                    return $block;
                }
            }
        }

        return ['headers' => [], 'width' => 1, 'lines' => [], 'empty' => 'No records match the current filters.'];
    }

    private static function appendHierarchyLine(array &$rows, array &$merges, array $line, int $width): void
    {
        $kind = $line['kind'] ?? '';
        $excelRow = count($rows) + 1;

        if ($kind === 'group') {
            $rows[] = self::row([[(string) ($line['title'] ?? ''), 'group']]);
            if ($width > 1) {
                $merges[] = 'A'.$excelRow.':'.self::column($width - 1).$excelRow;
            }

            return;
        }

        if ($kind === 'gap') {
            $cells = [];
            for ($i = 0; $i < $width; $i++) {
                $cells[] = ['', 'gap'];
            }
            $rows[] = self::row($cells);

            return;
        }

        if ($kind === 'headers') {
            $cells = array_map(fn ($header) => [$header['title'] ?? '', 'header'], $line['headers'] ?? []);
            if ($cells === []) {
                $cells[] = ['', 'header'];
            }
            $rows[] = self::row($cells);
            if ($width > count($cells)) {
                $merges[] = self::column(count($cells) - 1).$excelRow.':'.self::column($width - 1).$excelRow;
            }

            return;
        }

        if ($kind === 'parent' || $kind === 'row') {
            $style = $kind === 'parent' ? 'parent' : 'cell';
            $cells = [];
            foreach ($line['cells'] ?? [] as $cell) {
                $format = $kind === 'parent' ? 'parent' : self::borderedFormat($cell['format'] ?? 'text');
                $cells[] = [$cell['raw'] ?? $cell['text'] ?? '', $format];
            }
            if ($cells === []) {
                $cells[] = ['', $style];
            }
            $rows[] = self::row($cells);
            if ($width > count($cells)) {
                $merges[] = self::column(count($cells) - 1).$excelRow.':'.self::column($width - 1).$excelRow;
            }
        }
    }

    private static function borderedFormat(string $format): string
    {
        return match ($format) {
            'number' => 'cell-number',
            'currency' => 'cell-currency',
            'percent' => 'cell-percent',
            'date' => 'cell-date',
            default => 'cell',
        };
    }

    private static function row(array $cells, int $height = 0): array
    {
        return ['cells' => $cells, 'height' => $height];
    }

    private static function sheetName(string $name, array &$used): string
    {
        $base = trim(preg_replace('/[\\\\\\/\\*\\?\\:\\[\\]]+/', ' ', $name) ?? 'Sheet');
        $base = mb_substr($base === '' ? 'Sheet' : $base, 0, 28);
        $candidate = $base;
        $i = 2;
        while (isset($used[strtolower($candidate)])) {
            $candidate = mb_substr($base, 0, 26).' '.$i;
            $i++;
        }
        $used[strtolower($candidate)] = true;

        return $candidate;
    }

    private static function workbook(array $sheets): string
    {
        $entries = '';
        foreach (array_values($sheets) as $index => $sheet) {
            $entries .= '<sheet name="'.self::xml($sheet['name']).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$entries.'</sheets></workbook>';
    }

    private static function workbookRels(int $count): string
    {
        $rels = '';
        for ($i = 1; $i <= $count; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private static function contentTypes(int $count): string
    {
        $overrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $count; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .$overrides
            .'</Types>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="3">'
            .'<numFmt numFmtId="164" formatCode="#,##0"/>'
            .'<numFmt numFmtId="165" formatCode="#,##0.00"/>'
            .'<numFmt numFmtId="166" formatCode="0%"/>'
            .'</numFmts>'
            .'<fonts count="5">'
            .'<font><sz val="11"/><color rgb="FF102A32"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FF0B4F4D"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="12"/><color rgb="FF0F6F6C"/><name val="Calibri"/></font>'
            .'<font><sz val="11"/><color rgb="FF6B7C82"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="6">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0F6F6C"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEFEAE0"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFD6E8E6"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFB8D4D1"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'
            .'<left style="thin"><color rgb="FF8B8478"/></left>'
            .'<right style="thin"><color rgb="FF8B8478"/></right>'
            .'<top style="thin"><color rgb="FF8B8478"/></top>'
            .'<bottom style="thin"><color rgb="FF8B8478"/></bottom>'
            .'<diagonal/>'
            .'</border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="18">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="14" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private static function sheet(array $sheet): string
    {
        $rows = $sheet['rows'] ?? [];
        $widths = [];
        $xml = '';
        foreach (array_values($rows) as $r => $row) {
            $height = ! empty($row['height']) ? ' ht="'.($row['height'] * 6).'" customHeight="1"' : '';
            $xml .= '<row r="'.($r + 1).'"'.$height.'>';
            foreach (array_values($row['cells'] ?? []) as $c => $cell) {
                $xml .= self::cell($c, $r, $cell);
                $text = is_array($cell) ? (string) ($cell[0] ?? '') : (string) $cell;
                $widths[$c] = max($widths[$c] ?? 12, min(42, mb_strlen($text) + 4));
            }
            $xml .= '</row>';
        }

        $cols = '';
        foreach ($widths as $index => $width) {
            $cols .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        if (isset($sheet['width'])) {
            for ($i = 0; $i < (int) $sheet['width']; $i++) {
                $widths[$i] = max($widths[$i] ?? 16, 16);
            }
            $cols = '';
            foreach ($widths as $index => $width) {
                $cols .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
            }
        }

        $freeze = (int) ($sheet['freeze'] ?? 0);
        $filter = $sheet['filter'] ?? null;
        $extra = '';
        if ($freeze > 0) {
            $extra .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$freeze.'" topLeftCell="A'.($freeze + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        }
        if ($filter && $widths) {
            $last = self::column(max(0, count($widths) - 1));
            $extra .= '<autoFilter ref="A'.$filter.':'.$last.count($rows).'"/>';
        }

        $merges = $sheet['merges'] ?? [];
        $mergeXml = '';
        if ($merges) {
            $mergeXml = '<mergeCells count="'.count($merges).'">';
            foreach ($merges as $ref) {
                $mergeXml .= '<mergeCell ref="'.$ref.'"/>';
            }
            $mergeXml .= '</mergeCells>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$extra
            .($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$xml.'</sheetData>'
            .$mergeXml
            .'</worksheet>';
    }

    private static function cell(int $col, int $row, array $cell): string
    {
        $ref = self::column($col).($row + 1);
        $value = $cell[0] ?? '';
        $format = $cell[1] ?? 'text';
        $style = match ($format) {
            'header' => 1,
            'title' => 2,
            'heading' => 3,
            'muted', 'label' => 4,
            'number' => 5,
            'currency' => 6,
            'percent' => 7,
            'date' => 8,
            'parent' => 9,
            'group' => 10,
            'cell' => 11,
            'gap' => 12,
            'cell-number' => 13,
            'cell-currency' => 14,
            'cell-percent' => 15,
            'cell-date' => 16,
            default => 0,
        };

        if (in_array($format, ['number', 'currency', 'percent', 'date', 'cell-number', 'cell-currency', 'cell-percent', 'cell-date'], true)) {
            $numeric = ReportValue::excelNumber($value, str_starts_with($format, 'cell-') ? substr($format, 5) : $format);
            if (is_int($numeric) || is_float($numeric)) {
                if (in_array($format, ['percent', 'cell-percent'], true) && abs($numeric) > 1) {
                    $numeric = $numeric / 100;
                }

                return '<c r="'.$ref.'" s="'.$style.'"><v>'.self::xml((string) $numeric).'</v></c>';
            }
            $value = $numeric ?? '';
        }

        return '<c r="'.$ref.'" t="inlineStr" s="'.$style.'"><is><t>'.self::xml((string) $value).'</t></is></c>';
    }

    private static function column(int $index): string
    {
        $name = '';
        $n = $index + 1;
        while ($n > 0) {
            $n--;
            $name = chr(65 + ($n % 26)).$name;
            $n = intdiv($n, 26);
        }

        return $name;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars(mb_substr($value, 0, 32000), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
