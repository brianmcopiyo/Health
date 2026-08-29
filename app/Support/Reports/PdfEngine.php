<?php

namespace App\Support\Reports;

class PdfEngine
{
    private float $pageW = 612;

    private float $pageH = 792;

    private float $marginX = 42;

    private float $marginTop = 62;

    private float $marginBottom = 42;

    private float $y = 0;

    private bool $landscape = false;

    private array $pages = [];

    private array $ops = [];

    private array $meta = [];

    public static function render(array $document): string
    {
        return (new self)->build($document);
    }

    private function build(array $document): string
    {
        $this->meta = $document['meta'] ?? [];
        $this->newPage(false);
        $this->cover();

        foreach ($document['sections'] ?? [] as $section) {
            $this->section($section);
        }

        if ($this->ops) {
            $this->pages[] = ['ops' => $this->ops, 'landscape' => $this->landscape];
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
            $this->text($fact[1], $x, $top - 13, 10, 'F2', [0.063, 0.165, 0.196]);
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

    private function section(array $section): void
    {
        $this->portrait();
        $this->ensure(86);
        $this->text($section['title'] ?? 'Section', $this->marginX, $this->y, 14, 'F2', [0.043, 0.310, 0.302]);
        $this->y -= 8;
        $this->rect($this->marginX, $this->y, 36, 2, [0.788, 0.518, 0.165]);
        $this->y -= 16;

        foreach ($section['blocks'] ?? [] as $block) {
            match ($block['type'] ?? '') {
                'narrative' => $this->narrative($block),
                'kpis' => $this->kpis($block['items'] ?? []),
                'chart' => $this->chart($block),
                'table' => $this->table($block),
                'list' => $this->list($block),
                'empty' => $this->empty($block),
                default => null,
            };
        }

        $this->y -= 10;
    }

    private function narrative(array $block): void
    {
        $this->ensure(40);
        $this->wrapped($block['text'] ?? '', 10, [0.239, 0.353, 0.388], 14);
        $this->y -= 8;
    }

    private function kpis(array $items): void
    {
        if ($items === []) {
            return;
        }

        $cols = min(4, max(2, count($items)));
        $gap = 8;
        $width = ($this->contentW() - ($gap * ($cols - 1))) / $cols;
        $height = 54;
        $chunks = array_chunk($items, $cols);
        foreach ($chunks as $row) {
            $this->ensure($height + 10);
            $x = $this->marginX;
            foreach ($row as $item) {
                $this->rect($x, $this->y - $height, $width, $height, [0.965, 0.953, 0.925], [0.851, 0.812, 0.753]);
                $this->rect($x, $this->y - $height, 3, $height, $this->toneColor($item['tone'] ?? null));
                $this->text($item['title'] ?? 'Metric', $x + 10, $this->y - 14, 8, 'F1', [0.420, 0.486, 0.510]);
                $this->text($item['display'] ?? ReportValue::text($item['value'] ?? null), $x + 10, $this->y - 32, 16, 'F2', [0.063, 0.165, 0.196]);
                if (($item['hint'] ?? '') !== '') {
                    $this->text($this->fit($item['hint'], $width - 16, 8), $x + 10, $this->y - 46, 8, 'F1', [0.420, 0.486, 0.510]);
                }
                $x += $width + $gap;
            }
            $this->y -= $height + 10;
        }
    }

    private function chart(array $block): void
    {
        $items = $block['items'] ?? [];
        if ($items === []) {
            return;
        }

        $kind = $block['chart'] ?? 'bar';
        $height = $kind === 'hbar' ? max(120, 18 + (count($items) * 16)) : 168;
        $height = min($height, 220);
        $this->ensure($height + 28);
        $this->text($block['title'] ?? 'Chart', $this->marginX, $this->y, 11, 'F2', [0.063, 0.165, 0.196]);
        $this->y -= 14;
        $boxY = $this->y - $height;
        $this->rect($this->marginX, $boxY, $this->contentW(), $height, [1, 1, 1], [0.851, 0.812, 0.753]);

        match ($kind) {
            'line' => $this->lineChart($items, $this->marginX + 36, $boxY + 22, $this->contentW() - 52, $height - 40),
            'hbar' => $this->hbarChart($items, $this->marginX + 8, $boxY + 10, $this->contentW() - 16, $height - 20),
            'donut' => $this->donutChart($items, $this->marginX, $boxY, $this->contentW(), $height),
            default => $this->barChart($items, $this->marginX + 28, $boxY + 22, $this->contentW() - 40, $height - 40),
        };

        $this->y = $boxY - 14;
    }

    private function barChart(array $items, float $x, float $y, float $w, float $h): void
    {
        $max = max(1, ...array_map(fn ($item) => (float) $item['value'], $items));
        $n = max(1, count($items));
        $slot = $w / $n;
        $bar = min(28, $slot * 0.62);
        $this->line($x, $y, $x + $w, $y, [0.765, 0.718, 0.643], 0.6);
        foreach ($items as $i => $item) {
            $value = (float) $item['value'];
            $bh = ($value / $max) * ($h - 16);
            $bx = $x + ($i * $slot) + (($slot - $bar) / 2);
            $this->rect($bx, $y, $bar, $bh, [0.059, 0.435, 0.424]);
            $this->text($this->fit((string) $item['label'], $slot, 7), $bx + ($bar / 2), $y - 10, 7, 'F1', [0.420, 0.486, 0.510], 'center');
        }
    }

    private function hbarChart(array $items, float $x, float $y, float $w, float $h): void
    {
        $max = max(1, ...array_map(fn ($item) => (float) $item['value'], $items));
        $n = max(1, count($items));
        $row = $h / $n;
        $labelW = min(110, $w * 0.32);
        foreach ($items as $i => $item) {
            $top = $y + $h - (($i + 1) * $row) + 3;
            $this->text($this->fit((string) $item['label'], $labelW, 8), $x, $top + 8, 8, 'F1', [0.239, 0.353, 0.388]);
            $barW = (($w - $labelW - 28) * ((float) $item['value'] / $max));
            $this->rect($x + $labelW, $top, max($barW, 1), 9, [0.059, 0.435, 0.424]);
            $this->text(ReportValue::number($item['value']), $x + $labelW + $barW + 4, $top + 8, 8, 'F1', [0.420, 0.486, 0.510]);
        }
    }

    private function lineChart(array $items, float $x, float $y, float $w, float $h): void
    {
        $max = max(1, ...array_map(fn ($item) => (float) $item['value'], $items));
        $n = count($items);
        $this->line($x, $y, $x + $w, $y, [0.765, 0.718, 0.643], 0.6);
        $this->line($x, $y, $x, $y + $h, [0.765, 0.718, 0.643], 0.6);
        if ($n === 0) {
            return;
        }
        $points = [];
        foreach ($items as $i => $item) {
            $px = $n === 1 ? $x + ($w / 2) : $x + (($w / max($n - 1, 1)) * $i);
            $py = $y + ((((float) $item['value']) / $max) * $h);
            $points[] = [$px, $py];
        }
        for ($i = 1; $i < count($points); $i++) {
            $this->line($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], [0.059, 0.435, 0.424], 1.4);
        }
        $step = max(1, (int) ceil($n / 7));
        foreach ($items as $i => $item) {
            if ($i % $step !== 0 && $i !== $n - 1) {
                continue;
            }
            $this->text($this->fit((string) $item['label'], 36, 7), $points[$i][0], $y - 10, 7, 'F1', [0.420, 0.486, 0.510], 'center');
        }
    }

    private function donutChart(array $items, float $x, float $y, float $w, float $h): void
    {
        $usable = array_values(array_filter($items, fn ($item) => (float) $item['value'] > 0));
        $total = array_sum(array_map(fn ($item) => (float) $item['value'], $usable));
        if ($total <= 0) {
            return;
        }
        $cx = $x + ($w * 0.32);
        $cy = $y + ($h / 2);
        $r = min($h / 2 - 10, 52);
        $angle = 90;
        $colors = [
            [0.059, 0.435, 0.424],
            [0.788, 0.518, 0.165],
            [0.184, 0.435, 0.306],
            [0.129, 0.369, 0.549],
            [0.608, 0.173, 0.173],
            [0.420, 0.486, 0.510],
        ];
        foreach ($usable as $i => $item) {
            $sweep = ((float) $item['value'] / $total) * 360;
            $this->slice($cx, $cy, $r, $angle, $angle + $sweep, $colors[$i % count($colors)]);
            $angle += $sweep;
        }
        $this->circle($cx, $cy, $r * 0.56, [1, 1, 1]);
        $lx = $x + ($w * 0.58);
        $ly = $y + $h - 22;
        foreach ($usable as $i => $item) {
            $this->rect($lx, $ly - 7, 7, 7, $colors[$i % count($colors)]);
            $label = $this->fit(($item['label'] ?? '').'  '.ReportValue::number($item['value']), $w * 0.38, 8);
            $this->text($label, $lx + 11, $ly, 8, 'F1', [0.239, 0.353, 0.388]);
            $ly -= 14;
        }
    }

    private function table(array $block): void
    {
        $headers = $block['headers'] ?? [];
        $rows = $block['rows'] ?? [];
        $wide = ! empty($block['wide']);
        if ($wide && ! $this->landscape) {
            $this->newPage(true);
        }

        $this->ensure(52);
        $this->text($block['title'] ?? 'Records', $this->marginX, $this->y, 11, 'F2', [0.063, 0.165, 0.196]);
        $this->y -= 16;

        $widths = $this->columnWidths($headers);
        $this->tableHeader($headers, $widths);
        if ($rows === []) {
            $this->empty(['message' => $block['empty'] ?? 'No data available for the selected period.']);
            $this->y -= 8;

            return;
        }
        foreach ($rows as $index => $row) {
            $this->ensure(18);
            if ($this->y < $this->marginBottom + 36) {
                $this->newPage($this->landscape);
                $this->tableHeader($headers, $widths);
            }
            $this->tableRow($row, $widths, $index % 2 === 1);
        }

        $shown = count($rows);
        $total = (int) ($block['total'] ?? $shown);
        if ($total > $shown) {
            $this->y -= 6;
            $this->text('Showing '.$shown.' of '.ReportValue::number($total).' records.', $this->marginX, $this->y, 8, 'F1', [0.420, 0.486, 0.510]);
            $this->y -= 12;
        } else {
            $this->y -= 8;
        }
    }

    private function tableHeader(array $headers, array $widths): void
    {
        $x = $this->marginX;
        $this->rect($x, $this->y - 16, $this->contentW(), 16, [0.059, 0.435, 0.424]);
        foreach ($headers as $i => $header) {
            $align = ($header['align'] ?? 'left') === 'right' ? 'right' : 'left';
            $tx = $align === 'right' ? $x + $widths[$i] - 4 : $x + 4;
            $this->text($this->fit($header['title'] ?? '', $widths[$i] - 8, 8), $tx, $this->y - 11, 8, 'F2', [1, 1, 1], $align);
            $x += $widths[$i];
        }
        $this->y -= 16;
    }

    private function tableRow(array $row, array $widths, bool $alt): void
    {
        $x = $this->marginX;
        if ($alt) {
            $this->rect($x, $this->y - 15, $this->contentW(), 15, [0.965, 0.953, 0.925]);
        }
        foreach ($row as $i => $cell) {
            $align = ($cell['format'] ?? '') && in_array($cell['format'], ['number', 'currency', 'percent'], true) ? 'right' : 'left';
            $tx = $align === 'right' ? $x + $widths[$i] - 4 : $x + 4;
            $this->text($this->fit($cell['text'] ?? '—', $widths[$i] - 8, 8), $tx, $this->y - 11, 8, 'F1', [0.063, 0.165, 0.196], $align);
            $x += $widths[$i];
        }
        $this->y -= 15;
    }

    private function list(array $block): void
    {
        $items = $block['items'] ?? [];
        $this->ensure(36);
        $this->text($block['title'] ?? 'List', $this->marginX, $this->y, 11, 'F2', [0.063, 0.165, 0.196]);
        $this->y -= 14;
        if ($items === []) {
            $this->empty(['message' => 'No data available for the selected period.']);
            return;
        }
        foreach ($items as $item) {
            $this->ensure(20);
            $this->rect($this->marginX, $this->y - 4, 3, 3, $this->toneColor($item['tone'] ?? null));
            $this->text($item['title'] ?? '', $this->marginX + 10, $this->y, 9, 'F2', [0.063, 0.165, 0.196]);
            $right = trim(($item['value'] ?? '').(isset($item['detail']) ? '  '.$item['detail'] : ''));
            if ($right !== '') {
                $this->text($right, $this->marginX + $this->contentW(), $this->y, 9, 'F2', [0.043, 0.310, 0.302], 'right');
            }
            $this->y -= 11;
            if (($item['meta'] ?? '') !== '') {
                $this->text($item['meta'], $this->marginX + 10, $this->y, 8, 'F1', [0.420, 0.486, 0.510]);
                $this->y -= 11;
            }
            $this->y -= 4;
        }
        $this->y -= 4;
    }

    private function empty(array $block): void
    {
        $this->ensure(36);
        if (($block['title'] ?? '') !== '') {
            $this->text($block['title'], $this->marginX, $this->y, 11, 'F2', [0.063, 0.165, 0.196]);
            $this->y -= 14;
        }
        $this->text($block['message'] ?? 'No data available for the selected period.', $this->marginX, $this->y, 9, 'F1', [0.420, 0.486, 0.510]);
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
        $width = $this->contentW();

        return array_map(fn ($weight) => $width * ($weight / $sum), $flex);
    }

    private function ensure(float $need): void
    {
        if ($this->y - $need < $this->marginBottom + 8) {
            $this->newPage($this->landscape);
        }
    }

    private function newPage(bool $landscape): void
    {
        if ($this->ops) {
            $this->pages[] = ['ops' => $this->ops, 'landscape' => $this->landscape];
        }
        $this->landscape = $landscape;
        $this->pageW = $landscape ? 792 : 612;
        $this->pageH = $landscape ? 612 : 792;
        $this->ops = [];
        $this->y = $this->pageH - $this->marginTop;
        $this->chrome(count($this->pages) > 0);
    }

    private function portrait(): void
    {
        if ($this->landscape) {
            $this->newPage(false);
        }
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

    private function toneColor(?string $tone): array
    {
        return match ($tone) {
            'danger', 'error' => [0.608, 0.173, 0.173],
            'warn', 'warning' => [0.788, 0.518, 0.165],
            'ok' => [0.184, 0.435, 0.306],
            'info' => [0.129, 0.369, 0.549],
            default => [0.059, 0.435, 0.424],
        };
    }

    private function wrapped(string $text, int $size, array $color, int $lead): void
    {
        $width = (int) floor($this->contentW() / ($size * 0.5));
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $line = '';
        foreach ($words as $word) {
            $next = $line === '' ? $word : $line.' '.$word;
            if (strlen($next) > $width && $line !== '') {
                $this->text($line, $this->marginX, $this->y, $size, 'F1', $color);
                $this->y -= $lead;
                $line = $word;
            } else {
                $line = $next;
            }
        }
        if ($line !== '') {
            $this->text($line, $this->marginX, $this->y, $size, 'F1', $color);
            $this->y -= $lead;
        }
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

    private function circle(float $cx, float $cy, float $r, array $fill): void
    {
        $k = 0.5522847498 * $r;
        $this->ops[] = sprintf(
            'q %.3f %.3f %.3f rg %.2f %.2f m %.2f %.2f %.2f %.2f %.2f %.2f c %.2f %.2f %.2f %.2f %.2f %.2f c %.2f %.2f %.2f %.2f %.2f %.2f c %.2f %.2f %.2f %.2f %.2f %.2f c f Q',
            $fill[0],
            $fill[1],
            $fill[2],
            $cx + $r,
            $cy,
            $cx + $r,
            $cy + $k,
            $cx + $k,
            $cy + $r,
            $cx,
            $cy + $r,
            $cx - $k,
            $cy + $r,
            $cx - $r,
            $cy + $k,
            $cx - $r,
            $cy,
            $cx - $r,
            $cy - $k,
            $cx - $k,
            $cy - $r,
            $cx,
            $cy - $r,
            $cx + $k,
            $cy - $r,
            $cx + $r,
            $cy - $k,
            $cx + $r,
            $cy
        );
    }

    private function slice(float $cx, float $cy, float $r, float $start, float $end, array $color): void
    {
        $a0 = deg2rad($start);
        $span = $end - $start;
        $steps = max(1, (int) ceil(abs($span) / 90));
        $path = sprintf('%.2f %.2f m %.2f %.2f l', $cx, $cy, $cx + ($r * cos($a0)), $cy + ($r * sin($a0)));
        for ($i = 0; $i < $steps; $i++) {
            $from = $start + (($span / $steps) * $i);
            $to = $start + (($span / $steps) * ($i + 1));
            $path .= ' '.$this->arc($cx, $cy, $r, $from, $to);
        }
        $this->ops[] = sprintf('q %.3f %.3f %.3f rg %s h f Q', $color[0], $color[1], $color[2], $path);
    }

    private function arc(float $cx, float $cy, float $r, float $from, float $to): string
    {
        $a0 = deg2rad($from);
        $a1 = deg2rad($to);
        $x0 = $cx + ($r * cos($a0));
        $y0 = $cy + ($r * sin($a0));
        $x1 = $cx + ($r * cos($a1));
        $y1 = $cy + ($r * sin($a1));
        $f = 4 / 3 * tan(($a1 - $a0) / 4);

        return sprintf(
            '%.2f %.2f %.2f %.2f %.2f %.2f c',
            $x0 - ($f * $r * sin($a0)),
            $y0 + ($f * $r * cos($a0)),
            $x1 + ($f * $r * sin($a1)),
            $y1 - ($f * $r * cos($a1)),
            $x1,
            $y1
        );
    }

    private function assemble(): string
    {
        $count = max(1, count($this->pages));
        foreach ($this->pages as $index => $page) {
            $label = 'Page '.($index + 1).' of '.$count;
            $w = $page['landscape'] ? 792 : 612;
            $this->pages[$index]['ops'][] = sprintf(
                'q 0.420 0.486 0.510 rg BT /F1 8 Tf 1 0 0 1 %.2f 12 Tm %s Tj ET Q',
                $w - 42 - (strlen($label) * 3.8),
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
            $w = $page['landscape'] ? 792 : 612;
            $h = $page['landscape'] ? 612 : 792;
            $stream = implode("\n", $page['ops']);
            $contentId = count($objects) + 2;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$w.' '.$h.'] /Resources << /Font << /F1 '.$fontRegular.' 0 R /F2 '.$fontBold.' 0 R >> >> /Contents '.$contentId.' 0 R >>';
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
