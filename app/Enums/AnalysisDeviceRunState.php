<?php

namespace App\Enums;

/**
 * V1 §8.1 — OrthoSmart-only run state. Independent of `health_status`
 * (a healthy analyser can be `measuring` OR `idle`; a faulted one is
 * `fault` regardless of whether anyone tried to start a run).
 */
class AnalysisDeviceRunState
{
    public const READY = 'ready';
    public const PREPARING = 'preparing';
    public const MEASURING = 'measuring';
    public const RESULT_TRANSFER = 'result_transfer';
    public const IDLE = 'idle';
    public const FAULT = 'fault';

    public static function all(): array
    {
        return [
            self::READY,
            self::PREPARING,
            self::MEASURING,
            self::RESULT_TRANSFER,
            self::IDLE,
            self::FAULT,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('analysis.device_run_state.' . $v);
        return $translated !== 'analysis.device_run_state.' . $v ? $translated : ucfirst($v);
    }
}
