<?php

namespace App\Enums;

/**
 * V1 §7.6 Security event result column. Spec values:
 *   Success, failed, denied, locked, expired, blocked.
 *
 * DERIVED from event_type tail at read time.
 */
class SecurityResult
{
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const DENIED = 'denied';
    public const LOCKED = 'locked';
    public const EXPIRED = 'expired';
    public const BLOCKED = 'blocked';
    public const UNKNOWN = 'unknown';

    public static function all(): array
    {
        return [
            self::SUCCESS, self::FAILED, self::DENIED,
            self::LOCKED, self::EXPIRED, self::BLOCKED, self::UNKNOWN,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::DENIED => 'Denied',
            self::LOCKED => 'Locked',
            self::EXPIRED => 'Expired',
            self::BLOCKED => 'Blocked',
            self::UNKNOWN => 'Unknown',
            default => ucfirst($v),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::SUCCESS => 'success',
            self::FAILED, self::BLOCKED, self::LOCKED => 'danger',
            self::DENIED, self::EXPIRED => 'warning',
            self::UNKNOWN => 'neutral',
            default => 'neutral',
        };
    }

    public static function deriveFrom(?string $eventType): string
    {
        if ($eventType === null) return self::UNKNOWN;
        $tail = strtolower(explode('.', $eventType, 2)[1] ?? '');
        if (str_contains($tail, 'locked')) return self::LOCKED;
        if (str_contains($tail, 'expired')) return self::EXPIRED;
        if (str_contains($tail, 'blocked')) return self::BLOCKED;
        if (str_contains($tail, 'denied')) return self::DENIED;
        if (str_contains($tail, 'failed') || str_contains($tail, 'failure')) return self::FAILED;
        if (str_contains($tail, 'success') || str_contains($tail, 'granted') || str_contains($tail, 'logged_in')) return self::SUCCESS;
        return self::UNKNOWN;
    }
}
