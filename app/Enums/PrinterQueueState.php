<?php

namespace App\Enums;

/**
 * DERIVED queue state for the V1.4 §6 Printers tab.
 *
 * NOT a stored column — composed at read time from the live snapshot of
 * `document_print_attempts` rows attached to a printer. The "Health /
 * Queue" column on the tab combines this with the registry health.
 *
 * Priority order (worst first) when multiple signals are present:
 *   failures > printing > queued > idle
 *
 * Spec §6: "Combines printer health and queue state so the user sees
 * whether the printer is usable." This enum is the queue half — the
 * registry health is the other half.
 */
class PrinterQueueState
{
    public const IDLE = 'idle';
    public const QUEUED = 'queued';
    public const PRINTING = 'printing';
    public const FAILURES = 'failures';

    public static function all(): array
    {
        return [self::IDLE, self::QUEUED, self::PRINTING, self::FAILURES];
    }

    public static function label(string $v): string
    {
        $translated = __('hardware.printer_queue_state.' . $v);
        return $translated !== 'hardware.printer_queue_state.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::IDLE => 'neutral',
            self::QUEUED => 'info',
            self::PRINTING => 'info',
            self::FAILURES => 'danger',
            default => 'neutral',
        };
    }

    /**
     * Compose the queue state from raw counts on document_print_attempts.
     *
     * @param int $printing count of attempts in `printing`
     * @param int $queued   count of attempts in `queued`
     * @param int $failed   count of attempts in `failed` (not superseded)
     */
    public static function derive(int $printing, int $queued, int $failed): string
    {
        if ($failed > 0) return self::FAILURES;
        if ($printing > 0) return self::PRINTING;
        if ($queued > 0) return self::QUEUED;
        return self::IDLE;
    }
}
