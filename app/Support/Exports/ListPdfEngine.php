<?php

namespace App\Support\Exports;

class ListPdfEngine
{
    private float $pageW = 595.28;

    private float $pageH = 841.89;

    private float $marginX = 42;

    private float $marginTop = 62;

    private float $marginBottom = 42;

    private float $y = 0;

    private array $pages = [];

    private array $ops = [];

    private array $meta = [];

    private array $stroke = [0.545, 0.518, 0.471];

    private array $colWidths = [];

    private array $repeatHeaders = [];

    public static function render(array $document): string
    {
        return (new self)->build($document);
    }

    private function build(array $document): string
    {
        $this->meta = $document['meta'] ?? [];
        $this->newPage();
        $this->cover();

        $sections = $document['sections'] ?? [];
        $hierarchy = $this->hierarchyBlock($sections);

        if ($hierarchy) {
            $this->hierarchy($hierarchy);
        } else {
            $tables = [];
            foreach ($sections as $section) {
                foreach ($section['blocks'] ?? [] as $block) {
                    if (($block['type'] ?? '') === 'table') {
                        $tables[] = $block;
                    }
                }
            }

            if ($tables === []) {
                $this->empty('No records match the current filters.');
            }

            foreach ($tables as $index => $table) {
                $this->table($table, count($tables) > 1 || ($table['title'] ?? '') !== ($this->meta['title'] ?? ''));
                if ($index < count($tables) - 1) {
                    $this->y -= 10;
                }
            }
        }

        if ($this->ops) {
            $this->pages[] = ['ops' => $this->ops];
        }

        return $this->assemble();
    }

    private function cover(): void
    {
        $org = $this->meta['organization'] ?? [];
        $this->rect(0, $this->pageH - 28, $this->pageW, 28, [0.043, 0.310, 0.302]);
        $this->text($org['code'] ?? 'HMS', 42, $this->pageH - 18, 9, 'F2', [1, 1, 1]);
        $this->text($this->meta['kind'] ?? 'Report', $this->pageW - 42, $this->pageH - 18, 9, 'F1', [1, 1, 1], 'right');

        $this->text($org['name'] ?? 'Hospital', $this->marginX, $this->y, 11, 'F2', [0.059, 0.435, 0.424]);
        $this->y -= 22;
        $this->text($this->meta['title'] ?? 'Report', $this->marginX, $this->y, 22, 'F2', [0.063, 0.165, 0.196]);
        $this->y -= 16;
        $this->rule();

        $place = trim(($org['address'] ?? '').(($org['city'] ?? '') !== '' ? ', '.($org['city'] ?? '') : '').(($org['region'] ?? '') !== '' ? ', '.($org['region'] ?? '') : ''), ' ,');
        $facts = array_values(array_filter([
            ['Reporting period', $this->meta['period']['label'] ?? ''],
            ['Generated', $this->meta['generated_at'] ?? ''],
            ['Organization', $place !== '' ? $place : ($org['code'] ?? '')],
            ['Contact', $org['phone'] ?? ''],
        ], fn ($row) => $row[1] !== ''));

        $col = ($this->contentW()) / max(count($facts), 1);
        $top = $this->y;
        foreach ($facts as $i => $fact) {
            $x = $this->marginX + ($i * $col);
            $this->text($fact[0], $x, $top, 8, 'F1', [0.420, 0.486, 0.510]);
            $this->text($this->fit($fact[1], $col - 8, 10), $x, $top - 13, 10, 'F2', [0.063, 0.165, 0.196]);
        }
        $this->y = $top - 32;

        $filters = $this->meta['filters'] ?? [];
        if ($filters) {
            $this->text('Filters', $this->marginX, $this->y, 8, 'F2', [0.420, 0.486, 0.510]);
            $this->y -= 12;
            $line = implode('   ·   ', array_map(fn ($item) => ($item['label'] ?? '').': '.($item['value'] ?? ''), $filters));
            $this->wrapped($line, 9, [0.063, 0.165, 0.196], 12);
            $this->y -= 6;
        }

        $this->rule();
        $this->y -= 8;
    }

    private function hierarchyBlock(array $sections): ?array
    {
        foreach ($sections as $section) {
            foreach ($section['blocks'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'hierarchy') {
                    return $block;
                }
            }
        }

        return null;
    }

    private function hierarchy(array $block): void
    {
        $headers = $block['headers'] ?? [];
        $width = max(1, (int) ($block['width'] ?? count($headers)), count($headers));
        $this->colWidths = $this->equalWidths($width);
        $this->repeatHeaders = $headers;
        $lines = $block['lines'] ?? [];

        if ($lines === []) {
            $this->empty($block['empty'] ?? 'No records match the current filters.');

            return;
        }

        $this->ensure(20);
        $this->drawHeaderBand($headers);

        foreach ($lines as $line) {
            $kind = $line['kind'] ?? '';
            if ($kind === 'parent') {
                $this->repeatHeaders = $headers;
                $prepared = $this->prepareSpanned($line['cells'] ?? []);
                $this->ensureHierarchy($prepared['height']);
                $this->drawPrepared($prepared, [0.898, 0.922, 0.918], [0.043, 0.310, 0.302], 'F2');
                continue;
            }
            if ($kind === 'group') {
                $this->ensureHierarchy(18);
                $this->drawBand(18, (string) ($line['title'] ?? ''), [0.820, 0.875, 0.870], [0.043, 0.310, 0.302], 9, 'F2');
                continue;
            }
            if ($kind === 'headers') {
                $this->repeatHeaders = $line['headers'] ?? [];
                $this->ensureHierarchy(16);
                $this->drawHeaderBand($this->repeatHeaders);
                continue;
            }
            if ($kind === 'row') {
                $prepared = $this->prepareSpanned($line['cells'] ?? []);
                $this->ensureHierarchy($prepared['height']);
                $this->drawPrepared($prepared, [1, 1, 1], [0.063, 0.165, 0.196], 'F1');
                continue;
            }
            if ($kind === 'gap') {
                $this->repeatHeaders = $headers;
                $this->ensureHierarchy(14);
                $this->drawBand(14, '', [1, 1, 1], [0.063, 0.165, 0.196], 8, 'F1');
            }
        }
    }

    private function ensureHierarchy(float $height): void
    {
        if ($this->y - $height < $this->marginBottom + 8) {
            $this->newPage();
            if ($this->repeatHeaders !== []) {
                $this->drawHeaderBand($this->repeatHeaders);
            }
        }
    }

    private function equalWidths(int $count): array
    {
        $n = max(1, $count);
        $width = $this->contentW() / $n;

        return array_fill(0, $n, $width);
    }

    private function spanWidths(int $cells): array
    {
        $base = $this->colWidths ?: $this->equalWidths($cells);
        $n = max(1, min($cells, count($base)));
        if ($n >= count($base)) {
            return $base;
        }
        $widths = array_slice($base, 0, $n);
        $widths[$n - 1] += array_sum(array_slice($base, $n));

        return $widths;
    }

    private function prepareSpanned(array $cells): array
    {
        if ($cells === []) {
            $cells = [['text' => '', 'format' => 'text']];
        }
        $widths = $this->spanWidths(count($cells));
        $prepared = $this->prepareRow($cells, $widths);
        $prepared['widths'] = $widths;

        return $prepared;
    }

    private function drawHeaderBand(array $headers): void
    {
        if ($headers === []) {
            $headers = [['title' => '']];
        }
        $widths = $this->spanWidths(count($headers));
        $x = $this->marginX;
        $this->rect($x, $this->y - 16, $this->contentW(), 16, [0.059, 0.435, 0.424], $this->stroke);
        foreach ($headers as $i => $header) {
            $align = ($header['align'] ?? 'left') === 'right' ? 'right' : 'left';
            $tx = $align === 'right' ? $x + $widths[$i] - 4 : $x + 4;
            $this->text($this->fit($header['title'] ?? '', $widths[$i] - 8, 8), $tx, $this->y - 11, 8, 'F2', [1, 1, 1], $align);
            if ($i < count($headers) - 1) {
                $this->line($x + $widths[$i], $this->y, $x + $widths[$i], $this->y - 16, $this->stroke, 0.6);
            }
            $x += $widths[$i];
        }
        $this->y -= 16;
    }

    private function drawBand(float $height, string $text, array $fill, array $color, int $size, string $font): void
    {
        $this->rect($this->marginX, $this->y - $height, $this->contentW(), $height, $fill, $this->stroke);
        if ($text !== '') {
            $this->text($this->fit($text, $this->contentW() - 8, $size), $this->marginX + 4, $this->y - 12, $size, $font, $color);
        }
        $this->y -= $height;
    }

    private function drawPrepared(array $prepared, array $fill, array $color, string $font): void
    {
        $height = $prepared['height'];
        $widths = $prepared['widths'];
        $x = $this->marginX;
        foreach ($prepared['cells'] as $i => $cell) {
            $this->rect($x, $this->y - $height, $widths[$i], $height, $fill, $this->stroke);
            $align = in_array($cell['format'], ['number', 'currency', 'percent'], true) ? 'right' : 'left';
            foreach ($cell['lines'] as $n => $line) {
                $ty = $this->y - 11 - ($n * 11);
                $tx = $align === 'right' ? $x + $widths[$i] - 4 : $x + 4;
                $this->text($line, $tx, $ty, 8, $font, $color, $align);
            }
            $x += $widths[$i];
        }
        $this->y -= $height;
    }

    private function table(array $block, bool $showTitle): void
    {
        $headers = $block['headers'] ?? [];
        $rows = $block['rows'] ?? [];
        $widths = $this->columnWidths($headers);

        if ($showTitle && ($block['title'] ?? '') !== '') {
            $this->ensure(28);
            $this->text($block['title'], $this->marginX, $this->y, 11, 'F2', [0.063, 0.165, 0.196]);
            $this->y -= 16;
        }

        $this->ensure(36);
        $this->tableHeader($headers, $widths);

        if ($rows === []) {
            $this->ensure(18);
            $this->drawBand(18, $block['empty'] ?? 'No records match the current filters.', [1, 1, 1], [0.420, 0.486, 0.510], 8, 'F1');

            return;
        }

        foreach ($rows as $index => $row) {
            $prepared = $this->prepareRow($row, $widths);
            $this->ensureRow($prepared['height'], $headers, $widths);
            $this->tableRow($prepared, $widths, $index % 2 === 1);
        }

        $shown = count($rows);
        $total = (int) ($block['total'] ?? $shown);
        if ($total > $shown) {
            $this->y -= 6;
            $this->text('Showing '.$shown.' of '.$this->number($total).' records.', $this->marginX, $this->y, 8, 'F1', [0.420, 0.486, 0.510]);
            $this->y -= 12;
        } else {
            $this->y -= 8;
        }
    }

    private function tableHeader(array $headers, array $widths): void
    {
        $x = $this->marginX;
        $this->rect($x, $this->y - 16, $this->contentW(), 16, [0.059, 0.435, 0.424], $this->stroke);
        foreach ($headers as $i => $header) {
            $align = ($header['align'] ?? 'left') === 'right' ? 'right' : 'left';
            $tx = $align === 'right' ? $x + $widths[$i] - 4 : $x + 4;
            $this->text($this->fit($header['title'] ?? '', $widths[$i] - 8, 8), $tx, $this->y - 11, 8, 'F2', [1, 1, 1], $align);
            if ($i < count($headers) - 1) {
                $this->line($x + $widths[$i], $this->y, $x + $widths[$i], $this->y - 16, $this->stroke, 0.6);
            }
            $x += $widths[$i];
        }
        $this->y -= 16;
    }

    private function prepareRow(array $row, array $widths): array
    {
        $cells = [];
        $lines = 1;
        foreach ($row as $i => $cell) {
            $wrapped = $this->wrapLines((string) ($cell['text'] ?? '—'), $widths[$i] - 8, 8, 6);
            $lines = max($lines, count($wrapped));
            $cells[] = [
                'lines' => $wrapped,
                'format' => $cell['format'] ?? 'text',
            ];
        }

        return [
            'cells' => $cells,
            'height' => max(16, ($lines * 11) + 6),
        ];
    }

    private function tableRow(array $prepared, array $widths, bool $alt): void
    {
        $height = $prepared['height'];
        $x = $this->marginX;
        $fill = $alt ? [0.965, 0.953, 0.925] : [1, 1, 1];
        foreach ($prepared['cells'] as $i => $cell) {
            $this->rect($x, $this->y - $height, $widths[$i], $height, $fill, $this->stroke);
            $align = in_array($cell['format'], ['number', 'currency', 'percent'], true) ? 'right' : 'left';
            foreach ($cell['lines'] as $n => $line) {
                $ty = $this->y - 11 - ($n * 11);
                $tx = $align === 'right' ? $x + $widths[$i] - 4 : $x + 4;
                $this->text($line, $tx, $ty, 8, 'F1', [0.063, 0.165, 0.196], $align);
            }
            $x += $widths[$i];
        }
        $this->y -= $height;
    }

    private function empty(string $message): void
    {
        $this->ensure(24);
        $this->text($message, $this->marginX, $this->y, 9, 'F1', [0.420, 0.486, 0.510]);
        $this->y -= 18;
    }

    private function columnWidths(array $headers): array
    {
        $n = max(1, count($headers));
        $flex = [];
        foreach ($headers as $header) {
            $flex[] = in_array($header['format'] ?? '', ['number', 'currency', 'percent', 'date'], true) ? 0.8 : 1.2;
        }
        $sum = array_sum($flex) ?: $n;

        return array_map(fn ($weight) => $this->contentW() * ($weight / $sum), $flex);
    }

    private function ensure(float $need): void
    {
        if ($this->y - $need < $this->marginBottom + 8) {
            $this->newPage();
        }
    }

    private function ensureRow(float $height, array $headers, array $widths): void
    {
        if ($this->y - $height < $this->marginBottom + 8) {
            $this->newPage();
            $this->tableHeader($headers, $widths);
        }
    }

    private function newPage(): void
    {
        if ($this->ops) {
            $this->pages[] = ['ops' => $this->ops];
        }
        $this->ops = [];
        $this->y = $this->pageH - $this->marginTop;
        $this->chrome(count($this->pages) > 0);
    }

    private function chrome(bool $header): void
    {
        $org = $this->meta['organization'] ?? [];
        if ($header) {
            $this->rect(0, $this->pageH - 26, $this->pageW, 26, [0.043, 0.310, 0.302]);
            $this->text($org['name'] ?? 'Hospital', 42, $this->pageH - 16, 8, 'F2', [1, 1, 1]);
            $this->text(($this->meta['title'] ?? 'Report').'  ·  '.($this->meta['period']['label'] ?? ''), $this->pageW - 42, $this->pageH - 16, 8, 'F1', [1, 1, 1], 'right');
        }
        $this->rect(0, 0, $this->pageW, 28, [0.965, 0.953, 0.925]);
        $this->text('Confidential operational report', 42, 12, 8, 'F1', [0.420, 0.486, 0.510]);
    }

    private function contentW(): float
    {
        return $this->pageW - (2 * $this->marginX);
    }

    private function rule(): void
    {
        $this->line($this->marginX, $this->y, $this->marginX + $this->contentW(), $this->y, [0.851, 0.812, 0.753], 0.7);
        $this->y -= 12;
    }

    private function wrapped(string $text, int $size, array $color, int $lead): void
    {
        foreach ($this->wrapLines($text, $this->contentW(), $size, 8) as $line) {
            $this->text($line, $this->marginX, $this->y, $size, 'F1', $color);
            $this->y -= $lead;
        }
    }

    private function wrapLines(string $text, float $width, int $size, int $max): array
    {
        $limit = max(4, (int) floor($width / ($size * 0.5)));
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $parts = [];
        foreach ($words as $word) {
            if (strlen($word) <= $limit) {
                $parts[] = $word;
                continue;
            }
            foreach (str_split($word, $limit) as $chunk) {
                $parts[] = $chunk;
            }
        }
        $lines = [];
        $line = '';
        foreach ($parts as $word) {
            $next = $line === '' ? $word : $line.' '.$word;
            if (strlen($next) > $limit && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $next;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        if ($lines === []) {
            return ['—'];
        }
        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] = rtrim(substr($lines[$max - 1], 0, max(1, $limit - 1))).'…';
        }

        return $lines;
    }

    private function fit(string $text, float $width, int $size): string
    {
        $max = max(4, (int) floor($width / ($size * 0.5)));
        $text = trim($text);
        if (strlen($text) <= $max) {
            return $text;
        }

        return rtrim(substr($text, 0, $max - 1)).'…';
    }

    private function number(int|float $value): string
    {
        if (abs($value - round($value)) < 0.001) {
            return number_format((int) round($value));
        }

        return number_format((float) $value, 1);
    }

    private function text(string $text, float $x, float $y, int $size, string $font, array $color, string $align = 'left'): void
    {
        $encoded = self::winAnsi($text);
        if ($align === 'right') {
            $x -= strlen($encoded) * $size * 0.48;
        } elseif ($align === 'center') {
            $x -= (strlen($encoded) * $size * 0.48) / 2;
        }
        $this->ops[] = sprintf(
            'q %.3f %.3f %.3f rg BT /%s %d Tf 1 0 0 1 %.2f %.2f Tm %s Tj ET Q',
            $color[0],
            $color[1],
            $color[2],
            $font,
            $size,
            $x,
            $y,
            self::literal($encoded)
        );
    }

    private function rect(float $x, float $y, float $w, float $h, array $fill, ?array $stroke = null): void
    {
        $op = sprintf('q %.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f', $fill[0], $fill[1], $fill[2], $x, $y, $w, $h);
        if ($stroke) {
            $op .= sprintf(' %.3f %.3f %.3f RG 0.6 w %.2f %.2f %.2f %.2f re S', $stroke[0], $stroke[1], $stroke[2], $x, $y, $w, $h);
        }
        $this->ops[] = $op.' Q';
    }

    private function line(float $x1, float $y1, float $x2, float $y2, array $color, float $width): void
    {
        $this->ops[] = sprintf(
            'q %.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S Q',
            $color[0],
            $color[1],
            $color[2],
            $width,
            $x1,
            $y1,
            $x2,
            $y2
        );
    }

    private function assemble(): string
    {
        $count = max(1, count($this->pages));
        foreach ($this->pages as $index => $page) {
            $label = 'Page '.($index + 1).' of '.$count;
            $this->pages[$index]['ops'][] = sprintf(
                'q 0.420 0.486 0.510 rg BT /F1 8 Tf 1 0 0 1 %.2f 12 Tm %s Tj ET Q',
                $this->pageW - 42 - (strlen($label) * 3.8),
                self::literal($label)
            );
        }

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $next = 3;
        foreach ($this->pages as $_) {
            $kids[] = $next.' 0 R';
            $next += 2;
        }
        $fontRegular = $next;
        $fontBold = $next + 1;
        $objects[] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($this->pages).' >>';

        foreach ($this->pages as $page) {
            $stream = implode("\n", $page['ops']);
            $contentId = count($objects) + 2;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$this->pageW.' '.$this->pageH.'] /Resources << /Font << /F1 '.$fontRegular.' 0 R /F2 '.$fontBold.' 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[] = '<< /Length '.strlen($stream).' >> stream'."\n".$stream."\n".'endstream';
        }
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1).' 0 obj '.$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref\n0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n";
        $pdf .= 'startxref\n'.$xref."\n%%EOF";

        return $pdf;
    }

    private static function winAnsi(string $text): string
    {
        $map = [
            '—' => "\x97",
            '–' => "\x96",
            '·' => "\xB7",
            '•' => "\x95",
            '…' => "\x85",
            '‘' => "\x91",
            '’' => "\x92",
            '“' => "\x93",
            '”' => "\x94",
            '€' => "\x80",
            '₵' => 'GHS ',
            "\u{00A0}" => ' ',
        ];
        $text = strtr($text, $map);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        $out = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $char) {
            $code = mb_ord($char, 'UTF-8');
            if ($code === false) {
                continue;
            }
            if ($code <= 0x7F || ($code >= 0xA0 && $code <= 0xFF)) {
                $out .= chr($code);
            } else {
                $folded = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $char);
                $out .= is_string($folded) && $folded !== '' ? $folded : '';
            }
        }

        return $out;
    }

    private static function literal(string $bytes): string
    {
        $out = '';
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($bytes[$i]);
            if ($bytes[$i] === '\\' || $bytes[$i] === '(' || $bytes[$i] === ')') {
                $out .= '\\'.$bytes[$i];
            } elseif ($ord < 32 || $ord > 126) {
                $out .= sprintf('\\%03o', $ord);
            } else {
                $out .= $bytes[$i];
            }
        }

        return '('.$out.')';
    }
}
