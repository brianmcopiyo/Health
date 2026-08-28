<?php

namespace App\Http\Controllers;

use App\Models\User;

abstract class Controller
{
    protected function authorizePermission(User $user, string $action, string $subject): void
    {
        abort_unless($user->hasPermission($action, $subject), 403, 'This action is unauthorized.');
    }
}
