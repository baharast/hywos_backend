<?php

namespace App\Enums;

/**
 * Print status for an operational document and for each print attempt.
 *
 * Per FillTrack Operational Documents UX Frontend Spec-EN-V1.2 §16
 * (TypeScript shapes — DocumentPrintStatus). The same enum drives the
 * document-level print badge (latest attempt) and the per-attempt
 * `status` in the Print History tab (§11.3).
 */
class DocumentPrintStatus
{
    public const NOT_REQUESTED = 'not_requested';
    public const QUEUED = 'queued';
    public const PRINTING = 'printing';
    public const PRINTED = 'printed';
    public const FAILED = 'failed';
    public const REPRINTED = 'reprinted';

    public static function all(): array
    {
        return [
            self::NOT_REQUESTED,
            self::QUEUED,
            self::PRINTING,
            self::PRINTED,
            self::FAILED,
            self::REPRINTED,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('masterdata.document_print_status.' . $v);
        return $translated !== 'masterdata.document_print_status.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::NOT_REQUESTED => 'neutral',
            self::QUEUED => 'info',
            self::PRINTING => 'info',
            self::PRINTED => 'success',
            self::FAILED => 'danger',
            self::REPRINTED => 'info',
            default => 'neutral',
        };
    }
}
