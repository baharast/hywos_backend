<?php

namespace App\Enums;

/**
 * V1.4 §9 — Protocol / Technology values for Interface Health.
 *
 * Note: `SAP_RFC_IDOC` is the canonical name for "SAP NCo / IDoc / OData
 * to be clarified" — V1.4 explicitly marks this as TBC at the engineering
 * level; we keep one stable enum value and let the row's `data_status` or
 * `source_basis` columns carry the "to be clarified" caveat.
 *
 * `vpn` and `https` appear here as protocols even though Cloud Sync / VPN
 * are also FAMILIES — that's expected; family answers "what kind of
 * interface", protocol answers "over which wire".
 */
class InterfaceProtocol
{
    public const SAP_RFC_IDOC = 'sap_rfc_idoc';
    public const OPCUA = 'opcua';
    public const MODBUS_TCP = 'modbus_tcp';
    public const PROFINET = 'profinet';
    public const PROFIBUS = 'profibus';
    public const ETHERNET = 'ethernet';
    public const HTTPS = 'https';
    public const PRINTER_LOCAL = 'printer_local';
    public const PRINTER_NETWORK = 'printer_network';
    public const VPN = 'vpn';

    public static function all(): array
    {
        return [
            self::SAP_RFC_IDOC,
            self::OPCUA,
            self::MODBUS_TCP,
            self::PROFINET,
            self::PROFIBUS,
            self::ETHERNET,
            self::HTTPS,
            self::PRINTER_LOCAL,
            self::PRINTER_NETWORK,
            self::VPN,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('hardware.interface_protocol.' . $v);
        return $translated !== 'hardware.interface_protocol.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }
}
