<?php

namespace App\Enums;

/**
 * DERIVED action type for V1 §8 Change Log / Audit Trail.
 *
 * NOT a stored column — composed at read time from the action suffix
 * (tail after the dot). Per spec §8.3 filter dropdown:
 *   create, update, block, unblock, approve, reject, override,
 *   print/reprint, assign, unassign, configuration_change.
 *
 * The audit_logs.action column uses dotted "entity.action" format
 * (e.g. `driver.blocked`, `document.printed`). This enum extracts the
 * suffix and maps to a canonical type for the FE filter dropdown.
 */
class AuditActionType
{
    public const CREATE = 'create';
    public const UPDATE = 'update';
    public const BLOCK = 'block';
    public const UNBLOCK = 'unblock';
    public const APPROVE = 'approve';
    public const REJECT = 'reject';
    public const OVERRIDE = 'override';
    public const PRINT = 'print';
    public const REPRINT = 'reprint';
    public const ASSIGN = 'assign';
    public const UNASSIGN = 'unassign';
    public const CONFIGURATION_CHANGE = 'configuration_change';
    public const OTHER = 'other';

    public static function all(): array
    {
        return [
            self::CREATE, self::UPDATE, self::BLOCK, self::UNBLOCK,
            self::APPROVE, self::REJECT, self::OVERRIDE,
            self::PRINT, self::REPRINT, self::ASSIGN, self::UNASSIGN,
            self::CONFIGURATION_CHANGE, self::OTHER,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('logbook.audit_action_type.' . $v);
        return $translated !== 'logbook.audit_action_type.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::BLOCK, self::REJECT, self::OVERRIDE => 'warning',
            self::APPROVE, self::CREATE => 'success',
            self::UNBLOCK, self::UPDATE => 'info',
            default => 'neutral',
        };
    }

    /**
     * Map a full action string ("entity.action") to a canonical type.
     */
    public static function deriveFrom(string $action): string
    {
        $tail = explode('.', $action, 2)[1] ?? $action;
        $tail = strtolower($tail);

        if (str_contains($tail, 'created')) return self::CREATE;
        if (str_contains($tail, 'unblocked')) return self::UNBLOCK;
        if (str_contains($tail, 'blocked')) return self::BLOCK;
        if (str_contains($tail, 'unassigned')) return self::UNASSIGN;
        if (str_contains($tail, 'assigned')) return self::ASSIGN;
        if (str_contains($tail, 'approved')
            || str_contains($tail, 'released')
            || str_contains($tail, 'activated')
            || str_contains($tail, 'restored')) {
            return self::APPROVE;
        }
        if (str_contains($tail, 'rejected')
            || str_contains($tail, 'denied')
            || str_contains($tail, 'cancelled')
            || str_contains($tail, 'invalidated')
            || str_contains($tail, 'deactivated')
            || str_contains($tail, 'disabled')) {
            return self::REJECT;
        }
        if (str_contains($tail, 'override')
            || str_contains($tail, 'force')
            || str_contains($tail, 'manual_approval')) {
            return self::OVERRIDE;
        }
        if (str_contains($tail, 'reprinted')
            || str_contains($tail, 'rerouted')) {
            return self::REPRINT;
        }
        if (str_contains($tail, 'printed')
            || str_contains($tail, 'queued_for_print')
            || str_contains($tail, 'print')) {
            return self::PRINT;
        }
        if (str_contains($tail, 'config')
            || str_contains($tail, 'change_request')
            || str_contains($tail, 'service_mode')) {
            return self::CONFIGURATION_CHANGE;
        }
        if (str_contains($tail, 'updated')) return self::UPDATE;

        return self::OTHER;
    }
}
