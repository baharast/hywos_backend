<?php

namespace App\Enums;

/**
 * V1 §7.6 Security event review status.
 *
 * Spec §7.6 "More filters": unreviewed / in_review / reviewed /
 * false_positive (if supported).
 *
 * Stored as a column on a future `security_event_reviews` table. V1
 * does NOT yet persist review state — all rows surface as `unreviewed`
 * until the mark-reviewed write surface lands.
 */
class SecurityReviewStatus
{
    public const UNREVIEWED = 'unreviewed';
    public const IN_REVIEW = 'in_review';
    public const REVIEWED = 'reviewed';
    public const FALSE_POSITIVE = 'false_positive';

    public static function all(): array
    {
        return [
            self::UNREVIEWED,
            self::IN_REVIEW,
            self::REVIEWED,
            self::FALSE_POSITIVE,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('terminal.security_review_status.' . $v);
        return $translated !== 'terminal.security_review_status.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::UNREVIEWED => 'warning',
            self::IN_REVIEW => 'info',
            self::REVIEWED => 'success',
            self::FALSE_POSITIVE => 'neutral',
            default => 'neutral',
        };
    }
}
