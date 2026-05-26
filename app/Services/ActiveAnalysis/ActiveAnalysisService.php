<?php

namespace App\Services\ActiveAnalysis;

use App\Enums\ActiveAnalysisStatus;
use App\Enums\ActiveAnalysisType;
use App\Enums\AnalysisUserAction;
use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Models\ActiveAnalysis;
use App\Models\AnalysisAttempt;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single source of truth for Active Analyses (V1.4).
 *
 * Responsibilities:
 *   - Compute `required_action` + `allowed_actions[]` from the analysis
 *     row's state (V1.4 §6 action-availability matrix). Backend OWNS
 *     this — the FE never infers required action from raw element
 *     values (V1.4 §18).
 *   - Execute the 8 user actions (put on hold, repeat, cancel, release,
 *     reject-block, open-fault, repeat-measurement, manual-approval),
 *     writing one audit row + one event row each.
 *   - Enforce the per-action invariants:
 *       * max_attempts before repeat
 *       * VA-4 only on 3rd functionally-NOK pre-analysis
 *       * HA-3 only on first technically-invalid main analysis
 *       * MANUAL_FUNCTIONAL_APPROVAL only on main analysis NOK
 *
 * Returns `{ ok: bool, code?, analysis?, details? }` for the
 * controller to translate into HTTP responses.
 */
class ActiveAnalysisService
{
    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events
    ) {}

    /* ============================================================
     * Action computation (V1.4 §6 action-availability matrix)
     * ============================================================ */

    /**
     * Compute the canonical primary action + allowed list for the row's
     * current state. Called whenever the status changes so the cached
     * columns stay coherent.
     *
     * @return array{required_action: ?string, required_action_reason: ?string, allowed_actions: array<int,string>}
     */
    public function computeAllowedActions(ActiveAnalysis $a): array
    {
        $always = [AnalysisUserAction::VIEW_DETAILS];
        if ($a->related_result_id) {
            $always[] = AnalysisUserAction::OPEN_RELATED_RESULT_RECORD;
        }

        // Active running states — only monitor, no decision
        if (in_array($a->status, [
            ActiveAnalysisStatus::QUEUED,
            ActiveAnalysisStatus::PREPARING,
            ActiveAnalysisStatus::PURGING,
            ActiveAnalysisStatus::RUNNING,
            ActiveAnalysisStatus::WAITING_RESULT,
        ], true)) {
            return [
                'required_action' => null,
                'required_action_reason' => null,
                'allowed_actions' => array_merge([AnalysisUserAction::PUT_ON_HOLD, AnalysisUserAction::CANCEL_ANALYSIS], $always),
            ];
        }

        // RESULT_RECEIVED with no decision yet → backend waits for rule
        if ($a->status === ActiveAnalysisStatus::RESULT_RECEIVED) {
            return [
                'required_action' => null,
                'required_action_reason' => 'Awaiting rule evaluation.',
                'allowed_actions' => array_merge([AnalysisUserAction::PUT_ON_HOLD, AnalysisUserAction::CANCEL_ANALYSIS], $always),
            ];
        }

        // WAITING_DECISION on pre-analysis OK → Release Loading (VA-2)
        if ($a->status === ActiveAnalysisStatus::WAITING_DECISION && $a->analysis_type === ActiveAnalysisType::PRE_ANALYSIS) {
            return [
                'required_action' => AnalysisUserAction::RELEASE_LOADING,
                'required_action_reason' => 'Pre-analysis OK — manual release is required to continue loading.',
                'allowed_actions' => array_merge([
                    AnalysisUserAction::RELEASE_LOADING,
                    AnalysisUserAction::PUT_ON_HOLD,
                    AnalysisUserAction::CANCEL_ANALYSIS,
                ], $always),
            ];
        }

        // NOK on pre-analysis
        if ($a->status === ActiveAnalysisStatus::NOK && $a->analysis_type === ActiveAnalysisType::PRE_ANALYSIS) {
            // VA-4: 3rd functionally-NOK pre-analysis → reject/block trailer
            if ($a->attempt_count >= $a->max_attempts) {
                return [
                    'required_action' => AnalysisUserAction::REJECT_LOADING_BLOCK_TRAILER,
                    'required_action_reason' => sprintf(
                        'Third pre-analysis is functionally NOK (attempt %d/%d); loading cannot be released.',
                        $a->attempt_count,
                        $a->max_attempts
                    ),
                    'allowed_actions' => array_merge([
                        AnalysisUserAction::REJECT_LOADING_BLOCK_TRAILER,
                        AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK,
                    ], $always),
                ];
            }
            // VA-3: retry allowed
            return [
                'required_action' => AnalysisUserAction::REQUEST_REPEAT_ANALYSIS,
                'required_action_reason' => sprintf(
                    'Pre-analysis failed limit (attempt %d/%d); request a repeat.',
                    $a->attempt_count,
                    $a->max_attempts
                ),
                'allowed_actions' => array_merge([
                    AnalysisUserAction::REQUEST_REPEAT_ANALYSIS,
                    AnalysisUserAction::PUT_ON_HOLD,
                    AnalysisUserAction::CANCEL_ANALYSIS,
                ], $always),
            ];
        }

        // INVALID on pre-analysis (technically untrusted)
        if ($a->status === ActiveAnalysisStatus::INVALID && $a->analysis_type === ActiveAnalysisType::PRE_ANALYSIS) {
            if ($a->attempt_count >= $a->max_attempts) {
                // VA-5
                return [
                    'required_action' => AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK,
                    'required_action_reason' => 'Third pre-analysis is technically invalid; manual check / fault case required.',
                    'allowed_actions' => array_merge([
                        AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK,
                    ], $always),
                ];
            }
            return [
                'required_action' => AnalysisUserAction::REQUEST_REPEAT_ANALYSIS,
                'required_action_reason' => 'Pre-analysis values are technically invalid; request a repeat.',
                'allowed_actions' => array_merge([
                    AnalysisUserAction::REQUEST_REPEAT_ANALYSIS,
                    AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK,
                    AnalysisUserAction::PUT_ON_HOLD,
                ], $always),
            ];
        }

        // INVALID on main analysis
        if ($a->status === ActiveAnalysisStatus::INVALID && $a->analysis_type === ActiveAnalysisType::MAIN_ANALYSIS) {
            // HA-3: single technical repeat allowed
            $hasUsedTechnicalRepeat = $a->attempts()
                ->where('is_repeat', true)
                ->where('triggered_by', 'user_retest')
                ->exists();

            if (! $hasUsedTechnicalRepeat) {
                return [
                    'required_action' => AnalysisUserAction::REPEAT_MEASUREMENT,
                    'required_action_reason' => 'Main analysis is technically invalid; one technical repeat is allowed.',
                    'allowed_actions' => array_merge([
                        AnalysisUserAction::REPEAT_MEASUREMENT,
                        AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK,
                        AnalysisUserAction::PUT_ON_HOLD,
                    ], $always),
                ];
            }
            // HA-4: technically invalid again → fault case; docs/exit stay blocked
            return [
                'required_action' => AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK,
                'required_action_reason' => 'Technical repeat already used; manual check / fault case required. Documents and exit remain blocked.',
                'allowed_actions' => array_merge([
                    AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK,
                ], $always),
            ];
        }

        // NOK on main analysis (HA-5) — manual functional approval only,
        // gated by future role-permission check.
        if ($a->status === ActiveAnalysisStatus::NOK && $a->analysis_type === ActiveAnalysisType::MAIN_ANALYSIS) {
            return [
                'required_action' => AnalysisUserAction::MANUAL_FUNCTIONAL_APPROVAL,
                'required_action_reason' => 'Main analysis is functionally NOK. Manual functional approval is exceptional and audited; otherwise quality remains blocked.',
                'allowed_actions' => array_merge([
                    AnalysisUserAction::MANUAL_FUNCTIONAL_APPROVAL,
                    AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK,
                    AnalysisUserAction::PUT_ON_HOLD,
                ], $always),
            ];
        }

        // FAILED — analyser run itself failed
        if ($a->status === ActiveAnalysisStatus::FAILED) {
            if ($a->attempt_count >= $a->max_attempts) {
                return [
                    'required_action' => AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK,
                    'required_action_reason' => 'Analyser run failed and the retry budget is exhausted.',
                    'allowed_actions' => array_merge([AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK], $always),
                ];
            }
            return [
                'required_action' => AnalysisUserAction::REQUEST_REPEAT_ANALYSIS,
                'required_action_reason' => 'Analyser run failed; request a repeat.',
                'allowed_actions' => array_merge([
                    AnalysisUserAction::REQUEST_REPEAT_ANALYSIS,
                    AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK,
                    AnalysisUserAction::CANCEL_ANALYSIS,
                ], $always),
            ];
        }

        // ON_HOLD
        if ($a->status === ActiveAnalysisStatus::ON_HOLD) {
            return [
                'required_action' => null,
                'required_action_reason' => 'Analysis is on hold; resume by cancelling or following backend flow.',
                'allowed_actions' => array_merge([AnalysisUserAction::CANCEL_ANALYSIS], $always),
            ];
        }

        // CANCELLED / CLOSED — terminal, no action
        return [
            'required_action' => null,
            'required_action_reason' => null,
            'allowed_actions' => $always,
        ];
    }

    /**
     * Persist computed action snapshot. Called by every state transition.
     */
    public function refreshActionSnapshot(ActiveAnalysis $a): ActiveAnalysis
    {
        $snap = $this->computeAllowedActions($a);
        $a->required_action = $snap['required_action'];
        $a->required_action_reason = $snap['required_action_reason'];
        $a->allowed_actions = $snap['allowed_actions'];
        $a->save();
        return $a->fresh();
    }

    /* ============================================================
     * Action endpoints — each returns {ok,code?,analysis?}
     * ============================================================ */

    public function putOnHold(ActiveAnalysis $a, string $reason): array
    {
        if (in_array($a->status, [
            ActiveAnalysisStatus::CANCELLED,
            ActiveAnalysisStatus::CLOSED,
            ActiveAnalysisStatus::ON_HOLD,
        ], true)) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }

        return $this->withTransaction($a, AuditAction::ANALYSIS_PUT_ON_HOLD, 'analysis.put_on_hold',
            EventSeverity::INFO, $reason, function (ActiveAnalysis $a) use ($reason) {
                $a->status = ActiveAnalysisStatus::ON_HOLD;
                $a->held_at = now();
                $a->hold_reason = $reason;
                $a->save();
            }
        );
    }

    public function requestRepeat(ActiveAnalysis $a, string $reason): array
    {
        if (! in_array($a->status, [
            ActiveAnalysisStatus::NOK,
            ActiveAnalysisStatus::INVALID,
            ActiveAnalysisStatus::FAILED,
            ActiveAnalysisStatus::RESULT_RECEIVED,
        ], true)) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }
        if ($a->attempt_count >= $a->max_attempts) {
            return ['ok' => false, 'code' => 'ANALYSIS_MAX_ATTEMPTS_REACHED'];
        }

        return $this->withTransaction($a, AuditAction::ANALYSIS_REPEAT_REQUESTED, 'analysis.repeat_requested',
            EventSeverity::INFO, $reason, function (ActiveAnalysis $a) use ($reason) {
                $a->attempt_count += 1;
                $a->status = ActiveAnalysisStatus::QUEUED;
                $a->save();

                AnalysisAttempt::create([
                    'id' => (string) Str::uuid(),
                    'analysis_id' => $a->id,
                    'attempt_no' => $a->attempt_count,
                    'status' => ActiveAnalysisStatus::QUEUED,
                    'triggered_by' => 'user_repeat',
                    'started_at' => now(),
                    'is_repeat' => true,
                    'request_reason' => $reason,
                    'correlation_id' => request()?->header('X-Correlation-Id'),
                ]);
            }
        );
    }

    public function cancel(ActiveAnalysis $a, string $reason): array
    {
        if (in_array($a->status, [
            ActiveAnalysisStatus::CANCELLED,
            ActiveAnalysisStatus::CLOSED,
        ], true)) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }

        return $this->withTransaction($a, AuditAction::ANALYSIS_CANCELLED, 'analysis.cancelled',
            EventSeverity::WARNING, $reason, function (ActiveAnalysis $a) use ($reason) {
                $a->status = ActiveAnalysisStatus::CANCELLED;
                $a->cancelled_at = now();
                $a->cancellation_reason = $reason;
                $a->save();
            }
        );
    }

    public function releaseLoading(ActiveAnalysis $a, ?string $reason = null): array
    {
        // VA-2: pre-analysis OK + WAITING_DECISION
        if ($a->analysis_type !== ActiveAnalysisType::PRE_ANALYSIS
            || $a->status !== ActiveAnalysisStatus::WAITING_DECISION) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }

        return $this->withTransaction($a, AuditAction::ANALYSIS_LOADING_RELEASED, 'analysis.loading_released',
            EventSeverity::INFO, $reason, function (ActiveAnalysis $a) {
                $a->status = ActiveAnalysisStatus::CLOSED;
                $a->closed_at = now();
                $a->save();
            }
        );
    }

    public function rejectLoading(ActiveAnalysis $a, string $reason): array
    {
        // VA-4: pre-analysis NOK on attempt_count >= max_attempts
        if ($a->analysis_type !== ActiveAnalysisType::PRE_ANALYSIS
            || $a->status !== ActiveAnalysisStatus::NOK
            || $a->attempt_count < $a->max_attempts) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }

        return $this->withTransaction($a, AuditAction::ANALYSIS_LOADING_REJECTED, 'analysis.loading_rejected',
            EventSeverity::WARNING, $reason, function (ActiveAnalysis $a) use ($reason) {
                $a->status = ActiveAnalysisStatus::CLOSED;
                $a->closed_at = now();
                $a->cancellation_reason = $reason;        // re-use the field for the reject reason
                $a->save();
            }
        );
    }

    public function openFaultCase(ActiveAnalysis $a, string $reason, ?string $deviceBmk = null, ?string $element = null): array
    {
        // VA-5 / HA-4: must be INVALID OR (FAILED & max attempts) OR (after technical-repeat-used main)
        $allowedStatuses = [
            ActiveAnalysisStatus::INVALID,
            ActiveAnalysisStatus::FAILED,
        ];
        if (! in_array($a->status, $allowedStatuses, true)) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }

        return $this->withTransaction($a, AuditAction::ANALYSIS_FAULT_CASE_OPENED, 'analysis.fault_case_opened',
            EventSeverity::WARNING, $reason, function (ActiveAnalysis $a) {
                // Stays open as INVALID/FAILED but with required_action
                // explicitly cleared so the queue stops nagging. A future
                // clarification-case service hook can cross-create the
                // case row.
                $a->required_action = null;
                $a->required_action_reason = 'Fault case opened; awaiting manual check resolution.';
                $a->save();
            }, ['affected_device_bmk' => $deviceBmk, 'affected_element' => $element]
        );
    }

    public function repeatMeasurement(ActiveAnalysis $a, string $reason): array
    {
        // HA-3: main analysis + INVALID + technical repeat NOT yet used
        if ($a->analysis_type !== ActiveAnalysisType::MAIN_ANALYSIS
            || $a->status !== ActiveAnalysisStatus::INVALID) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }
        $hasUsedTechnicalRepeat = $a->attempts()
            ->where('is_repeat', true)
            ->where('triggered_by', 'user_retest')
            ->exists();
        if ($hasUsedTechnicalRepeat) {
            return ['ok' => false, 'code' => 'ANALYSIS_TECHNICAL_REPEAT_USED'];
        }

        return $this->withTransaction($a, AuditAction::ANALYSIS_MEASUREMENT_REPEATED, 'analysis.measurement_repeated',
            EventSeverity::INFO, $reason, function (ActiveAnalysis $a) use ($reason) {
                $a->attempt_count += 1;
                $a->status = ActiveAnalysisStatus::QUEUED;
                $a->save();

                AnalysisAttempt::create([
                    'id' => (string) Str::uuid(),
                    'analysis_id' => $a->id,
                    'attempt_no' => $a->attempt_count,
                    'status' => ActiveAnalysisStatus::QUEUED,
                    'triggered_by' => 'user_retest',
                    'started_at' => now(),
                    'is_repeat' => true,
                    'request_reason' => $reason,
                    'correlation_id' => request()?->header('X-Correlation-Id'),
                ]);
            }
        );
    }

    public function manualFunctionalApproval(ActiveAnalysis $a, string $reason, ?string $category = null): array
    {
        // HA-5: main analysis + NOK
        if ($a->analysis_type !== ActiveAnalysisType::MAIN_ANALYSIS
            || $a->status !== ActiveAnalysisStatus::NOK) {
            return ['ok' => false, 'code' => 'INVALID_STATE_TRANSITION'];
        }

        return $this->withTransaction($a, AuditAction::ANALYSIS_MANUAL_APPROVED, 'analysis.manual_approved',
            EventSeverity::WARNING, $reason, function (ActiveAnalysis $a) {
                $a->status = ActiveAnalysisStatus::CLOSED;
                $a->closed_at = now();
                $a->save();
            }, ['justification_category' => $category]
        );
    }

    /* ============================================================
     * Internal helper — wraps audit + event + action snapshot refresh
     * ============================================================ */

    /**
     * @param  callable(ActiveAnalysis):void  $mutator
     */
    protected function withTransaction(
        ActiveAnalysis $a,
        string $auditAction,
        string $eventType,
        string $severity,
        ?string $reason,
        callable $mutator,
        array $extraDetails = []
    ): array {
        $fresh = DB::transaction(function () use ($a, $auditAction, $eventType, $severity, $reason, $mutator, $extraDetails) {
            $old = $this->audit->snapshotModel($a);

            $mutator($a);
            $a = $a->fresh();

            // Refresh the cached action snapshot for the new status
            $a = $this->refreshActionSnapshot($a);

            $this->audit->record(
                $a,
                $auditAction,
                $old,
                $this->audit->snapshotModel($a),
                $reason,
                null
            );
            $this->events->record(
                $eventType,
                $a,
                "Analysis {$a->display_no} → {$eventType}",
                array_merge([
                    'analysis_id' => $a->id,
                    'display_no' => $a->display_no,
                    'analysis_type' => $a->analysis_type,
                    'status' => $a->status,
                    'attempt_count' => $a->attempt_count,
                    'max_attempts' => $a->max_attempts,
                    'reason' => $reason,
                ], $extraDetails),
                EventCategory::QUALITY,
                $severity
            );

            return $a;
        });

        return ['ok' => true, 'analysis' => $fresh];
    }
}
