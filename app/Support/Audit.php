<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, Model $model, array $payload = []): void
    {
        $user = auth()->user();
        $request = request();

        AuditEvent::query()->create([
            'hospital_id' => $model->getAttribute('hospital_id')
                ?? $model->getAttribute('from_hospital_id')
                ?? $user?->hospital_id,
            'actor_id' => $user?->id,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'payload' => self::safePayload($payload) ?: null,
        ]);
    }

    public static function viewed(Model $model, array $payload = []): void
    {
        self::record('viewed', $model, $payload);
    }

    public static function exported(Model $model, array $payload = []): void
    {
        self::record('exported', $model, $payload);
    }

    public static function downloaded(Model $model, array $payload = []): void
    {
        self::record('downloaded', $model, $payload);
    }

    private static function safePayload(array $payload): array
    {
        $blocked = [
            'password', 'token', 'accessToken', 'body', 'notes', 'result', 'national_id', 'phone',
            'email', 'address', 'reason', 'handover_notes', 'contents',
        ];

        $clean = [];
        foreach ($payload as $key => $value) {
            if (in_array($key, $blocked, true) || str_contains(strtolower((string) $key), 'password')) {
                $clean[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = array_values(array_filter($value, fn ($item) => is_scalar($item) || $item === null));
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
