<?php

namespace App\Support\Access;

class PermissionActions
{
    public static function moduleActions(): array
    {
        return [
            'read' => 'View',
            'create' => 'Create',
            'update' => 'Edit',
            'delete' => 'Delete',
            'approve' => 'Approve',
            'export' => 'Export',
            'manage' => 'Manage',
        ];
    }
}
