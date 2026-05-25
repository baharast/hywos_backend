<?php

namespace App\Enums;

/**
 * Source pages / workflows that may emit a clarification case.
 *
 * Per FillTrack Clarification Cases UX Frontend Spec-EN-V1.3 §5 (Case Sources)
 * and §14 (TypeScript shapes).
 *
 * Each source maps to a typical owner role and typical primary action in §5;
 * those derivations are owned by the lifecycle controller (TSK-012), not by
 * this enum. The enum only carries the value list and human label.
 */
class ClarificationSource
{
    public const ORDER_MATCHING = 'order_matching';
    public const GATE_TERMINAL = 'gate_terminal';
    public const PARKING = 'parking';
    public const LOADING_CONTROL = 'loading_control';
    public const ANALYSIS_QUALITY = 'analysis_quality';
    public const DOCUMENTS = 'documents';
    public const DEVICE_INTERFACE = 'device_interface';
    public const EXIT = 'exit';

    public static function all(): array
    {
        return [
            self::ORDER_MATCHING,
            self::GATE_TERMINAL,
            self::PARKING,
            self::LOADING_CONTROL,
            self::ANALYSIS_QUALITY,
            self::DOCUMENTS,
            self::DEVICE_INTERFACE,
            self::EXIT,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::ORDER_MATCHING => 'Order Matching',
            self::GATE_TERMINAL => 'Gate / Terminal',
            self::PARKING => 'Parking',
            self::LOADING_CONTROL => 'Loading Control',
            self::ANALYSIS_QUALITY => 'Analysis / Quality',
            self::DOCUMENTS => 'Documents / Print',
            self::DEVICE_INTERFACE => 'Device / Interface',
            self::EXIT => 'Exit',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }
}
