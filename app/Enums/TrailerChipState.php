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
        return match ($v) {
            self::ASSIGNED => 'Chip assigned',
            self::MISSING => 'Missing',
            self::BLOCKED => 'Blocked',
            self::MISMATCH => 'Mismatch',
            self::NOT_REQUIRED => 'Not required',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
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
