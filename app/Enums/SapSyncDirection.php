<?php

namespace App\Enums;

/**
 * Direction of a SAP <-> FillTrack order communication record (V1.5 §9.1).
 *
 * Separate from result/handling status: direction is *which way* the message
 * flowed, not whether it succeeded.
 */
class SapSyncDirection
{
    public const IMPORT = 'import';
    public const EXPORT = 'export';

    public static function all(): array
    {
        return [
            self::IMPORT,
            self::EXPORT,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('masterdata.sap_sync_direction.' . $v);
        return $translated !== 'masterdata.sap_sync_direction.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }
}
