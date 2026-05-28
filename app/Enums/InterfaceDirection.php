<?php

namespace App\Enums;

/**
 * V1.4 §9 — direction of data flow at the FillTrack boundary. `inbound`
 * = "they push to us", `outbound` = "we push to them", `bidirectional`
 * = both (typical for PLC fieldbuses).
 */
class InterfaceDirection
{
    public const INBOUND = 'inbound';
    public const OUTBOUND = 'outbound';
    public const BIDIRECTIONAL = 'bidirectional';

    public static function all(): array
    {
        return [self::INBOUND, self::OUTBOUND, self::BIDIRECTIONAL];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::INBOUND => 'Inbound',
            self::OUTBOUND => 'Outbound',
            self::BIDIRECTIONAL => 'Bidirectional',
            default => ucfirst($v),
        };
    }
}
