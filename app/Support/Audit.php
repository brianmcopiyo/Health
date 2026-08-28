<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, Model $model, array $payload = []): void
    {
        $user = auth()->user();

        AuditEvent::query()->create([
            'hospital_id' => $model->getAttribute('hospital_id')
                ?? $model->getAttribute('from_hospital_id')
                ?? $user?->hospital_id,
            'actor_id' => $user?->id,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'payload' => $payload ?: null,
        ]);
    }
}
