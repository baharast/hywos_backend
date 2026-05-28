<?php

namespace App\Enums;

/**
 * V1.1 §11.6 — downstream impact on the H2 certificate of quality.
 *
 *   allowed       — quality decision permits certificate generation
 *   blocked       — certificate must NOT be generated until the issue is resolved
 *   generated     — certificate has already been generated for this result
 *   not_required  — certificate not in scope for this analysis (e.g. retest only)
 */
class CertificateImpact
{
    public const ALLOWED = 'allowed';
    public const BLOCKED = 'blocked';
    public const GENERATED = 'generated';
    public const NOT_REQUIRED = 'not_required';

    public static function all(): array
    {
        return [self::ALLOWED, self::BLOCKED, self::GENERATED, self::NOT_REQUIRED];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::ALLOWED => 'Allowed',
            self::BLOCKED => 'Blocked',
            self::GENERATED => 'Generated',
            self::NOT_REQUIRED => 'Not required',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ALLOWED => 'success',
            self::GENERATED => 'success',
            self::BLOCKED => 'danger',
            self::NOT_REQUIRED => 'neutral',
            default => 'neutral',
        };
    }
}
