<?php

namespace App\Enums;

/**
 * DERIVED retention class for V1 §8 Change Log / Audit Trail.
 *
 * NOT a stored column — composed at read time from the change category.
 * Per spec §8.3 "More filters" dropdown:
 *   Operational, quality-critical, security-critical, configuration-critical.
 *
 * Retention class is a hint for the FE (and any export workflow) about
 * how long the row must be retained and how sensitive its disclosure
 * is. V1 maps it 1:1 from AuditChangeCategory.
 */
class AuditRetentionClass
{
    public const OPERATIONAL = 'operational';
    public const QUALITY_CRITICAL = 'quality_critical';
    public const SECURITY_CRITICAL = 'security_critical';
    public const CONFIGURATION_CRITICAL = 'configuration_critical';

    public static function all(): array
    {
        return [
            self::OPERATIONAL,
            self::QUALITY_CRITICAL,
            self::SECURITY_CRITICAL,
            self::CONFIGURATION_CRITICAL,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('logbook.audit_retention_class.' . $v);
        return $translated !== 'logbook.audit_retention_class.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function fromChangeCategory(string $changeCategory): string
    {
        return match ($changeCategory) {
            AuditChangeCategory::QUALITY_DECISION => self::QUALITY_CRITICAL,
            AuditChangeCategory::SECURITY_ROLE => self::SECURITY_CRITICAL,
            AuditChangeCategory::CONFIGURATION => self::CONFIGURATION_CRITICAL,
            AuditChangeCategory::MANUAL_OVERRIDE => self::QUALITY_CRITICAL,
            default => self::OPERATIONAL,
        };
    }
}
