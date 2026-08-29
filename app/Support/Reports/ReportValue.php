<?php

namespace App\Support\Reports;

use Carbon\CarbonImmutable;

class ReportValue
{
    public static function format(mixed $value, string $format = 'text'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_array($value)) {
            return '—';
        }

        return match ($format) {
            'number' => self::number($value),
            'currency' => self::currency($value),
            'percent' => self::percent($value),
            'date' => self::date($value),
            'datetime' => self::datetime($value),
            'status' => ReportCatalog::label((string) $value),
            default => self::text($value),
        };
    }

    public static function text(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value) || is_float($value)) {
            return self::number($value);
        }

        return trim((string) $value);
    }

    public static function number(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_string($value) && ! is_numeric(str_replace([',', '%', 'd'], '', $value))) {
            return $value;
        }

        $number = (float) $value;
        if (abs($number - round($number)) < 0.001) {
            return number_format((int) round($number));
        }

        return number_format($number, 1);
    }

    public static function currency(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2);
    }

    public static function percent(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $text = is_string($value) ? trim($value) : (string) $value;
        if (str_ends_with($text, '%')) {
            return $text;
        }

        return ((int) round((float) $value)).'%';
    }

    public static function date(mixed $value): string
    {
        $date = self::parse($value);
        if (! $date) {
            return $value === null || $value === '' ? '—' : (string) $value;
        }

        return $date->format('j M Y');
    }

    public static function datetime(mixed $value): string
    {
        $date = self::parse($value);
        if (! $date) {
            return $value === null || $value === '' ? '—' : (string) $value;
        }

        return $date->format('j M Y H:i');
    }

    public static function period(string $from, string $to): string
    {
        return self::date($from).' – '.self::date($to);
    }

    public static function infer(string $key, mixed $value): string
    {
        if (in_array($key, ['when', 'opened', 'registered', 'created', 'requested', 'generated_at'], true)) {
            return 'date';
        }

        if (in_array($key, ['total', 'amount', 'billed', 'collected', 'outstanding', 'invoiced'], true)) {
            return 'currency';
        }

        if ($key === 'status' || $key === 'type' || $key === 'sex') {
            return 'status';
        }

        if (str_contains($key, 'percent') || $key === 'occupancy' || $key === 'delta') {
            return 'percent';
        }

        if (is_int($value) || is_float($value)) {
            return 'number';
        }

        if (is_string($value) && str_ends_with(trim($value), '%')) {
            return 'percent';
        }

        return 'text';
    }

    public static function excelNumber(mixed $value, string $format): int|float|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($format === 'date') {
            $date = self::parse($value);
            if (! $date) {
                return (string) $value;
            }

            return ($date->getTimestamp() / 86400) + 25569;
        }

        if (in_array($format, ['number', 'currency', 'percent'], true)) {
            if (is_string($value) && str_ends_with(trim($value), '%')) {
                return ((float) $value) / 100;
            }

            return is_numeric($value) ? $value + 0 : (string) $value;
        }

        return self::format($value, $format);
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
