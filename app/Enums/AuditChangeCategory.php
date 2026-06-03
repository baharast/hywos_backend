<?php

namespace App\Enums;

/**
 * DERIVED change category for V1 §8 Change Log / Audit Trail.
 *
 * NOT a stored column — composed at read time from the action prefix on
 * the audit_logs row. Per spec §8.3 filter dropdown:
 *   Master data, process status, manual override, quality decision,
 *   document, security/role, configuration, device/service mode.
 *
 * Action format on audit_logs is "entity.action" (e.g. `driver.blocked`,
 * `plant_config.activated`, `hardware_device.service_mode_set`). The
 * deriveFrom() helper maps the entity prefix to a change category.
 */
class AuditChangeCategory
{
    public const MASTER_DATA = 'master_data';
    public const PROCESS_STATUS = 'process_status';
    public const MANUAL_OVERRIDE = 'manual_override';
    public const QUALITY_DECISION = 'quality_decision';
    public const DOCUMENT = 'document';
    public const SECURITY_ROLE = 'security_role';
    public const CONFIGURATION = 'configuration';
    public const DEVICE_SERVICE_MODE = 'device_service_mode';

    public static function all(): array
    {
        return [
            self::MASTER_DATA, self::PROCESS_STATUS, self::MANUAL_OVERRIDE,
            self::QUALITY_DECISION, self::DOCUMENT, self::SECURITY_ROLE,
            self::CONFIGURATION, self::DEVICE_SERVICE_MODE,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('logbook.audit_change_category.' . $v);
        return $translated !== 'logbook.audit_change_category.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::MANUAL_OVERRIDE, self::SECURITY_ROLE => 'warning',
            self::QUALITY_DECISION => 'info',
            self::CONFIGURATION => 'info',
            default => 'neutral',
        };
    }

    /**
     * Map an action string to a change category.
     *
     * Examples:
     *   driver.blocked              → master_data
     *   plant_config.activated      → configuration
     *   hardware_device.service_mode_set → device_service_mode
     *   document.reprinted          → document
     *   user.roles_updated          → security_role
     *   active_analysis.manual_approval → quality_decision
     *   loading_order.assigned_driver → process_status
     */
    public static function deriveFrom(string $action): string
    {
        $prefix = explode('.', $action, 2)[0] ?? '';
        $tail = explode('.', $action, 2)[1] ?? '';

        // Security / role first — tightest match on the prefix.
        if (in_array($prefix, ['user', 'role', 'auth_medium', 'tan', 'session', 'permission'], true)) {
            return self::SECURITY_ROLE;
        }

        // Manual override / quality decisions — tail contains explicit signal.
        if (str_contains($tail, 'manual_approval')
            || str_contains($tail, 'force_match')
            || str_contains($tail, 'force_success')
            || str_contains($tail, 'override')
            || str_contains($tail, 'rejected')) {
            // Quality-decisions narrow further
            if (in_array($prefix, ['active_analysis', 'analysis', 'analysis_attempt'], true)) {
                return self::QUALITY_DECISION;
            }
            return self::MANUAL_OVERRIDE;
        }

        if (in_array($prefix, ['active_analysis', 'analysis', 'analysis_attempt', 'analysis_device', 'product_spec', 'calibration_profile'], true)) {
            return self::QUALITY_DECISION;
        }

        if (in_array($prefix, ['document', 'operational_document', 'report', 'document_print_attempt', 'printer_job'], true)) {
            return self::DOCUMENT;
        }

        if (in_array($prefix, ['hardware_device', 'interface', 'plc', 'opc_ua'], true)) {
            return self::DEVICE_SERVICE_MODE;
        }

        if (in_array($prefix, ['plant_config', 'plant_configuration'], true)) {
            return self::CONFIGURATION;
        }

        if (in_array($prefix, ['driver', 'trailer', 'tractor', 'tractor_vehicle', 'customer', 'carrier', 'company', 'chip_card'], true)) {
            return self::MASTER_DATA;
        }

        if (in_array($prefix, [
            'loading_order', 'plant_visit', 'loading', 'clarification_case',
            'bay_line', 'parking_slot', 'gate_terminal', 'terminal_workflow',
            'terminal_training', 'sap_sync',
        ], true)) {
            return self::PROCESS_STATUS;
        }

        return self::PROCESS_STATUS;
    }
}
