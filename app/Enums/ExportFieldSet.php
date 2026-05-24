<?php

namespace App\Enums;

class ExportFieldSet
{
    public const DEFAULT_FIELDS = 'default_fields';
    public const ALL_EXPORTABLE_FIELDS = 'all_exportable_fields';

    public static function all(): array
    {
        return [self::DEFAULT_FIELDS, self::ALL_EXPORTABLE_FIELDS];
    }
}
