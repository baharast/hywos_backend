<?php

namespace App\Enums;

/**
 * Chip-specific view over `auth_media.status`. EXPIRED is derived from `expires_at`
 * at read-time even when the underlying status column is still `active`.
 */
class ChipCardLifecycleStatus
{
    public const ACTIVE = 'active';
    public const BLOCKED = 'blocked';
    public const LOST = 'lost';
    public const DEFECTIVE = 'defective';
    public const EXPIRED = 'expired';
    public const REPLACED = 'replaced';
    public const ARCHIVED = 'archived';

    public static function all(): array
    {
        return [
            self::ACTIVE, self::BLOCKED, self::LOST, self::DEFECTIVE,
            self::EXPIRED, self::REPLACED, self::ARCHIVED,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::ACTIVE => 'Active',
            self::BLOCKED => 'Blocked',
            self::LOST => 'Lost',
            self::DEFECTIVE => 'Defective',
            self::EXPIRED => 'Expired',
            self::REPLACED => 'Replaced',
            self::ARCHIVED => 'Archived',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ACTIVE => 'success',
            self::BLOCKED => 'danger',
            self::LOST => 'danger',
            self::DEFECTIVE => 'warning',
            self::EXPIRED => 'orange',
            self::REPLACED => 'info',
            self::ARCHIVED => 'offline',
            default => 'neutral',
        };
    }
}
