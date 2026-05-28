<?php

namespace App\Enums;

/**
 * V1.4 §9 — UX grouping for the Interface Health page. Each row's
 * `family` answers "what kind of comm boundary is this?" so the page can
 * group SAP traffic separately from PLC fieldbuses, printer services,
 * cloud sync, etc.
 *
 * Note: Cloud Sync and VPN / Remote Support are *families*, not protocols
 * (V1.4 §11 "interface family validation"). Their protocol values live in
 * `InterfaceProtocol` (HTTPS / VPN).
 */
class InterfaceFamily
{
    public const SAP = 'sap';
    public const PLC_FIELDBUS = 'plc_fieldbus';
    public const ANALYSIS_INTERFACE = 'analysis_interface';
    public const GATE_ETHERNET = 'gate_ethernet';
    public const PRINTER_SERVICE = 'printer_service';
    public const CLOUD_SYNC = 'cloud_sync';
    public const VPN_REMOTE_SUPPORT = 'vpn_remote_support';
    public const MAIL_NOTIFICATION = 'mail_notification';

    public static function all(): array
    {
        return [
            self::SAP,
            self::PLC_FIELDBUS,
            self::ANALYSIS_INTERFACE,
            self::GATE_ETHERNET,
            self::PRINTER_SERVICE,
            self::CLOUD_SYNC,
            self::VPN_REMOTE_SUPPORT,
            self::MAIL_NOTIFICATION,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::SAP => 'SAP',
            self::PLC_FIELDBUS => 'PLC / Fieldbus',
            self::ANALYSIS_INTERFACE => 'Analysis Interface',
            self::GATE_ETHERNET => 'Gate Ethernet',
            self::PRINTER_SERVICE => 'Printer Service',
            self::CLOUD_SYNC => 'Cloud Sync',
            self::VPN_REMOTE_SUPPORT => 'VPN / Remote Support',
            self::MAIL_NOTIFICATION => 'Mail / Notification',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    /**
     * Hint for the FE's group-header tone — families with hard operational
     * impact get a danger-leaning tone so they stand out.
     */
    public static function tone(string $v): string
    {
        return match ($v) {
            self::SAP, self::PLC_FIELDBUS, self::ANALYSIS_INTERFACE,
            self::GATE_ETHERNET, self::PRINTER_SERVICE => 'danger',
            self::CLOUD_SYNC, self::VPN_REMOTE_SUPPORT, self::MAIL_NOTIFICATION => 'neutral',
            default => 'neutral',
        };
    }
}
