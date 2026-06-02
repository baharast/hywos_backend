<?php

namespace App\Enums;

class EventSeverity
{
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const DANGER = 'danger';
    public const CRITICAL = 'critical';

    public static function label(string $v): string
    {
        $translated = __('alarms.event_severity.' . $v);
        return $translated !== 'alarms.event_severity.' . $v ? $translated : ucfirst($v);
    }
}
