<?php

namespace App\Enums;

/**
 * Lifecycle status for a Product Specification (V2.1 §9 + §6 status model).
 *
 *   draft    — being configured; can be edited without reason
 *   active   — in use; edits require reason + create a new revision
 *   inactive — temporarily disabled but not retired
 *   retired  — historical; never used for new analyses
 */
class ProductSpecStatus
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const RETIRED = 'retired';

    public static function all(): array
    {
        return [self::DRAFT, self::ACTIVE, self::INACTIVE, self::RETIRED];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::RETIRED => 'Retired',
            default => ucfirst($v),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::DRAFT => 'info',
            self::ACTIVE => 'success',
            self::INACTIVE => 'neutral',
            self::RETIRED => 'offline',
            default => 'neutral',
        };
    }

    /**
     * Whether rows under this status can still be edited (V2.1 §7). Draft
     * rows are freely editable; active rows require reason on each edit;
     * inactive/retired rows are frozen.
     */
    public static function isEditable(string $v): bool
    {
        return in_array($v, [self::DRAFT, self::ACTIVE], true);
    }

    /**
     * Whether an existing-value edit requires the `reason` field. Per V2.1
     * §7 only draft skips the requirement; active demands a reason.
     */
    public static function requiresReasonForEdit(string $v): bool
    {
        return $v === self::ACTIVE;
    }
}
