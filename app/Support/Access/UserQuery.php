<?php

namespace App\Support\Access;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserQuery
{
    public static function apply(Builder $query, Request $request, array $sortable = ['name', 'email', 'created_at', 'last_login_at', 'status']): Builder
    {
        if ($search = $request->string('q')->toString()) {
            $prefix = addcslashes($search, '%_').'%';
            $query->where(function ($builder) use ($prefix) {
                $builder->where('name', 'like', $prefix)
                    ->orWhere('email', 'like', $prefix)
                    ->orWhere('phone', 'like', $prefix);
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($roleId = $request->string('role_id')->toString()) {
            $query->where('role_id', $roleId);
        }

        $sort = $request->string('sort')->toString() ?: 'name';

        if (! in_array($sort, $sortable, true)) {
            $sort = 'name';
        }

        $dir = strtolower($request->string('sort_dir')->toString()) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $dir);

        if ($sort !== 'name') {
            $query->orderBy('name');
        }

        return $query;
    }
}
