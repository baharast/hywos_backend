<?php

namespace App\Enums;

/**
 * Result codes emitted by the safe connection-test endpoint per V1.4
 * §10. The test is non-invasive — it does NOT issue PLC writes or
 * device commands; it only records a timestamp + maps the current
 * health into a result for the FE to display.
 */
class HardwareConnectionTestResult
{
    public const PASSED = 'passed';
    public const FAILED = 'failed';
    public const TIMEOUT = 'timeout';
    public const NOT_SUPPORTED = 'not_supported';

    public static function all(): array
    {
        return [self::PASSED, self::FAILED, self::TIMEOUT, self::NOT_SUPPORTED];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::PASSED => 'Passed',
            self::FAILED => 'Failed',
            self::TIMEOUT => 'Timeout',
            self::NOT_SUPPORTED => 'Not supported',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::PASSED => 'success',
            self::FAILED => 'danger',
            self::TIMEOUT => 'warning',
            self::NOT_SUPPORTED => 'neutral',
            default => 'neutral',
        };
    }
}
