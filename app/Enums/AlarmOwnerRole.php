<?php

namespace App\Enums;

/**
 * V1 §6.6 alarm owner role.
 *   Operator, IT/Support, Analysis Specialist, Dispatcher, Admin.
 */
class AlarmOwnerRole
{
    public const OPERATOR = 'operator';
    public const IT_SUPPORT = 'it_support';
    public const ANALYSIS_SPECIALIST = 'analysis_specialist';
    public const DISPATCHER = 'dispatcher';
    public const ADMIN = 'admin';

    public static function all(): array
    {
        return [
            self::OPERATOR, self::IT_SUPPORT, self::ANALYSIS_SPECIALIST,
            self::DISPATCHER, self::ADMIN,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('alarms.owner_role.' . $v);
        return $translated !== 'alarms.owner_role.' . $v
            ? $translated
            : ucwords(str_replace('_', ' ', $v));
    }

    /** Default owner suggested for an alarm category. */
    public static function defaultOwnerForCategory(string $category): string
    {
        return match ($category) {
            AlarmCategory::SAFETY => self::OPERATOR,
            AlarmCategory::LOADING_PROCESS => self::OPERATOR,
            AlarmCategory::QUALITY_ANALYSIS => self::ANALYSIS_SPECIALIST,
            AlarmCategory::GATE_ACCESS => self::DISPATCHER,
            AlarmCategory::DEVICE_COMMUNICATION,
            AlarmCategory::INTERFACE_SAP => self::IT_SUPPORT,
            AlarmCategory::DOCUMENT_PRINTER => self::OPERATOR,
            default => self::OPERATOR,
        };
    }
}
