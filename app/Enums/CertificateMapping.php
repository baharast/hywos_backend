<?php

namespace App\Enums;

/**
 * Where a Product Gas Limit row's measured result should appear on the
 * generated documents (V2.1 §4.2).
 *
 *   not_printed       — measured but not shown on any document
 *   certificate_row   — printed on the H2 certificate of quality
 *   delivery_note     — printed on the delivery note
 *   qm_document       — printed on the QM (quality management) document
 */
class CertificateMapping
{
    public const NOT_PRINTED = 'not_printed';
    public const CERTIFICATE_ROW = 'certificate_row';
    public const DELIVERY_NOTE = 'delivery_note';
    public const QM_DOCUMENT = 'qm_document';

    public static function all(): array
    {
        return [
            self::NOT_PRINTED,
            self::CERTIFICATE_ROW,
            self::DELIVERY_NOTE,
            self::QM_DOCUMENT,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('analysis.certificate_mapping.' . $v);
        return $translated !== 'analysis.certificate_mapping.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }
}
