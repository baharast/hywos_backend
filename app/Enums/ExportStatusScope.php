<?php

namespace App\Enums;

class ExportStatusScope
{
    public const ACTIVE_CURRENT_ONLY = 'active_current_only';
    public const INCLUDE_INACTIVE_BLOCKED_ARCHIVED = 'include_inactive_blocked_archived';
    public const ALL_STATUSES = 'all_statuses';

    public static function all(): array
    {
        return [self::ACTIVE_CURRENT_ONLY, self::INCLUDE_INACTIVE_BLOCKED_ARCHIVED, self::ALL_STATUSES];
    }
}
