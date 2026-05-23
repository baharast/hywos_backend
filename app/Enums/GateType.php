<?php

namespace App\Enums;

class GateType
{
    public const ENTRY = 'entry';
    public const EXIT = 'exit';
    public const COMBINED = 'combined';
    public const SERVICE = 'service';

    public static function all(): array
    {
        return [self::ENTRY, self::EXIT, self::COMBINED, self::SERVICE];
    }
}
