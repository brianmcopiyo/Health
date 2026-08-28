<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait VisibleOnHospitalNetwork
{
    protected static function bootVisibleOnHospitalNetwork(): void
    {
        static::addGlobalScope('hospital_network', function (Builder $builder) {
            $user = auth()->user();

            if (! $user || $user->isPlatformAdmin()) {
                return;
            }

            if (! $user->hospital_id) {
                $builder->whereRaw('0 = 1');

                return;
            }

            $table = $builder->getModel()->getTable();

            $builder->where(function (Builder $query) use ($user, $table) {
                $query->where($table.'.from_hospital_id', $user->hospital_id)
                    ->orWhere($table.'.to_hospital_id', $user->hospital_id);
            });
        });
    }
}
