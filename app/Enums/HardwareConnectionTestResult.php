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
        $translated = __('hardware.connection_test_result.' . $v);
        return $translated !== 'hardware.connection_test_result.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
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
