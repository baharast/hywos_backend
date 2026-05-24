<?php

namespace App\Enums;

class AuditAction
{
    // User account
    public const USER_CREATED = 'user.created';
    public const USER_UPDATED = 'user.updated';
    public const USER_DISABLED = 'user.disabled';
    public const USER_ENABLED = 'user.enabled';
    public const USER_LOCKED = 'user.locked';
    public const USER_UNLOCKED = 'user.unlocked';
    public const USER_ACCESS_RESET = 'user.access_reset';
    public const USER_ROLES_UPDATED = 'user.roles_updated';

    // Role / permissions
    public const ROLE_CREATED = 'role.created';
    public const ROLE_UPDATED = 'role.updated';
    public const ROLE_DEACTIVATED = 'role.deactivated';
    public const ROLE_PERMISSIONS_UPDATED = 'role.permissions_updated';

    // Driver
    public const DRIVER_CREATED = 'driver.created';
    public const DRIVER_UPDATED = 'driver.updated';
    public const DRIVER_BLOCKED = 'driver.blocked';
    public const DRIVER_UNBLOCKED = 'driver.unblocked';

    // Auth media (chip cards, TANs)
    public const AUTH_MEDIUM_CREATED = 'auth_medium.created';
    public const AUTH_MEDIUM_BLOCKED = 'auth_medium.blocked';
    public const AUTH_MEDIUM_REVOKED = 'auth_medium.revoked';
    public const TAN_CREATED = 'tan.created';

    // Customer
    public const CUSTOMER_CREATED = 'customer.created';
    public const CUSTOMER_UPDATED = 'customer.updated';
    public const CUSTOMER_BLOCKED = 'customer.blocked';
    public const CUSTOMER_UNBLOCKED = 'customer.unblocked';
    public const CUSTOMER_ACTIVATED = 'customer.activated';
    public const CUSTOMER_DEACTIVATED = 'customer.deactivated';
    public const CUSTOMER_SAP_FIELD_UPDATE_REJECTED = 'customer.sap_field_update_rejected';

    // Company
    public const COMPANY_CREATED = 'company.created';
    public const COMPANY_UPDATED = 'company.updated';
    public const COMPANY_ACTIVATED = 'company.activated';
    public const COMPANY_DEACTIVATED = 'company.deactivated';

    // Plant configuration
    public const PLANT_CONFIG_DRAFT_STARTED = 'plant_config.draft_started';
    public const PLANT_CONFIG_DRAFT_SAVED = 'plant_config.draft_saved';
    public const PLANT_CONFIG_VALIDATED = 'plant_config.validated';
    public const PLANT_CONFIG_ACTIVATED = 'plant_config.activated';
    public const PLANT_CONFIG_OBJECT_CREATED = 'plant_config.object_created';
    public const PLANT_CONFIG_OBJECT_UPDATED = 'plant_config.object_updated';
    public const PLANT_CONFIG_CHANGE_REQUESTED = 'plant_config.change_requested';
    public const PLANT_CONFIG_CHANGE_APPROVED = 'plant_config.change_approved';
    public const PLANT_CONFIG_CHANGE_REJECTED = 'plant_config.change_rejected';
    public const PLANT_CONFIG_CHANGE_APPLIED = 'plant_config.change_applied';

    // Loading operations
    public const LOADING_RELEASED = 'loading.released';
    public const LOADING_PAUSED = 'loading.paused';
    public const LOADING_COMPLETED = 'loading.completed';
    public const LOADING_FAILED = 'loading.failed';
    public const LOADING_NOTE_ADDED = 'loading.note_added';

    // Quality
    public const QUALITY_DECISION_APPROVED = 'quality.decision_approved';
    public const QUALITY_DECISION_REJECTED = 'quality.decision_rejected';

    // Documents
    public const DOCUMENT_REPRINTED = 'document.reprinted';

    // Trailer
    public const TRAILER_CREATED = 'trailer.created';
    public const TRAILER_UPDATED = 'trailer.updated';
    public const TRAILER_BLOCKED = 'trailer.blocked';
    public const TRAILER_UNBLOCKED = 'trailer.unblocked';

    // Tractor / Vehicle
    public const VEHICLE_CREATED = 'vehicle.created';
    public const VEHICLE_UPDATED = 'vehicle.updated';
    public const VEHICLE_BLOCKED = 'vehicle.blocked';
    public const VEHICLE_UNBLOCKED = 'vehicle.unblocked';
    public const VEHICLE_PLATE_CHANGED = 'vehicle.plate_changed';

    // Tractor-Trailer coupling lifecycle
    public const COUPLING_CREATED = 'coupling.created';
    public const COUPLING_UNCOUPLED = 'coupling.uncoupled';

    // Carrier / Freight Forwarder
    public const CARRIER_CREATED = 'carrier.created';
    public const CARRIER_UPDATED = 'carrier.updated';
    public const CARRIER_BLOCKED = 'carrier.blocked';
    public const CARRIER_UNBLOCKED = 'carrier.unblocked';
    public const CARRIER_APPROVAL_CHANGED = 'carrier.approval_changed';
    public const CARRIER_SAP_FIELD_UPDATE_REJECTED = 'carrier.sap_field_update_rejected';

    // Chip cards
    public const CHIP_CARD_REGISTERED = 'chip_card.registered';
    public const CHIP_CARD_ASSIGNED = 'chip_card.assigned';
    public const CHIP_CARD_REASSIGNED = 'chip_card.reassigned';
    public const CHIP_CARD_UNASSIGNED = 'chip_card.unassigned';
    public const CHIP_CARD_BLOCKED = 'chip_card.blocked';
    public const CHIP_CARD_UNBLOCKED = 'chip_card.unblocked';
    public const CHIP_CARD_MARKED_LOST = 'chip_card.marked_lost';
    public const CHIP_CARD_MARKED_DEFECTIVE = 'chip_card.marked_defective';
    public const CHIP_CARD_REPLACED = 'chip_card.replaced';
    public const CHIP_CARD_ARCHIVED = 'chip_card.archived';
}
