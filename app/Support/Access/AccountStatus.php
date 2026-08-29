<?php

namespace App\Support\Access;

class AccountStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const SUSPENDED = 'suspended';

    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE, self::SUSPENDED];
    }

    public static function allowsAuthentication(?string $status): bool
    {
        return ($status ?: self::ACTIVE) === self::ACTIVE;
    }

    public static function denialMessage(?string $status): string
    {
        return $status === self::SUSPENDED
            ? 'This account is suspended'
            : 'This account is inactive';
    }
}
