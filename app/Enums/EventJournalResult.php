<?php

namespace App\Enums;

/**
 * DERIVED outcome / result for V1 §7.4 Event Journal.
 *
 * Spec §9 values: Info, Warning, Error, Success, Denied, Failed
 * (severity vocabulary), plus the §9 "Event outcome" set: accepted,
 * denied, failed, completed, timeout, blocked, started, stopped.
 *
 * NOT stored. Composed at read time from event_logs.severity +
 * event_type tail. The §7.4 "Severity / result" column blends both:
 *   - severity-driven: info / warning / error
 *   - outcome-driven: success / denied / failed / completed / etc.
 *
 * We surface ONE typed value per row, picking the more specific
 * outcome when the event_type tail contains a clear signal.
 */
class EventJournalResult
{
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const SUCCESS = 'success';
    public const DENIED = 'denied';
    public const FAILED = 'failed';
    public const COMPLETED = 'completed';
    public const TIMEOUT = 'timeout';
    public const BLOCKED = 'blocked';
    public const STARTED = 'started';
    public const STOPPED = 'stopped';

    public static function all(): array
    {
        return [
            self::INFO, self::WARNING, self::ERROR,
            self::SUCCESS, self::DENIED, self::FAILED, self::COMPLETED,
            self::TIMEOUT, self::BLOCKED, self::STARTED, self::STOPPED,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('alarms.journal_result.' . $v);
        return $translated !== 'alarms.journal_result.' . $v ? $translated : ucfirst($v);
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::SUCCESS, self::COMPLETED, self::STARTED => 'success',
            self::INFO => 'info',
            self::WARNING => 'warning',
            self::ERROR, self::FAILED, self::TIMEOUT, self::BLOCKED => 'danger',
            self::DENIED => 'warning',
            self::STOPPED => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * Pick the most specific result from event_type tail and severity.
     */
    public static function deriveFrom(?string $eventType, ?string $severity): string
    {
        $tail = $eventType === null ? '' : strtolower(explode('.', $eventType, 2)[1] ?? '');

        // Outcome-driven signals win when present.
        if (str_contains($tail, 'denied')) return self::DENIED;
        if (str_contains($tail, 'timeout')) return self::TIMEOUT;
        if (str_contains($tail, 'blocked')) return self::BLOCKED;
        if (str_contains($tail, 'failed') || str_contains($tail, 'failure')) return self::FAILED;
        if (str_contains($tail, 'completed') || str_contains($tail, 'closed') || str_contains($tail, 'finished')) return self::COMPLETED;
        if (str_contains($tail, 'started') || str_contains($tail, 'opened') || str_contains($tail, 'queued')) return self::STARTED;
        if (str_contains($tail, 'stopped') || str_contains($tail, 'paused') || str_contains($tail, 'cancelled')) return self::STOPPED;
        if (str_contains($tail, 'success') || str_contains($tail, 'accepted') || str_contains($tail, 'granted')) return self::SUCCESS;

        // Fallback on severity.
        return match (strtolower((string) $severity)) {
            EventSeverity::CRITICAL, EventSeverity::DANGER, 'error' => self::ERROR,
            EventSeverity::WARNING => self::WARNING,
            default => self::INFO,
        };
    }
}
