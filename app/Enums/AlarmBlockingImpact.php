<?php

namespace App\Enums;

/**
 * V1 §6.6 + §9 alarm blocking impact.
 *   Blocks loading, blocks gate entry, blocks exit, blocks documents,
 *   analysis approval blocked, warning only / no direct block.
 */
class AlarmBlockingImpact
{
    public const BLOCKS_LOADING = 'blocks_loading';
    public const BLOCKS_GATE_ENTRY = 'blocks_gate_entry';
    public const BLOCKS_EXIT = 'blocks_exit';
    public const BLOCKS_DOCUMENTS = 'blocks_documents';
    public const ANALYSIS_APPROVAL_BLOCKED = 'analysis_approval_blocked';
    public const WARNING_ONLY = 'warning_only';

    public static function all(): array
    {
        return [
            self::BLOCKS_LOADING, self::BLOCKS_GATE_ENTRY, self::BLOCKS_EXIT,
            self::BLOCKS_DOCUMENTS, self::ANALYSIS_APPROVAL_BLOCKED,
            self::WARNING_ONLY,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('alarms.blocking_impact.' . $v);
        return $translated !== 'alarms.blocking_impact.' . $v
            ? $translated
            : ucwords(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::BLOCKS_LOADING, self::BLOCKS_EXIT,
            self::ANALYSIS_APPROVAL_BLOCKED => 'danger',
            self::BLOCKS_GATE_ENTRY, self::BLOCKS_DOCUMENTS => 'warning',
            self::WARNING_ONLY => 'neutral',
            default => 'neutral',
        };
    }

    public static function isBlocking(string $v): bool
    {
        return $v !== self::WARNING_ONLY;
    }
}
