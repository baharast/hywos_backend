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

    // Customer
    public const CUSTOMER_CREATED = 'customer.created';
    public const CUSTOMER_UPDATED = 'customer.updated';
    public const CUSTOMER_BLOCKED = 'customer.blocked';
    public const CUSTOMER_UNBLOCKED = 'customer.unblocked';
    public const CUSTOMER_ACTIVATED = 'customer.activated';
    public const CUSTOMER_DEACTIVATED = 'customer.deactivated';

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

    // Quality
    public const QUALITY_DECISION_APPROVED = 'quality.decision_approved';
    public const QUALITY_DECISION_REJECTED = 'quality.decision_rejected';

    // Documents
    public const DOCUMENT_REPRINTED = 'document.reprinted';
}
