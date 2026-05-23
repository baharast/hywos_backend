<?php

namespace App\Enums;

class ChangeRequestType
{
    public const METADATA = 'metadata';
    public const STRUCTURAL = 'structural';
    public const DEVICE_LINK = 'device_link';
    public const DEACTIVATE = 'deactivate';
    public const REACTIVATE = 'reactivate';

    public static function all(): array
    {
        return [
            self::METADATA,
            self::STRUCTURAL,
            self::DEVICE_LINK,
            self::DEACTIVATE,
            self::REACTIVATE,
        ];
    }
}
