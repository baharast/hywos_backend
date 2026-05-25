<?php

namespace App\Enums;

/**
 * Driver task / driver flow — the WHY of a visit.
 *
 * Per Active Plant Visits V1.6 §7 (the more granular of the two spec
 * versions), this is the canonical set. Loading Orders V2.2 uses a slightly
 * different label set ("take_full_trailer_and_leave", "go_to_parking",
 * "print_and_exit") — those map into the values below:
 *
 *   loading-order label              => canonical value
 *   ─────────────────────────────────────────────────────────────
 *   trailer_filling                  => TRAILER_FILLING
 *   take_full_trailer_and_leave      => PICKUP_LOADED_TRAILER
 *   go_to_parking                    => PARK_TRAILER
 *   print_and_exit                   => DOCUMENTS_EXIT
 *   exit                             => EXIT_ONLY
 *   (no direct mapping)              => PICKUP_EMPTY_TRAILER_THEN_LOAD
 */
class DriverTask
{
    public const TRAILER_FILLING = 'trailer_filling';
    public const PARK_TRAILER = 'park_trailer';
    public const PICKUP_LOADED_TRAILER = 'pickup_loaded_trailer';
    public const PICKUP_EMPTY_TRAILER_THEN_LOAD = 'pickup_empty_trailer_then_load';
    public const DOCUMENTS_EXIT = 'documents_exit';
    public const EXIT_ONLY = 'exit_only';

    public static function all(): array
    {
        return [
            self::TRAILER_FILLING,
            self::PARK_TRAILER,
            self::PICKUP_LOADED_TRAILER,
            self::PICKUP_EMPTY_TRAILER_THEN_LOAD,
            self::DOCUMENTS_EXIT,
            self::EXIT_ONLY,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::TRAILER_FILLING => 'Trailer Filling',
            self::PARK_TRAILER => 'Park Trailer',
            self::PICKUP_LOADED_TRAILER => 'Pick Up Loaded Trailer',
            self::PICKUP_EMPTY_TRAILER_THEN_LOAD => 'Pick Up Empty Trailer Then Load',
            self::DOCUMENTS_EXIT => 'Documents & Exit',
            self::EXIT_ONLY => 'Exit Only',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    /**
     * Does this task require a trailer to be assigned on the loading order
     * before the visit reaches the operational step that uses it?
     *
     * Used by LoadingOrderReadinessService when deriving NEEDS_ASSIGNMENT.
     */
    public static function requiresTrailer(string $v): bool
    {
        return match ($v) {
            self::TRAILER_FILLING, self::PARK_TRAILER => true,
            self::PICKUP_LOADED_TRAILER, self::PICKUP_EMPTY_TRAILER_THEN_LOAD => false,
            self::DOCUMENTS_EXIT, self::EXIT_ONLY => false,
            default => true,
        };
    }

    /**
     * Does this task require a driver to be assigned on the loading order?
     *
     * MVP rule per Loading Orders V2.2: driver may be empty at create time
     * but must be assigned before the order is READY. Only EXIT_ONLY
     * relaxes this (admin/operator-driven close-out).
     */
    public static function requiresDriver(string $v): bool
    {
        return $v !== self::EXIT_ONLY;
    }
}
