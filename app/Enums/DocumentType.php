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
        $translated = __('masterdata.document_type.' . $v);
        return $translated !== 'masterdata.document_type.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }
}
