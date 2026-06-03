<?php

namespace App\Enums;

/**
 * Driver-facing session state at one of the 3 touchpoints. V2.3 §9.
 *
 * V2.3 deliberately removes `waiting` and `blocked`:
 *   - "waiting" surfaces as issue_reason + action_needed on an `active` row
 *   - "blocked" translates to `denied` (validation) or `needs_operator`
 *     (human review required) or `device_fault` depending on context
 */
class GateTerminalSessionState
{
    public const IDLE = 'idle';
    public const ACTIVE = 'active';
    public const DENIED = 'denied';
    public const NEEDS_OPERATOR = 'needs_operator';
    public const DEVICE_FAULT = 'device_fault';
    public const SERVICE_MODE = 'service_mode';

    public static function all(): array
    {
        return [
            self::IDLE,
            self::ACTIVE,
            self::DENIED,
            self::NEEDS_OPERATOR,
            self::DEVICE_FAULT,
            self::SERVICE_MODE,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('terminal.session_state.' . $v);
        return $translated !== 'terminal.session_state.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::IDLE => 'neutral',
            self::ACTIVE => 'info',
            self::DENIED => 'danger',
            self::NEEDS_OPERATOR => 'orange',
            self::DEVICE_FAULT => 'danger',
            self::SERVICE_MODE => 'maintenance',
            default => 'neutral',
        };
    }

    /**
     * Touchpoint-card display priority (V2.3 §6.3). Lower number = higher
     * priority. Used by the touchpoints endpoint to pick the "primary"
     * session shown on each card when multiple sessions are active.
     *
     * 1. device_fault           — open device / gate / reader fault
     * 2. denied / needs_operator — open denied / blocked / needs-operator
     * 3. service_mode           — touchpoint out for service
     * 4. active                 — current active driver session
     * 5. idle                   — calm state
     */
    public static function displayPriority(string $v): int
    {
        return match ($v) {
            self::DEVICE_FAULT => 1,
            self::DENIED => 2,
            self::NEEDS_OPERATOR => 2,
            self::SERVICE_MODE => 3,
            self::ACTIVE => 4,
            self::IDLE => 5,
            default => 6,
        };
    }
}
