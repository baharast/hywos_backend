<?php

namespace App\Enums;

class ExportRecordScope
{
    public const ALL_RECORDS = 'all_records';
    public const CREATED_OR_UPDATED_IN_RANGE = 'created_or_updated_in_range';

    public static function all(): array
    {
        return [self::ALL_RECORDS, self::CREATED_OR_UPDATED_IN_RANGE];
    }
}
