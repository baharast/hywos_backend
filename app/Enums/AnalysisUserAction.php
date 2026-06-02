<?php

namespace App\Enums;

/**
 * Canonical user actions for an Active Analysis (V1.4 §5 + §17).
 *
 * The FE must only show these 10 action types. The backend computes
 * `allowed_actions[]` per analysis state (V1.4 §6) and returns one
 * `required_action` as the primary CTA. The FE NEVER infers a
 * required action from raw element values (V1.4 §18).
 *
 * Disallowed actions to never expose as free UI buttons (V1.4 §5.1):
 *   - Start Pre/Main analysis  → system-triggered
 *   - Wait                     → a status, not an action
 *   - Quality Blocked          → an outcome status
 *   - Block Documents          → an outcome of the rule engine
 *   - Generic Escalate         → no generic escalation
 *   - Override Quality         → use MANUAL_FUNCTIONAL_APPROVAL instead
 */
class AnalysisUserAction
{
    public const VIEW_DETAILS = 'view_details';
    public const PUT_ON_HOLD = 'put_on_hold';
    public const REQUEST_REPEAT_ANALYSIS = 'request_repeat_analysis';
    public const CANCEL_ANALYSIS = 'cancel_analysis';
    public const RELEASE_LOADING = 'release_loading';
    public const REJECT_LOADING_BLOCK_TRAILER = 'reject_loading_block_trailer';
    public const OPEN_FAULT_CASE_MANUAL_CHECK = 'open_fault_case_manual_check';
    public const REPEAT_MEASUREMENT = 'repeat_measurement';
    public const MANUAL_FUNCTIONAL_APPROVAL = 'manual_functional_approval';
    public const OPEN_RELATED_RESULT_RECORD = 'open_related_result_record';

    public static function all(): array
    {
        return [
            self::VIEW_DETAILS,
            self::PUT_ON_HOLD,
            self::REQUEST_REPEAT_ANALYSIS,
            self::CANCEL_ANALYSIS,
            self::RELEASE_LOADING,
            self::REJECT_LOADING_BLOCK_TRAILER,
            self::OPEN_FAULT_CASE_MANUAL_CHECK,
            self::REPEAT_MEASUREMENT,
            self::MANUAL_FUNCTIONAL_APPROVAL,
            self::OPEN_RELATED_RESULT_RECORD,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('analysis.user_action.' . $v);
        return $translated !== 'analysis.user_action.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    /**
     * Actions that require a reason / audit note (V1.4 §5 + §11.2 +
     * §18). VIEW_DETAILS and OPEN_RELATED_RESULT_RECORD are navigation
     * only — no reason.
     */
    public static function requiresReason(string $v): bool
    {
        return in_array($v, [
            self::PUT_ON_HOLD,
            self::REQUEST_REPEAT_ANALYSIS,
            self::CANCEL_ANALYSIS,
            self::REJECT_LOADING_BLOCK_TRAILER,
            self::OPEN_FAULT_CASE_MANUAL_CHECK,
            self::REPEAT_MEASUREMENT,
            self::MANUAL_FUNCTIONAL_APPROVAL,
        ], true);
    }

    /**
     * Short rule-source label (V1.4 §11.2 "Action source") — e.g.
     * "VA-4" for reject-loading on 3rd functionally-NOK pre-analysis,
     * "HA-5" for manual functional approval on main analysis. Returned
     * alongside the primary action so the workbench can render the
     * small metadata line.
     */
    public static function ruleSource(string $v): ?string
    {
        return match ($v) {
            self::RELEASE_LOADING => 'VA-2',
            self::REQUEST_REPEAT_ANALYSIS => 'VA-3 / HA-3',
            self::REJECT_LOADING_BLOCK_TRAILER => 'VA-4',
            self::OPEN_FAULT_CASE_MANUAL_CHECK => 'VA-5 / HA-4',
            self::REPEAT_MEASUREMENT => 'HA-3',
            self::MANUAL_FUNCTIONAL_APPROVAL => 'HA-5',
            default => null,
        };
    }
}
