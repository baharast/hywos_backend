<?php

namespace App\Enums;

/**
 * Typed view over `document_print_attempts.status` — the column itself
 * is a free-form string(30) so the underlying D1 emitter can persist
 * any value, but the V1.4 §6 Printers tab compares against these
 * canonical names.
 *
 * `reprinted` is a D1 terminal state (an attempt that was later
 * replaced by a reprint — see DocumentPrintAttempt::is_reprint).
 * `rerouted` is the NEW terminal state introduced by V1.4 §6: an
 * attempt that was sent to a DIFFERENT printer via the reroute action.
 * `cancelled` is the human-cancellation terminal state (rarely
 * produced today; the demo seeder seeds one row to round out the
 * tone palette).
 */
class PrinterJobStatus
{
    public const QUEUED = 'queued';
    public const PRINTING = 'printing';
    public const PRINTED = 'printed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const REPRINTED = 'reprinted';
    public const REROUTED = 'rerouted';

    public static function all(): array
    {
        return [
            self::QUEUED,
            self::PRINTING,
            self::PRINTED,
            self::FAILED,
            self::CANCELLED,
            self::REPRINTED,
            self::REROUTED,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::QUEUED => 'Queued',
            self::PRINTING => 'Printing',
            self::PRINTED => 'Printed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::REPRINTED => 'Reprinted',
            self::REROUTED => 'Rerouted',
            default => ucfirst($v),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::QUEUED => 'info',
            self::PRINTING => 'info',
            self::PRINTED => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'neutral',
            self::REPRINTED => 'warning',
            self::REROUTED => 'warning',
            default => 'neutral',
        };
    }

    /**
     * V1.4 §6 — retry is offered only for `failed` and `cancelled` jobs.
     * A `queued`/`printing` job is already on its way; a `printed` /
     * `reprinted` / `rerouted` job has reached a terminal state.
     */
    public static function isRetryable(string $v): bool
    {
        return in_array($v, [self::FAILED, self::CANCELLED], true);
    }

    /**
     * V1.4 §6 — reroute is offered ONLY for `failed`. A cancelled job is
     * a human stop; the operator should re-queue from D1, not bounce it.
     */
    public static function isReroutable(string $v): bool
    {
        return $v === self::FAILED;
    }
}
