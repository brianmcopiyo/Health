<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToHospital
{
    protected static function bootBelongsToHospital(): void
    {
        static::addGlobalScope('hospital', function (Builder $builder) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            if ($user->isPlatformAdmin() && static::platformBypassesTenant()) {
                return;
            }

            if ($user->isPlatformAdmin() || ! $user->hospital_id) {
                $builder->whereRaw('0 = 1');

                return;
            }

            $builder->where($builder->getModel()->getTable().'.hospital_id', $user->hospital_id);
        });

        static::creating(function ($model) {
            $user = auth()->user();

            if ($user && ! $user->isPlatformAdmin() && empty($model->hospital_id)) {
                $model->hospital_id = $user->hospital_id;
            }
        });
    }

    public static function platformBypassesTenant(): bool
    {
        return false;
    }
}
