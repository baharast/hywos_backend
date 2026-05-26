<?php

namespace App\Enums;

/**
 * Operational document type.
 *
 * Per FillTrack Operational Documents UX Frontend Spec-EN-V1.2 §16
 * (TypeScript shapes — DocumentType).
 */
class DocumentType
{
    public const CERTIFICATE = 'certificate';
    public const DELIVERY_NOTE = 'delivery_note';
    public const QM_DOCUMENT = 'qm_document';

    public static function all(): array
    {
        return [
            self::CERTIFICATE,
            self::DELIVERY_NOTE,
            self::QM_DOCUMENT,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::CERTIFICATE => 'Certificate',
            self::DELIVERY_NOTE => 'Delivery Note',
            self::QM_DOCUMENT => 'QM Document',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }
}
