<?php

namespace App\Enums;

/**
 * Which analysis steps a Product Gas Limit row applies to (V2.1 §4.2,
 * §11 — `appliesToAnalysisTypes` multi-select on each gas-limit row).
 *
 * One row may apply to several steps (e.g. H2 lower-limit is checked at
 * pre-, main-, and final analysis), so the field is an array on the row.
 */
class AnalysisTypeApplicable
{
    public const PRE_ANALYSIS = 'pre_analysis';
    public const MAIN_ANALYSIS = 'main_analysis';
    public const FINAL_ANALYSIS = 'final_analysis';
    public const RETEST = 'retest';

    public static function all(): array
    {
        return [
            self::PRE_ANALYSIS,
            self::MAIN_ANALYSIS,
            self::FINAL_ANALYSIS,
            self::RETEST,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('analysis.type.' . $v);
        return $translated !== 'analysis.type.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }
}
