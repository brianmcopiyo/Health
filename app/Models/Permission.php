<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends BaseModel
{
    protected $fillable = [
        'name',
        'action',
        'subject',
        'group',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function toAbilityRule(): array
    {
        return [
            'action' => $this->action,
            'subject' => $this->subject,
        ];
    }
}
