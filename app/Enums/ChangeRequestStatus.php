<?php

namespace App\Enums;

class ChangeRequestStatus
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const APPLIED = 'applied';

    public static function all(): array
    {
        return [self::DRAFT, self::SUBMITTED, self::APPROVED, self::REJECTED, self::APPLIED];
    }
}
