<?php

namespace App\Enums;

class ExportFormat
{
    public const CSV = 'csv';
    public const XLSX = 'xlsx';

    public static function all(): array
    {
        return [self::CSV, self::XLSX];
    }

    /**
     * Formats that are fully implemented in MVP. XLSX is accepted in the API
     * but currently falls back to CSV with a warning until the spreadsheet
     * package is added.
     */
    public static function implemented(): array
    {
        return [self::CSV];
    }
}
