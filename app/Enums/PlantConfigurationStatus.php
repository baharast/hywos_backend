<?php

namespace App\Enums;

class PlantConfigurationStatus
{
    public const NOT_CONFIGURED = 'not_configured';
    public const DRAFT = 'draft';
    public const INCOMPLETE = 'incomplete';
    public const PENDING_CONFIRMATION = 'pending_confirmation';
    public const ACTIVE_LOCKED = 'active_locked';
    public const CHANGE_REQUESTED = 'change_requested';
    public const INACTIVE = 'inactive';

    public static function all(): array
    {
        return [
            self::NOT_CONFIGURED,
            self::DRAFT,
            self::INCOMPLETE,
            self::PENDING_CONFIRMATION,
            self::ACTIVE_LOCKED,
            self::CHANGE_REQUESTED,
            self::INACTIVE,
        ];
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::ACTIVE_LOCKED => 'success',
            self::DRAFT, self::PENDING_CONFIRMATION => 'info',
            self::INCOMPLETE => 'warning',
            self::CHANGE_REQUESTED => 'orange',
            self::INACTIVE, self::NOT_CONFIGURED => 'neutral',
            default => 'neutral',
        };
    }
}
