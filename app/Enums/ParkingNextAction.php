<?php

namespace App\Enums;

/**
 * Operational next-action hint surfaced on a parking slot card (V2.1 §16.1).
 *
 * This is an instruction for the operator, not a status. It does not carry a
 * tone — the UI renders it as a plain action chip / button label.
 */
class ParkingNextAction
{
    public const NONE = 'none';
    public const WAIT_FOR_ARRIVAL = 'wait_for_arrival';
    public const WAIT_FOR_PICKUP = 'wait_for_pickup';
    public const OPEN_VISIT = 'open_visit';
    public const OPEN_ORDER = 'open_order';
    public const OPEN_DOCUMENTS = 'open_documents';
    public const OPEN_CLARIFICATION = 'open_clarification';

    public static function all(): array
    {
        return [
            self::NONE,
            self::WAIT_FOR_ARRIVAL,
            self::WAIT_FOR_PICKUP,
            self::OPEN_VISIT,
            self::OPEN_ORDER,
            self::OPEN_DOCUMENTS,
            self::OPEN_CLARIFICATION,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('plant_visits.parking_next_action.' . $v);
        return $translated !== 'plant_visits.parking_next_action.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }
}
