<?php

namespace App\Enums;

/**
 * V1 §7.5 Logbook category. Spec values:
 *   Safety note, operations note, manual action, handover note,
 *   device follow-up, near miss, information.
 */
class LogbookCategory
{
    public const SAFETY_NOTE = 'safety_note';
    public const OPERATIONS_NOTE = 'operations_note';
    public const MANUAL_ACTION = 'manual_action';
    public const HANDOVER_NOTE = 'handover_note';
    public const DEVICE_FOLLOW_UP = 'device_follow_up';
    public const NEAR_MISS = 'near_miss';
    public const INFORMATION = 'information';

    public static function all(): array
    {
        return [
            self::SAFETY_NOTE, self::OPERATIONS_NOTE, self::MANUAL_ACTION,
            self::HANDOVER_NOTE, self::DEVICE_FOLLOW_UP, self::NEAR_MISS,
            self::INFORMATION,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('logbook.category.' . $v);
        return $translated !== 'logbook.category.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::SAFETY_NOTE, self::NEAR_MISS => 'warning',
            self::MANUAL_ACTION => 'info',
            self::HANDOVER_NOTE => 'info',
            self::DEVICE_FOLLOW_UP => 'info',
            default => 'neutral',
        };
    }
}
