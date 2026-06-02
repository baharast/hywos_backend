<?php

namespace App\Enums;

/**
 * Per-element status in the 6-element comparison table (V1.4 §12 + §13).
 *
 * Critical distinction:
 *   NOK     — value is VALID + TRUSTED but failed the limit
 *   INVALID — value cannot be trusted (stale / device fault / calibration)
 *   MISSING — required element has no value at all
 *
 * UI must visually + semantically separate NOK and INVALID (V1.4 §3).
 */
class AnalysisElementStatus
{
    public const WAITING = 'waiting';
    public const RECEIVED = 'received';
    public const VALID = 'valid';
    public const NOK = 'nok';
    public const MISSING = 'missing';
    public const INVALID = 'invalid';
    public const NOT_TRANSFERRED = 'not_transferred';

    public static function all(): array
    {
        return [
            self::WAITING, self::RECEIVED, self::VALID, self::NOK,
            self::MISSING, self::INVALID, self::NOT_TRANSFERRED,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('analysis.element_status.' . $v);
        return $translated !== 'analysis.element_status.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::VALID, self::RECEIVED => 'success',
            self::WAITING => 'info',
            self::NOK => 'danger',
            self::INVALID, self::MISSING, self::NOT_TRANSFERRED => 'warning',
            default => 'neutral',
        };
    }
}
