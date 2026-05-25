<?php

namespace App\Enums;

class ClarificationStatus
{
    public const OPEN = 'open';
    public const IN_REVIEW = 'in_review';
    public const RESOLVED = 'resolved';
    public const CLOSED = 'closed';
    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [self::OPEN, self::IN_REVIEW, self::RESOLVED, self::CLOSED, self::CANCELLED];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::OPEN => 'Open',
            self::IN_REVIEW => 'In Review',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
            self::CANCELLED => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::OPEN => 'orange',
            self::IN_REVIEW => 'warning',
            self::RESOLVED => 'success',
            self::CLOSED => 'offline',
            self::CANCELLED => 'offline',
            default => 'neutral',
        };
    }
}
