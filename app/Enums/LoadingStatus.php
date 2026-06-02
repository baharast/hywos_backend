<?php

namespace App\Enums;

/**
 * LoadingState enum — the 11-value vocabulary from FillTrack Loading Control
 * UX Spec V3.2 §6.5 + §11.1.
 *
 * The class is named `LoadingStatus` for backward compatibility with the
 * existing model + seeder; the wire-level vocabulary is V3.2's `LoadingState`.
 *
 * Legacy DB-stored values (`assigned`, `released`, `paused`, `failed`,
 * `cancelled`, `quality_check_open`) are retained as @deprecated constants so
 * the `LoadingOperation` model and the existing seeded rows keep working.
 * Resources MUST run those DB values through {@see self::mapToWire()} before
 * emitting them on the wire.
 */
class LoadingStatus
{
    /* ============================================================
     * V3.2 wire vocabulary (canonical 11-value set, §11.1).
     * `all()`, `tone()`, `label()` all operate on these.
     * ============================================================
     */
    public const ASSIGNED_READY_FOR_BAY = 'assigned_ready_for_bay';
    public const WAITING_PRE_ANALYSIS = 'waiting_pre_analysis';
    public const READY_FOR_LOADING = 'ready_for_loading';
    public const LOADING = 'loading';
    public const PAUSED_WAITING = 'paused_waiting';
    public const COMPLETED = 'completed';
    public const WAITING_MAIN_ANALYSIS = 'waiting_main_analysis';
    public const QUALITY_BLOCKED = 'quality_blocked';
    public const DOCUMENTS_PENDING = 'documents_pending';
    public const CLARIFICATION_REQUIRED = 'clarification_required';
    public const FAULT_DEVICE_ISSUE = 'fault_device_issue';

    /* ============================================================
     * Legacy DB-level constants. Kept so `LoadingOperation::booted()` and
     * the seeder continue to compile. Do NOT emit these on the wire —
     * always translate via mapToWire() first.
     * ============================================================
     */
    /** @deprecated V3.2 — use ASSIGNED_READY_FOR_BAY on wire */
    public const ASSIGNED = 'assigned';
    /** @deprecated V3.2 — use READY_FOR_LOADING on wire */
    public const RELEASED = 'released';
    /** @deprecated V3.2 — use PAUSED_WAITING on wire */
    public const PAUSED = 'paused';
    /** @deprecated V3.2 — folds into DOCUMENTS_PENDING on wire */
    public const QUALITY_CHECK_OPEN = 'quality_check_open';
    /** @deprecated V3.2 — use FAULT_DEVICE_ISSUE on wire */
    public const FAILED = 'failed';
    /** @deprecated V3.2 — no equivalent, surfaced as completed on wire */
    public const CANCELLED = 'cancelled';

    /**
     * Wire-level V3.2 vocabulary. Used for `?loading_state=` filter
     * validation and badge dictionaries on the FE.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ASSIGNED_READY_FOR_BAY,
            self::WAITING_PRE_ANALYSIS,
            self::READY_FOR_LOADING,
            self::LOADING,
            self::PAUSED_WAITING,
            self::COMPLETED,
            self::WAITING_MAIN_ANALYSIS,
            self::QUALITY_BLOCKED,
            self::DOCUMENTS_PENDING,
            self::CLARIFICATION_REQUIRED,
            self::FAULT_DEVICE_ISSUE,
        ];
    }

    /**
     * DB-stored values that mean the loading is no longer active. Includes
     * BOTH the legacy values (`failed`, `cancelled`) and the V3.2 wire
     * vocabulary (`completed`) so {@see \App\Models\LoadingOperation::scopeActive}
     * filters every flavour correctly.
     *
     * @return list<string>
     */
    public static function terminal(): array
    {
        return [
            self::COMPLETED,
            self::FAILED,
            self::CANCELLED,
        ];
    }

    /**
     * Translate a DB-stored legacy value (or already-V3.2 value) into the V3.2
     * wire-level string. Pass `null` through unchanged.
     */
    public static function mapToWire(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return match ($value) {
            self::ASSIGNED => self::ASSIGNED_READY_FOR_BAY,
            self::RELEASED => self::READY_FOR_LOADING,
            self::PAUSED => self::PAUSED_WAITING,
            self::QUALITY_CHECK_OPEN => self::DOCUMENTS_PENDING,
            self::FAILED => self::FAULT_DEVICE_ISSUE,
            self::CANCELLED => self::COMPLETED,
            // Already in wire vocabulary (or unknown — pass through so the
            // operator sees the raw value rather than a silent default).
            default => $value,
        };
    }

    public static function tone(string $value): string
    {
        $wire = self::mapToWire($value);
        return match ($wire) {
            self::COMPLETED => 'success',
            self::READY_FOR_LOADING, self::LOADING => 'info',
            self::ASSIGNED_READY_FOR_BAY => 'neutral',
            self::WAITING_PRE_ANALYSIS,
            self::WAITING_MAIN_ANALYSIS,
            self::PAUSED_WAITING,
            self::DOCUMENTS_PENDING => 'warning',
            self::CLARIFICATION_REQUIRED => 'orange',
            self::QUALITY_BLOCKED, self::FAULT_DEVICE_ISSUE => 'danger',
            default => 'neutral',
        };
    }

    public static function label(string $value): string
    {
        $wire = self::mapToWire($value);
        if ($wire === null) {
            return ucfirst(str_replace('_', ' ', $value));
        }
        $translated = __('loading.loading_status.' . $wire);
        return $translated !== 'loading.loading_status.' . $wire
            ? $translated
            : ucfirst(str_replace('_', ' ', $wire));
    }
}
