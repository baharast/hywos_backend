<?php

namespace App\Enums;

/**
 * Lifecycle status of an Active Analysis JOB. Per V1.4 §17 (13 values).
 *
 * NOTE: this is distinct from the existing `App\Enums\AnalysisStatus`,
 * which is the `analysis_status` column on LoadingOperation rows in the
 * Loading Control view (different concept, different lifecycle). Both
 * enums coexist intentionally.
 */
class ActiveAnalysisStatus
{
    // In-progress (system-managed; no user decision yet)
    public const QUEUED = 'queued';
    public const PREPARING = 'preparing';
    public const PURGING = 'purging';
    public const RUNNING = 'running';
    public const WAITING_RESULT = 'waiting_result';
    public const RESULT_RECEIVED = 'result_received';

    // Decision states (a user action is allowed)
    public const WAITING_DECISION = 'waiting_decision';
    public const INVALID = 'invalid';                // technically untrusted
    public const NOK = 'nok';                        // limit failed
    public const FAILED = 'failed';                  // analysis run failed

    // Held / cancelled / closed
    public const ON_HOLD = 'on_hold';
    public const CANCELLED = 'cancelled';
    public const CLOSED = 'closed';                  // resolved + registered to Results

    public static function all(): array
    {
        return [
            self::QUEUED, self::PREPARING, self::PURGING, self::RUNNING,
            self::WAITING_RESULT, self::RESULT_RECEIVED, self::WAITING_DECISION,
            self::INVALID, self::NOK, self::FAILED,
            self::ON_HOLD, self::CANCELLED, self::CLOSED,
        ];
    }

    /**
     * Statuses that still appear in the Active Analyses queue (the page
     * is "open analyses only" per V1.4 §4.1). CANCELLED and CLOSED move
     * to Results & Quality Decisions instead.
     */
    public static function openStatuses(): array
    {
        return [
            self::QUEUED, self::PREPARING, self::PURGING, self::RUNNING,
            self::WAITING_RESULT, self::RESULT_RECEIVED, self::WAITING_DECISION,
            self::INVALID, self::NOK, self::FAILED, self::ON_HOLD,
        ];
    }

    /**
     * Statuses where a user decision action might be available. Used by
     * the action-required summary tile.
     */
    public static function decisionStatuses(): array
    {
        return [
            self::WAITING_DECISION, self::INVALID, self::NOK, self::FAILED,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::QUEUED => 'Queued',
            self::PREPARING => 'Preparing',
            self::PURGING => 'Purging',
            self::RUNNING => 'Running',
            self::WAITING_RESULT => 'Waiting result',
            self::RESULT_RECEIVED => 'Result received',
            self::WAITING_DECISION => 'Waiting decision',
            self::INVALID => 'Invalid',
            self::NOK => 'NOK',
            self::FAILED => 'Failed',
            self::ON_HOLD => 'On hold',
            self::CANCELLED => 'Cancelled',
            self::CLOSED => 'Closed',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::QUEUED, self::PREPARING, self::PURGING, self::RUNNING,
            self::WAITING_RESULT, self::RESULT_RECEIVED => 'info',
            self::WAITING_DECISION => 'warning',
            self::INVALID, self::NOK, self::FAILED => 'danger',
            self::ON_HOLD => 'orange',
            self::CANCELLED => 'offline',
            self::CLOSED => 'success',
            default => 'neutral',
        };
    }
}
