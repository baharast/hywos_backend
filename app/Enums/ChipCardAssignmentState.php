<?php

namespace App\Enums;

class ChipCardAssignmentState
{
    public const UNASSIGNED = 'unassigned';
    public const ASSIGNED = 'assigned';
    public const REPLACED = 'replaced';
    public const ARCHIVED = 'archived';

    public static function all(): array
    {
        return [self::UNASSIGNED, self::ASSIGNED, self::REPLACED, self::ARCHIVED];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::UNASSIGNED => 'Unassigned',
            self::ASSIGNED => 'Assigned',
            self::REPLACED => 'Replaced',
            self::ARCHIVED => 'Archived',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ASSIGNED => 'success',
            self::UNASSIGNED => 'neutral',
            self::REPLACED => 'info',
            self::ARCHIVED => 'offline',
            default => 'neutral',
        };
    }
}
