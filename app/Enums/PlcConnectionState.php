<?php

namespace App\Enums;

/**
 * DERIVED connection state for the V1.4 §8 PLC / OPC UA Health tab.
 *
 * NOT a stored column — composed at read time from
 * `hardware_devices.health` (+ light heuristics on `last_message` for
 * cert/timeout signals). Spec §8 calls for:
 *   Connected, Degraded, Disconnected, Certificate Error, Timeout,
 *   Unknown.
 *
 * Health maps to connection state as follows:
 *   online                                → connected
 *   warning                               → degraded
 *   alarm / fault                         → degraded (unless message hints cert/timeout)
 *   offline                               → disconnected
 *   service_mode                          → degraded
 *   unknown                               → unknown
 *
 * Spec also wants the FE to distinguish this from operational health
 * (alarms etc.) — connection state is purely the link-layer view.
 */
class PlcConnectionState
{
    public const CONNECTED = 'connected';
    public const DEGRADED = 'degraded';
    public const DISCONNECTED = 'disconnected';
    public const CERTIFICATE_ERROR = 'certificate_error';
    public const TIMEOUT = 'timeout';
    public const UNKNOWN = 'unknown';

    public static function all(): array
    {
        return [
            self::CONNECTED, self::DEGRADED, self::DISCONNECTED,
            self::CERTIFICATE_ERROR, self::TIMEOUT, self::UNKNOWN,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('hardware.plc_connection_state.' . $v);
        return $translated !== 'hardware.plc_connection_state.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::CONNECTED => 'success',
            self::DEGRADED => 'warning',
            self::DISCONNECTED, self::TIMEOUT, self::CERTIFICATE_ERROR => 'danger',
            self::UNKNOWN => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * Compose a connection state from a hardware_devices row's health
     * and (optionally) its last_message text.
     */
    public static function derive(?string $health, ?string $lastMessage = null): string
    {
        // Light heuristics on the message — only fire on explicit hints
        // so we don't misclassify generic faults.
        if ($lastMessage !== null) {
            $lower = strtolower($lastMessage);
            if (str_contains($lower, 'certificate') || str_contains($lower, 'cert ')) {
                return self::CERTIFICATE_ERROR;
            }
            if (str_contains($lower, 'timeout') || str_contains($lower, 'timed out')) {
                return self::TIMEOUT;
            }
        }

        return match ($health) {
            HardwareDeviceHealth::ONLINE => self::CONNECTED,
            HardwareDeviceHealth::WARNING => self::DEGRADED,
            HardwareDeviceHealth::ALARM, HardwareDeviceHealth::FAULT => self::DEGRADED,
            HardwareDeviceHealth::OFFLINE => self::DISCONNECTED,
            HardwareDeviceHealth::SERVICE_MODE => self::DEGRADED,
            HardwareDeviceHealth::UNKNOWN, null, '' => self::UNKNOWN,
            default => self::UNKNOWN,
        };
    }
}
