<?php

namespace App\Enums;

/**
 * Approval status for V1 §8 Change Log / Audit Trail.
 *
 * Spec §8.4 + §9 values: not_required, pending, approved, rejected.
 *
 * In MVP V1 the audit_logs table does NOT carry a stored approval
 * column — every committed audit row is implicitly NOT_REQUIRED.
 * Four-eyes approval workflow is forward-contract per §14 open
 * question "Which audit actions require four-eyes approval?".
 *
 * The enum surfaces all 4 values so the FE filter dropdown lines up
 * with the spec; deriveFrom() returns NOT_REQUIRED for V1 rows. When
 * the approval workflow lands, this is the single spot to update
 * (plus the migration adding `approval_status` / `approved_by_user_id`
 * / `approved_at` columns).
 */
class AuditApprovalStatus
{
    public const NOT_REQUIRED = 'not_required';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public static function all(): array
    {
        return [
            self::NOT_REQUIRED,
            self::PENDING,
            self::APPROVED,
            self::REJECTED,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::NOT_REQUIRED => 'Not required',
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::APPROVED => 'success',
            self::PENDING => 'info',
            self::REJECTED => 'danger',
            self::NOT_REQUIRED => 'neutral',
            default => 'neutral',
        };
    }
}
