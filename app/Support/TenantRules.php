<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class TenantRules
{
    public static function inHospital(string $table, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column);
        $user = auth()->user();

        if ($user && ! $user->isPlatformAdmin() && $user->hospital_id) {
            $rule->where('hospital_id', $user->hospital_id);
        }

        return $rule;
    }
}
