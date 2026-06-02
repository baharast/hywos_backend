<?php

namespace App\Enums;

/**
 * Soft-FK entity types a clarification case may be bound to.
 * `entity_id` is never enforced via a DB foreign key — some target tables
 * (sap_sync_records, plant_visits) may not exist yet.
 */
class ClarificationEntityType
{
    public const PLANT_VISIT = 'plant_visit';
    public const LOADING_ORDER = 'loading_order';
    public const PARKING_SLOT = 'parking_slot';
    public const BAY_LINE = 'bay_line';
    public const DRIVER = 'driver';
    public const TRAILER = 'trailer';
    public const TRACTOR_VEHICLE = 'tractor_vehicle';
    public const CHIP_CARD = 'chip_card';
    public const TAN = 'tan';
    public const SAP_SYNC_RECORD = 'sap_sync_record';

    public static function all(): array
    {
        return [
            self::PLANT_VISIT,
            self::LOADING_ORDER,
            self::PARKING_SLOT,
            self::BAY_LINE,
            self::DRIVER,
            self::TRAILER,
            self::TRACTOR_VEHICLE,
            self::CHIP_CARD,
            self::TAN,
            self::SAP_SYNC_RECORD,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('clarification.entity_type.' . $v);
        return $translated !== 'clarification.entity_type.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    /**
     * Resolve the FE deeplink for a (type, id) pair. Returns null when the
     * entity type has no canonical detail screen yet.
     */
    public static function routePathFor(?string $entityType, ?string $entityId): ?string
    {
        if (! $entityType || ! $entityId) {
            return null;
        }

        return match ($entityType) {
            self::PLANT_VISIT => "/operations/active-plant-visits/{$entityId}",
            self::LOADING_ORDER => "/operations/loading-orders/{$entityId}",
            self::PARKING_SLOT => "/operations/trailer-parking/{$entityId}",
            self::BAY_LINE => "/operations/loading-control/stations/{$entityId}",
            self::DRIVER => "/master-data/drivers/{$entityId}",
            self::TRAILER => "/master-data/trailers/{$entityId}",
            self::TRACTOR_VEHICLE => "/master-data/tractor-vehicles/{$entityId}",
            self::CHIP_CARD => "/master-data/chip-cards/{$entityId}",
            self::TAN => "/master-data/tans/{$entityId}",
            self::SAP_SYNC_RECORD => "/integrations/sap-sync/{$entityId}",
            default => null,
        };
    }
}
