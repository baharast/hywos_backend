<?php

namespace App\Enums;

/**
 * TAN status as exposed to the UI. NOT a DB column — derived at read-time
 * from `auth_media.status`, `revoked_at`, `consumed_at`, `expires_at` and
 * `valid_from`.
 */
class TanStatus
{
    public const ACTIVE = 'active';
    public const PENDING = 'pending';
    public const EXPIRED = 'expired';
    public const CONSUMED = 'consumed';
    public const REVOKED = 'revoked';
    public const BLOCKED = 'blocked';

    public static function all(): array
    {
        return [
            self::ACTIVE, self::PENDING, self::EXPIRED,
            self::CONSUMED, self::REVOKED, self::BLOCKED,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('vehicles.tan_status.' . $v);
        return $translated !== 'vehicles.tan_status.' . $v ? $translated : ucfirst($v);
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ACTIVE => 'success',
            self::PENDING => 'info',
            self::EXPIRED => 'offline',
            self::CONSUMED => 'info',
            self::REVOKED => 'danger',
            self::BLOCKED => 'danger',
            default => 'neutral',
        };
    }
}
