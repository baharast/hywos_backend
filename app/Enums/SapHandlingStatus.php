<?php

namespace App\Enums;

/**
 * Simplified handling state shown to the dashboard user (V1.5 §9.3).
 *
 * Backend-derived. The technical reality may include retry-failed /
 * manual-support-required, but both collapse into `needs_support` for
 * dashboard users so the page stays simple and operational.
 */
class SapHandlingStatus
{
    public const NO_ACTION_NEEDED = 'no_action_needed';
    public const AUTO_RETRY_SCHEDULED = 'auto_retry_scheduled';
    public const NEEDS_SUPPORT = 'needs_support';
    public const RESOLVED = 'resolved';

    public static function all(): array
    {
        return [
            self::NO_ACTION_NEEDED,
            self::AUTO_RETRY_SCHEDULED,
            self::NEEDS_SUPPORT,
            self::RESOLVED,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('masterdata.sap_handling_status.' . $v);
        return $translated !== 'masterdata.sap_handling_status.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::NO_ACTION_NEEDED => 'neutral',
            self::AUTO_RETRY_SCHEDULED => 'info',
            self::NEEDS_SUPPORT => 'warning',
            self::RESOLVED => 'success',
            default => 'neutral',
        };
    }
}
