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
        $translated = __('vehicles.chip_assignment_state.' . $v);
        return $translated !== 'vehicles.chip_assignment_state.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
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
