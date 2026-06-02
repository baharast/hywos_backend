<?php

namespace App\Enums;

/**
 * DERIVED V1 §7.4 Event Journal category — the 11-value FE-facing
 * vocabulary on top of the stored `event_logs.event_category` column.
 *
 * NOT a stored column. Stored values in event_category use the higher-
 * level App\Enums\EventCategory taxonomy (operations / integration /
 * security / system / quality / document / device). This enum
 * decomposes those into the 11 finer buckets §7.4 calls for:
 *   Gate/Access, Driver Terminal, Filling Station, Loading, Analysis,
 *   Document/Print, Device/Communication, Interface/SAP, Security,
 *   System, Audit Reference.
 *
 * Mapping uses BOTH event_category and event_type prefix so different
 * "operations" sub-domains (gate vs. loading vs. driver_terminal) land
 * in distinct FE buckets.
 */
class EventJournalCategory
{
    public const GATE_ACCESS = 'gate_access';
    public const DRIVER_TERMINAL = 'driver_terminal';
    public const FILLING_STATION = 'filling_station';
    public const LOADING = 'loading';
    public const ANALYSIS = 'analysis';
    public const DOCUMENT_PRINT = 'document_print';
    public const DEVICE_COMMUNICATION = 'device_communication';
    public const INTERFACE_SAP = 'interface_sap';
    public const SECURITY = 'security';
    public const SYSTEM = 'system';
    public const AUDIT_REFERENCE = 'audit_reference';

    public static function all(): array
    {
        return [
            self::GATE_ACCESS, self::DRIVER_TERMINAL, self::FILLING_STATION,
            self::LOADING, self::ANALYSIS, self::DOCUMENT_PRINT,
            self::DEVICE_COMMUNICATION, self::INTERFACE_SAP,
            self::SECURITY, self::SYSTEM, self::AUDIT_REFERENCE,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('alarms.journal_category.' . $v);
        return $translated !== 'alarms.journal_category.' . $v
            ? $translated
            : ucwords(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::SECURITY => 'warning',
            self::AUDIT_REFERENCE => 'info',
            default => 'neutral',
        };
    }

    /**
     * Derive a FE category from the stored event_category + event_type.
     *
     * event_type is dotted: e.g. `gate.access_granted`,
     * `driver_terminal.session_started`, `loading.released`,
     * `document.printed`, `device.heartbeat_missed`, `sap.import_failed`.
     */
    public static function deriveFrom(?string $eventCategory, ?string $eventType): string
    {
        $prefix = $eventType === null ? '' : explode('.', $eventType, 2)[0];

        // event_type prefix is the strongest signal.
        $byPrefix = match ($prefix) {
            'gate', 'gate_terminal', 'access', 'identification' => self::GATE_ACCESS,
            'driver_terminal', 'terminal', 'terminal_workflow', 'terminal_training' => self::DRIVER_TERMINAL,
            'filling', 'filling_station', 'bay_line' => self::FILLING_STATION,
            'loading', 'loading_order', 'loading_control', 'plant_visit', 'clarification_case' => self::LOADING,
            'analysis', 'active_analysis', 'analysis_device', 'product_spec', 'calibration_profile' => self::ANALYSIS,
            'document', 'operational_document', 'document_print_attempt', 'report', 'printer_job' => self::DOCUMENT_PRINT,
            'hardware_device', 'interface_health', 'plc', 'opc_ua' => self::DEVICE_COMMUNICATION,
            'sap', 'sap_sync', 'interface' => self::INTERFACE_SAP,
            'auth', 'session', 'user', 'role', 'permission', 'auth_medium', 'tan' => self::SECURITY,
            'audit', 'audit_log' => self::AUDIT_REFERENCE,
            'system', 'scheduled_job', 'health' => self::SYSTEM,
            default => null,
        };
        if ($byPrefix !== null) return $byPrefix;

        // Fallback to the broader stored event_category.
        return match ($eventCategory) {
            EventCategory::OPERATIONS => self::LOADING,
            EventCategory::INTEGRATION => self::INTERFACE_SAP,
            EventCategory::SECURITY => self::SECURITY,
            EventCategory::QUALITY => self::ANALYSIS,
            EventCategory::DOCUMENT => self::DOCUMENT_PRINT,
            EventCategory::DEVICE => self::DEVICE_COMMUNICATION,
            EventCategory::SYSTEM => self::SYSTEM,
            default => self::SYSTEM,
        };
    }
}
