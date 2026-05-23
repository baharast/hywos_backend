<?php

namespace App\Enums;

class InspectionState
{
    public const VALID = 'valid';
    public const EXPIRING_SOON = 'expiring_soon';
    public const EXPIRED = 'expired';
    public const MISSING = 'missing';

    public static function all(): array
    {
        return [self::VALID, self::EXPIRING_SOON, self::EXPIRED, self::MISSING];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::VALID => 'Valid',
            self::EXPIRING_SOON => 'Expiring soon',
            self::EXPIRED => 'Expired',
            self::MISSING => 'Missing',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::VALID => 'success',
            self::EXPIRING_SOON => 'warning',
            self::EXPIRED => 'danger',
            self::MISSING => 'orange',
            default => 'neutral',
        };
    }
}
