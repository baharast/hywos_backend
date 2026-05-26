<?php

namespace App\Enums;

/**
 * The 3 MVP analysis-device types per V1 §3.
 *
 * Distinct from `gas_warning_channel` (which is rendered as a sub-row on
 * the status-table, not as a top-level device card).
 */
class AnalysisDeviceType
{
    public const ANALYSER = 'orthosmart_analyser';
    public const GAS_WARNING_CONTROLLER = 'gas_warning_controller';
    public const SAMPLE_SWITCHING_MODULE = 'sample_switching_module';

    public static function all(): array
    {
        return [
            self::ANALYSER,
            self::GAS_WARNING_CONTROLLER,
            self::SAMPLE_SWITCHING_MODULE,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::ANALYSER => 'OrthoSmart Analyser',
            self::GAS_WARNING_CONTROLLER => 'GWA-REGARD3900 (Gas Warning)',
            self::SAMPLE_SWITCHING_MODULE => 'CGS-SAM1000DP2 (Sample Switching)',
            default => $v,
        };
    }

    /**
     * FE icon hint — the card chooses an icon, the backend just suggests.
     */
    public static function iconHint(string $v): string
    {
        return match ($v) {
            self::ANALYSER => 'flask',
            self::GAS_WARNING_CONTROLLER => 'shield-alert',
            self::SAMPLE_SWITCHING_MODULE => 'split',
            default => 'cpu',
        };
    }
}
