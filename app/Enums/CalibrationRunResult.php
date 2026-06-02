<?php

namespace App\Enums;

/**
 * Read-only outcome of the latest calibration validation run for one
 * component (V2.1 §5.5). Set by the calibration run / device interface,
 * NEVER entered by the user — the form must not surface this as an
 * editable field (§5.7).
 */
class CalibrationRunResult
{
    public const PASS = 'pass';
    public const FAIL = 'fail';
    public const NOT_RECORDED = 'not_recorded';

    public static function all(): array
    {
        return [self::PASS, self::FAIL, self::NOT_RECORDED];
    }

    public static function label(string $v): string
    {
        $translated = __('analysis.calibration_run_result.' . $v);
        return $translated !== 'analysis.calibration_run_result.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::PASS => 'success',
            self::FAIL => 'danger',
            self::NOT_RECORDED => 'neutral',
            default => 'neutral',
        };
    }
}
