<?php

namespace App\Enums;

class CarrierStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const BLOCKED = 'blocked';
    public const ARCHIVED = 'archived';

    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE, self::BLOCKED, self::ARCHIVED];
    }

    public static function label(string $v): string
    {
        return ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'offline',
            self::BLOCKED => 'danger',
            self::ARCHIVED => 'neutral',
            default => 'neutral',
        };
    }
}
