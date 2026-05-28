<?php

namespace App\Enums;

/**
 * V1.1 §12.2 open-decision statuses — read-only review surface when the
 * Include open decisions filter is enabled. These rows must NEVER carry
 * a state-changing action on Results & Quality Decisions (V1.1 §4); the
 * only allowed action is "Open in Active Analyses".
 */
class OpenDecisionStatus
{
    public const REPEAT_REQUESTED = 'repeat_requested';
    public const PENDING_REVIEW = 'pending_review';
    public const ESCALATION_REQUIRED = 'escalation_required';
    public const WAITING_FOR_DECISION = 'waiting_for_decision';

    public static function all(): array
    {
        return [
            self::REPEAT_REQUESTED,
            self::PENDING_REVIEW,
            self::ESCALATION_REQUIRED,
            self::WAITING_FOR_DECISION,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::REPEAT_REQUESTED => 'Repeat requested',
            self::PENDING_REVIEW => 'Pending review',
            self::ESCALATION_REQUIRED => 'Escalation required',
            self::WAITING_FOR_DECISION => 'Waiting for decision',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::REPEAT_REQUESTED => 'info',
            self::PENDING_REVIEW => 'warning',
            self::ESCALATION_REQUIRED => 'danger',
            self::WAITING_FOR_DECISION => 'warning',
            default => 'neutral',
        };
    }
}
