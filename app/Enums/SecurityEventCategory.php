<?php

namespace App\Enums;

/**
 * V1 §7.6 Security category. Spec §7.6:
 *   Authentication, authorization, account lifecycle, role/permission,
 *   chip/TAN access, session, API/integration.
 *
 * DERIVED at read time from event_type prefix; not a stored column.
 */
class SecurityEventCategory
{
    public const AUTHENTICATION = 'authentication';
    public const AUTHORIZATION = 'authorization';
    public const ACCOUNT_LIFECYCLE = 'account_lifecycle';
    public const ROLE_PERMISSION = 'role_permission';
    public const CHIP_TAN_ACCESS = 'chip_tan_access';
    public const SESSION = 'session';
    public const API_INTEGRATION = 'api_integration';

    public static function all(): array
    {
        return [
            self::AUTHENTICATION, self::AUTHORIZATION,
            self::ACCOUNT_LIFECYCLE, self::ROLE_PERMISSION,
            self::CHIP_TAN_ACCESS, self::SESSION, self::API_INTEGRATION,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('alarms.security_category.' . $v);
        return $translated !== 'alarms.security_category.' . $v
            ? $translated
            : ucwords(str_replace('_', ' ', $v));
    }

    /**
     * Derive from the event_type's prefix + tail.
     *
     * Examples:
     *   auth.login_failed         → authentication
     *   permission.denied         → authorization
     *   user.created / .disabled  → account_lifecycle
     *   role.permissions_updated  → role_permission
     *   auth_medium.* / tan.*     → chip_tan_access
     *   session.timeout           → session
     *   api.token_revoked         → api_integration
     */
    public static function deriveFrom(?string $eventType): string
    {
        if ($eventType === null || $eventType === '') return self::SESSION;
        [$prefix, $tail] = array_pad(explode('.', $eventType, 2), 2, '');

        return match ($prefix) {
            'auth' => self::AUTHENTICATION,
            'permission' => self::AUTHORIZATION,
            'user' => str_contains($tail, 'role')
                ? self::ROLE_PERMISSION
                : self::ACCOUNT_LIFECYCLE,
            'role' => self::ROLE_PERMISSION,
            'auth_medium', 'tan', 'chip_card' => self::CHIP_TAN_ACCESS,
            'session' => self::SESSION,
            'api', 'service_token', 'integration' => self::API_INTEGRATION,
            default => self::AUTHENTICATION,
        };
    }
}
