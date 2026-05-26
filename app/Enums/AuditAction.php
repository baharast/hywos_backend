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

    // Documents — Operational Documents V1.2 lifecycle. Each maps to a
    // §12 lifecycle status transition. `DOCUMENT_REPRINTED` predates the
    // module and is reused for the reprint action; the rest are new.
    public const DOCUMENT_GENERATED = 'document.generated';
    public const DOCUMENT_QUEUED_FOR_PRINT = 'document.queued_for_print';
    public const DOCUMENT_PRINTED = 'document.printed';
    public const DOCUMENT_PRINT_FAILED = 'document.print_failed';
    public const DOCUMENT_REPRINTED = 'document.reprinted';
    public const DOCUMENT_HANDED_OVER = 'document.handed_over';
    public const DOCUMENT_BLOCKED = 'document.blocked';
    public const DOCUMENT_INVALIDATED = 'document.invalidated';
    public const DOCUMENT_CANCELLED = 'document.cancelled';

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

    // Master Data Export
    public const EXPORT_JOB_CREATED = 'export.job_created';
    public const EXPORT_JOB_DOWNLOADED = 'export.job_downloaded';
    public const EXPORT_JOB_RETRIED = 'export.job_retried';
    public const EXPORT_JOB_FAILED = 'export.job_failed';

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

    // TANs (legacy TAN_CREATED above is the quick-path; these are the dashboard flow)
    public const TAN_GENERATED = 'tan.generated';
    public const TAN_CONSUMED = 'tan.consumed';
    public const TAN_REVOKED = 'tan.revoked';
    public const TAN_EXPIRED = 'tan.expired';
    public const TAN_USAGE_FAILED = 'tan.usage_failed';

    // Loading Orders (reserved block — controller in TSK-004 will emit these)
    public const LOADING_ORDER_CREATED = 'loading_order.created';
    public const LOADING_ORDER_UPDATED = 'loading_order.updated';
    public const LOADING_ORDER_DRIVER_ASSIGNED = 'loading_order.driver_assigned';
    public const LOADING_ORDER_DRIVER_UNASSIGNED = 'loading_order.driver_unassigned';
    public const LOADING_ORDER_TRAILER_ASSIGNED = 'loading_order.trailer_assigned';
    public const LOADING_ORDER_TRAILER_UNASSIGNED = 'loading_order.trailer_unassigned';
    public const LOADING_ORDER_BLOCKED = 'loading_order.blocked';
    public const LOADING_ORDER_UNBLOCKED = 'loading_order.unblocked';
    public const LOADING_ORDER_CANCELLED = 'loading_order.cancelled';
    public const LOADING_ORDER_SAP_FIELD_UPDATE_REJECTED = 'loading_order.sap_field_update_rejected';
    public const LOADING_ORDER_SAP_IMPORTED = 'loading_order.sap_imported';

    // Plant Visits (controller in TSK-005 will emit these)
    public const PLANT_VISIT_CREATED = 'plant_visit.created';
    public const PLANT_VISIT_STEP_ADVANCED = 'plant_visit.step_advanced';
    public const PLANT_VISIT_STATUS_CHANGED = 'plant_visit.status_changed';
    public const PLANT_VISIT_LOCATION_CHANGED = 'plant_visit.location_changed';
    public const PLANT_VISIT_BLOCKED = 'plant_visit.blocked';
    public const PLANT_VISIT_CLARIFICATION_RAISED = 'plant_visit.clarification_raised';
    public const PLANT_VISIT_READY_FOR_EXIT = 'plant_visit.ready_for_exit';
    public const PLANT_VISIT_CLOSED = 'plant_visit.closed';
    public const PLANT_VISIT_FORCE_CLOSED = 'plant_visit.force_closed';

    // Clarification Cases
    // V1.3 has no `cancelled` status — CLARIFICATION_CANCELLED was removed
    // when the enum was reconciled. "Close without resolution" is captured
    // in resolution_note + CLARIFICATION_CLOSED audit, not a separate action.
    public const CLARIFICATION_CREATED = 'clarification.created';
    public const CLARIFICATION_ACKNOWLEDGED = 'clarification.acknowledged';
    public const CLARIFICATION_ASSIGNED = 'clarification.assigned';
    public const CLARIFICATION_RESOLVED = 'clarification.resolved';
    public const CLARIFICATION_CLOSED = 'clarification.closed';

    // Trailer Parking slots (controller in TSK-006 will emit these)
    public const PARKING_SLOT_RESERVED = 'parking_slot.reserved';
    public const PARKING_SLOT_OCCUPIED = 'parking_slot.occupied';
    public const PARKING_SLOT_CLEARED = 'parking_slot.cleared';
    public const PARKING_SLOT_BLOCKED = 'parking_slot.blocked';
    public const PARKING_SLOT_UNBLOCKED = 'parking_slot.unblocked';
    public const PARKING_SLOT_OUT_OF_SERVICE = 'parking_slot.out_of_service';
    public const PARKING_SLOT_RESTORED = 'parking_slot.restored';

    // SAP Sync / Order Import Status (reserved for future write surface;
    // the current MVP controller is read-only per V1.5 §2.2 / §4.2 and
    // never emits these. They are listed here so write endpoints — manual
    // retry, mark-resolved — can land later without churning this enum.)
    public const SAP_SYNC_RECORD_IMPORTED = 'sap_sync.record_imported';
    public const SAP_SYNC_RECORD_EXPORTED = 'sap_sync.record_exported';
    public const SAP_SYNC_RECORD_FAILED = 'sap_sync.record_failed';
    public const SAP_SYNC_RECORD_RESOLVED = 'sap_sync.record_resolved';
    public const SAP_SYNC_RETRY_REQUESTED = 'sap_sync.retry_requested';

    // Gate & Terminal Monitor (reserved; current MVP controller is read-only
    // per V2.3 — a future write controller will emit these for session
    // lifecycle events as drivers move through entry / terminal / exit.)
    public const GATE_TERMINAL_SESSION_STARTED = 'gate_terminal.session_started';
    public const GATE_TERMINAL_SESSION_DENIED = 'gate_terminal.session_denied';
    public const GATE_TERMINAL_NEEDS_OPERATOR_FLAGGED = 'gate_terminal.needs_operator_flagged';
    public const GATE_TERMINAL_RESOLVED = 'gate_terminal.resolved';
    public const GATE_TERMINAL_DEVICE_FAULT = 'gate_terminal.device_fault';

    // Driver Terminal Login (V3) — emitted by TerminalAuthController. Each
    // identification attempt produces one audit row regardless of outcome
    // so failed attempts remain traceable (§9 US-DLOGIN-09 + §17 + §21.1).
    public const TERMINAL_LOGIN_TAN = 'terminal.login_tan';
    public const TERMINAL_LOGIN_CHIP = 'terminal.login_chip';
    public const TERMINAL_LOGIN_FAILED = 'terminal.login_failed';
    public const TERMINAL_TRAINING_REDIRECT = 'terminal.training_redirect';
    public const TERMINAL_LANGUAGE_CHANGED = 'terminal.language_changed';
    public const TERMINAL_SESSION_LOGOUT = 'terminal.session_logout';
    public const TERMINAL_SESSION_TIMEOUT = 'terminal.session_timeout';
    public const TERMINAL_MANAGER_LOGON = 'terminal.manager_logon';
    public const TERMINAL_MANAGER_LOGON_FAILED = 'terminal.manager_logon_failed';

    // Driver Workflow (V6 §8) — emitted by DriverWorkflowController for the
    // trailer/tractor/task/order/bay-line/print/complete steps that follow a
    // successful terminal login. Each writes one row + one event so the
    // kiosk-side narrative is fully reconstructable from the audit log.
    public const TERMINAL_WORKFLOW_TRAILER_SET = 'terminal.workflow.trailer_set';
    public const TERMINAL_WORKFLOW_TRACTOR_SET = 'terminal.workflow.tractor_set';
    public const TERMINAL_WORKFLOW_TASK_SELECTED = 'terminal.workflow.task_selected';
    public const TERMINAL_WORKFLOW_ORDER_CONFIRMED = 'terminal.workflow.order_confirmed';
    public const TERMINAL_WORKFLOW_DELIVERY_NOTE_PRINTED = 'terminal.workflow.delivery_note_printed';
    public const TERMINAL_WORKFLOW_COMPLETED = 'terminal.workflow.completed';

    // Safety Training (V6 §7) — emitted by SafetyTrainingService for the
    // driver-confirmation-per-module flow + the 5-question exam.
    // EXAM_SUBMITTED records the attempt; EXAM_PASSED / EXAM_FAILED captures
    // the outcome so pass-rate analytics don't have to re-read each score.
    public const TERMINAL_TRAINING_MODULE_COMPLETED = 'terminal.training.module_completed';
    public const TERMINAL_TRAINING_EXAM_SUBMITTED   = 'terminal.training.exam_submitted';
    public const TERMINAL_TRAINING_EXAM_PASSED      = 'terminal.training.exam_passed';
    public const TERMINAL_TRAINING_EXAM_FAILED      = 'terminal.training.exam_failed';

    // Products & Quality Specs (V2.1) — emitted by ProductSpecificationService
    // and CalibrationProfileService. Lifecycle constants (created/updated/
    // activated/retired) cover the header; gas-limit and component row
    // mutations are tracked separately so an auditor can see exactly which
    // limit / reference value changed.
    public const PRODUCT_SPEC_CREATED = 'product_spec.created';
    public const PRODUCT_SPEC_UPDATED = 'product_spec.updated';
    public const PRODUCT_SPEC_ACTIVATED = 'product_spec.activated';
    public const PRODUCT_SPEC_RETIRED = 'product_spec.retired';
    public const PRODUCT_SPEC_GAS_LIMIT_ADDED = 'product_spec.gas_limit_added';
    public const PRODUCT_SPEC_GAS_LIMIT_UPDATED = 'product_spec.gas_limit_updated';

    public const CALIBRATION_PROFILE_CREATED = 'calibration_profile.created';
    public const CALIBRATION_PROFILE_UPDATED = 'calibration_profile.updated';
    public const CALIBRATION_PROFILE_ACTIVATED = 'calibration_profile.activated';
    public const CALIBRATION_PROFILE_RETIRED = 'calibration_profile.retired';
    public const CALIBRATION_COMPONENT_ADDED = 'calibration_profile.component_added';
    public const CALIBRATION_COMPONENT_UPDATED = 'calibration_profile.component_updated';

    // Analysis Devices (V1) — reserved for a future write controller
    // (acknowledge / inhibit / mark-maintenance / fault-raised). Read-only
    // in MVP per V1 §4.2; these constants are intentionally never emitted
    // by the current AnalysisDeviceController.
    public const ANALYSIS_DEVICE_INHIBITED = 'analysis_device.inhibited';
    public const ANALYSIS_DEVICE_RESTORED = 'analysis_device.restored';
    public const ANALYSIS_DEVICE_FAULT_RAISED = 'analysis_device.fault_raised';
    public const ANALYSIS_DEVICE_CALIBRATION_DUE = 'analysis_device.calibration_due';
}
