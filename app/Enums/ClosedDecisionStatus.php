<?php

namespace App\Enums;

/**
 * V1.1 §12.2 closed-decision statuses. Default Results & Quality
 * Decisions list only surfaces records whose decision is one of these
 * (active/open ones live on Active Analyses until they close).
 */
class ClosedDecisionStatus
{
    public const APPROVED = 'approved';
    public const RELEASED = 'released';
    public const BLOCKED = 'blocked';
    public const REJECTED = 'rejected';
    public const CLOSED = 'closed';

    public static function all(): array
    {
        return [
            self::APPROVED, self::RELEASED, self::BLOCKED, self::REJECTED, self::CLOSED,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::APPROVED => 'Approved',
            self::RELEASED => 'Released',
            self::BLOCKED => 'Blocked',
            self::REJECTED => 'Rejected',
            self::CLOSED => 'Closed',
            default => ucfirst($v),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::APPROVED, self::RELEASED => 'success',
            self::BLOCKED, self::REJECTED => 'danger',
            self::CLOSED => 'neutral',
            default => 'neutral',
        };
    }
}
