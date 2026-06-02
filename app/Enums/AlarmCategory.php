<?php

namespace App\Enums;

/**
 * V1 §6.6 + §9 alarm category.
 *   Safety, Loading/Process, Quality/Analysis, Gate/Access,
 *   Device/Communication, Document/Printer, Interface/SAP.
 */
class AlarmCategory
{
    public const SAFETY = 'safety';
    public const LOADING_PROCESS = 'loading_process';
    public const QUALITY_ANALYSIS = 'quality_analysis';
    public const GATE_ACCESS = 'gate_access';
    public const DEVICE_COMMUNICATION = 'device_communication';
    public const DOCUMENT_PRINTER = 'document_printer';
    public const INTERFACE_SAP = 'interface_sap';

    public static function all(): array
    {
        return [
            self::SAFETY, self::LOADING_PROCESS, self::QUALITY_ANALYSIS,
            self::GATE_ACCESS, self::DEVICE_COMMUNICATION,
            self::DOCUMENT_PRINTER, self::INTERFACE_SAP,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('alarms.category.' . $v);
        return $translated !== 'alarms.category.' . $v
            ? $translated
            : ucwords(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::SAFETY => 'danger',
            self::QUALITY_ANALYSIS, self::DEVICE_COMMUNICATION => 'warning',
            default => 'neutral',
        };
    }
}
