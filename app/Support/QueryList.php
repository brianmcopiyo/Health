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
}
