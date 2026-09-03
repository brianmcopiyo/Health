<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class QueryList
{
    public static function paginate(Builder $query, Request $request, int $default = 25): LengthAwarePaginator
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', $default)));

        return $query->paginate($perPage);
    }

    public static function term(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return '%'.addcslashes($value, '%_').'%';
    }

    public static function equals(Builder $query, Request $request, string $column, ?string $key = null): void
    {
        $key ??= $column;
        if ($request->filled($key)) {
            $query->where($column, $request->string($key)->toString());
        }
    }

    public static function boolean(Builder $query, Request $request, string $column, ?string $key = null): void
    {
        $key ??= $column;
        if ($request->filled($key)) {
            $query->where($column, $request->boolean($key));
        }
    }

    public static function dateRange(Builder $query, Request $request, string $column, string $from = 'from', string $to = 'to'): void
    {
        if ($request->filled($from)) {
            $query->whereDate($column, '>=', $request->string($from)->toString());
        }
        if ($request->filled($to)) {
            $query->whereDate($column, '<=', $request->string($to)->toString());
        }
    }

    public static function numberRange(Builder $query, Request $request, string $column, string $min, string $max): void
    {
        if ($request->filled($min) && is_numeric($request->input($min))) {
            $query->where($column, '>=', $request->input($min));
        }
        if ($request->filled($max) && is_numeric($request->input($max))) {
            $query->where($column, '<=', $request->input($max));
        }
    }
}
