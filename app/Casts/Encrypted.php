<?php

namespace App\Casts;

use App\Support\FieldCrypt;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class Encrypted implements CastsAttributes
{
    public function __construct(private ?string $indexMap = null)
    {
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return FieldCrypt::decrypt((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $plain = $value === null ? null : trim((string) $value);
        $payload = [
            $key => ($plain === null || $plain === '') ? null : FieldCrypt::encrypt($plain),
        ];

        foreach ($this->indexes() as $column => $mode) {
            if ($plain === null || $plain === '') {
                $payload[$column] = null;
                continue;
            }

            $normalized = match ($mode) {
                'phone' => FieldCrypt::normalizePhone($plain),
                'phone_tail' => FieldCrypt::phoneTail(FieldCrypt::normalizePhone($plain)),
                'email' => FieldCrypt::normalizeEmail($plain),
                'national_id' => FieldCrypt::normalizeNationalId($plain),
                'lower' => mb_strtolower($plain),
                default => $plain,
            };

            $payload[$column] = FieldCrypt::blindIndex($normalized);
        }

        return $payload;
    }

    private function indexes(): array
    {
        if (! $this->indexMap) {
            return [];
        }

        $map = [];
        foreach (explode(',', $this->indexMap) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) === 2) {
                $map[$pair[0]] = $pair[1];
            }
        }

        return $map;
    }
}
