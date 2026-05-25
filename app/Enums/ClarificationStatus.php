<?php

namespace App\Enums;

/**
 * Lifecycle status for a clarification case.
 *
 * Per FillTrack Clarification Cases UX Frontend Spec-EN-V1.3 §4.1 (Case
 * Status) and §14 (TypeScript shapes).
 *
 * V1.3 deliberately has NO `cancelled` status — terminal end-state is
 * `closed`. "Closing without resolution" is recorded via resolution_note
 * + audit trail, not via a separate status.
 */
class ClarificationStatus
{
    public const OPEN = 'open';
    public const IN_PROGRESS = 'in_progress';
    public const WAITING_FOR_OWNER = 'waiting_for_owner';
    public const RESOLVED = 'resolved';
    public const CLOSED = 'closed';

    public static function all(): array
    {
        return [
            self::OPEN,
            self::IN_PROGRESS,
            self::WAITING_FOR_OWNER,
            self::RESOLVED,
            self::CLOSED,
        ];
    }

    /**
     * Statuses that should appear in the default "active" filter of the
     * Clarification Cases list page. Resolved is included because §4.1 says
     * resolved cases are still surfaced for verification before close;
     * closed cases are archived and filter-only (§4.1 UI rule).
     */
    public static function activeStatuses(): array
    {
        return [
            self::OPEN,
            self::IN_PROGRESS,
            self::WAITING_FOR_OWNER,
            self::RESOLVED,
        ];
    }

    /**
     * Open (non-terminal) statuses — used by `scopeOpen` on the model and
     * by summary counts (`totalOpen`, `criticalOpen`, `blockingOpen`).
     * Excludes resolved/closed.
     */
    public static function openStatuses(): array
    {
        return [
            self::OPEN,
            self::IN_PROGRESS,
            self::WAITING_FOR_OWNER,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::WAITING_FOR_OWNER => 'Waiting for Owner',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::OPEN => 'orange',
            self::IN_PROGRESS => 'info',
            self::WAITING_FOR_OWNER => 'warning',
            self::RESOLVED => 'success',
            self::CLOSED => 'offline',
            default => 'neutral',
        };
    }
}
