<?php

namespace App\Support\Access;

class AccountPresenter
{
    public static function fields($user): array
    {
        return [
            'status' => $user->status ?: AccountStatus::ACTIVE,
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'has_avatar' => (bool) $user->avatar_path,
        ];
    }
}
