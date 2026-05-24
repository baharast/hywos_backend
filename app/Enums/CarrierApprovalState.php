<?php

namespace App\Enums;

class CarrierApprovalState
{
    public const APPROVED = 'approved';
    public const PENDING_REVIEW = 'pending_review';
    public const MISSING_DATA = 'missing_data';
    public const NOT_APPROVED = 'not_approved';

    public static function all(): array
    {
        return [self::APPROVED, self::PENDING_REVIEW, self::MISSING_DATA, self::NOT_APPROVED];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::APPROVED => 'Approved',
            self::PENDING_REVIEW => 'Pending Review',
            self::MISSING_DATA => 'Missing Data',
            self::NOT_APPROVED => 'Not Approved',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::APPROVED => 'success',
            self::PENDING_REVIEW => 'warning',
            self::MISSING_DATA => 'orange',
            self::NOT_APPROVED => 'danger',
            default => 'neutral',
        };
    }
}
