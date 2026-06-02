<?php

namespace App\Enums;

/**
 * Per FillTrack Results & Quality Decisions V1.1 §12.1.
 *
 * Derived enum — NOT a stored column. The service computes it from the
 * `analyses` row + its `analysis_element_results` rows at read time:
 *
 *   passed     — all 6 element rows are `valid` AND every limit holds
 *   nok        — at least one valid element value failed its limit
 *   invalid    — at least one element is untrusted (stale / device / cal)
 *   incomplete — at least one required element is missing / not transferred
 */
class ResultStatus
{
    public const PASSED = 'passed';
    public const NOK = 'nok';
    public const INVALID = 'invalid';
    public const INCOMPLETE = 'incomplete';

    public static function all(): array
    {
        return [self::PASSED, self::NOK, self::INVALID, self::INCOMPLETE];
    }

    public static function label(string $v): string
    {
        $translated = __('analysis.result_status.' . $v);
        return $translated !== 'analysis.result_status.' . $v ? $translated : ucfirst($v);
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::PASSED => 'success',
            self::NOK => 'danger',
            self::INVALID => 'warning',
            self::INCOMPLETE => 'warning',
            default => 'neutral',
        };
    }
}
