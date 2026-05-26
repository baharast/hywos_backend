<?php

namespace App\Enums;

/**
 * Method used by the driver to identify at the terminal login page.
 *
 * Per FillTrack Driver Login Page UX Frontend Spec-EN-V3 §21.2 (suggested
 * frontend data shape — `LoginMethod`). Chip card is the primary method
 * where the reader is available; TAN is the controlled fallback.
 */
class LoginMethod
{
    public const TAN = 'tan';
    public const CHIP_CARD = 'chip_card';

    public static function all(): array
    {
        return [self::TAN, self::CHIP_CARD];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::TAN => 'TAN Number',
            self::CHIP_CARD => 'Driver Chip Card',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }
}
