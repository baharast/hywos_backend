<?php

namespace App\Enums;

class CouplingSource
{
    public const TERMINAL = 'terminal';
    public const GATE = 'gate';
    public const OPERATOR = 'operator';
    public const READER = 'reader';
    public const MANUAL = 'manual';

    public static function all(): array
    {
        return [self::TERMINAL, self::GATE, self::OPERATOR, self::READER, self::MANUAL];
    }
}
