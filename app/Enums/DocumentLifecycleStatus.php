<?php

namespace App\Enums;

/**
 * Lifecycle status for an operational document.
 *
 * Per FillTrack Operational Documents UX Frontend Spec-EN-V1.2 §12.1
 * (Lifecycle Statuses), §16 (TypeScript shapes — DocumentLifecycleStatus)
 * and §17 (Status Badge Mapping).
 *
 * 10 values total. `invalidated` and `cancelled` are both terminal states
 * — `invalidated` is the operational/safety annulment (backend-driven),
 * `cancelled` is the lifecycle abort before generation/handover. Both
 * map to neutral tone by default; UI may elevate to danger when the
 * document still blocks exit.
 */
class DocumentLifecycleStatus
{
    public const PENDING = 'pending';
    public const GENERATED = 'generated';
    public const QUEUED_FOR_PRINT = 'queued_for_print';
    public const PRINTED = 'printed';
    public const PRINT_FAILED = 'print_failed';
    public const REPRINTED = 'reprinted';
    public const HANDED_OVER = 'handed_over';
    public const BLOCKED = 'blocked';
    public const INVALIDATED = 'invalidated';
    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::GENERATED,
            self::QUEUED_FOR_PRINT,
            self::PRINTED,
            self::PRINT_FAILED,
            self::REPRINTED,
            self::HANDED_OVER,
            self::BLOCKED,
            self::INVALIDATED,
            self::CANCELLED,
        ];
    }

    /**
     * Statuses that are still operationally active — the document still
     * needs attention or downstream action. Excludes terminal end-states
     * (handed_over, invalidated, cancelled).
     */
    public static function activeStatuses(): array
    {
        return [
            self::PENDING,
            self::GENERATED,
            self::QUEUED_FOR_PRINT,
            self::PRINTED,
            self::PRINT_FAILED,
            self::REPRINTED,
            self::BLOCKED,
        ];
    }

    /**
     * Statuses that block exit. UI uses this to drive the exception-first
     * sort and the Exit Block column.
     */
    public static function exitBlockingStatuses(): array
    {
        return [
            self::PENDING,
            self::PRINT_FAILED,
            self::BLOCKED,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('masterdata.document_lifecycle_status.' . $v);
        return $translated !== 'masterdata.document_lifecycle_status.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::PENDING => 'warning',
            self::GENERATED => 'info',
            self::QUEUED_FOR_PRINT => 'info',
            self::PRINTED => 'success',
            self::PRINT_FAILED => 'danger',
            self::REPRINTED => 'info',
            self::HANDED_OVER => 'success',
            self::BLOCKED => 'danger',
            self::INVALIDATED => 'neutral',
            self::CANCELLED => 'neutral',
            default => 'neutral',
        };
    }
}
