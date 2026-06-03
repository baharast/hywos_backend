<?php

namespace App\Enums;

class TrailerChipState
{
    public const ASSIGNED = 'assigned';
    public const MISSING = 'missing';
    public const BLOCKED = 'blocked';
    public const MISMATCH = 'mismatch';
    public const NOT_REQUIRED = 'not_required';

    public static function all(): array
    {
        return [self::ASSIGNED, self::MISSING, self::BLOCKED, self::MISMATCH, self::NOT_REQUIRED];
    }

    public static function label(string $v): string
    {
        $translated = __('vehicles.trailer_chip_state.' . $v);
        return $translated !== 'vehicles.trailer_chip_state.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ASSIGNED => 'info',
            self::NOT_REQUIRED => 'neutral',
            self::MISSING => 'warning',
            self::MISMATCH => 'orange',
            self::BLOCKED => 'danger',
            default => 'neutral',
        };
    }
}
