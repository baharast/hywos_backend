<?php

namespace App\Enums;

/**
 * The 3 driver-facing touchpoints monitored by Gate & Terminal Monitor V2.3.
 *
 * Bay Line 1-6 is NOT a touchpoint — those belong to Loading Control. The
 * board on this page is fixed at exactly 3 cards (V2.3 §6).
 */
class GateTerminalTouchpoint
{
    public const ENTRY_GATE = 'entry_gate';
    public const DRIVER_TERMINAL = 'driver_terminal';
    public const EXIT_GATE = 'exit_gate';

    public static function all(): array
    {
        return [self::ENTRY_GATE, self::DRIVER_TERMINAL, self::EXIT_GATE];
    }

    public static function label(string $v): string
    {
        $translated = __('terminal.touchpoint.' . $v);
        return $translated !== 'terminal.touchpoint.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }
}
