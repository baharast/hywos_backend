<?php

namespace App\Enums;

/**
 * Loading Order lifecycle status as exposed to the dashboard.
 *
 * IMPORTANT: per Loading Orders V2.2 §4.4, this value is DERIVED from order
 * data + driver/trailer assignment + downstream operational state. It is not
 * a freely-settable column. Persist only what cannot be derived (e.g.
 * BLOCKED + CANCELLED which require a reason and have audit semantics);
 * everything else flows through LoadingOrderReadinessService.
 */
class LoadingOrderStatus
{
    public const DRAFT = 'draft';
    public const NEEDS_ASSIGNMENT = 'needs_assignment';
    public const READY = 'ready';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';
    public const BLOCKED = 'blocked';
    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::DRAFT,
            self::NEEDS_ASSIGNMENT,
            self::READY,
            self::IN_PROGRESS,
            self::COMPLETED,
            self::BLOCKED,
            self::CANCELLED,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('loading.order_status.' . $v);
        return $translated !== 'loading.order_status.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::DRAFT => 'neutral',
            self::NEEDS_ASSIGNMENT => 'warning',
            self::READY => 'success',
            self::IN_PROGRESS => 'info',
            self::COMPLETED => 'success',
            self::BLOCKED => 'danger',
            self::CANCELLED => 'offline',
            default => 'neutral',
        };
    }
}
