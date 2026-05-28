<?php

namespace App\Services\QualityDecision;

use App\Enums\ActiveAnalysisStatus;
use App\Enums\AnalysisElementStatus;
use App\Enums\AnalysisUserAction;
use App\Enums\CertificateImpact;
use App\Enums\ClosedDecisionStatus;
use App\Enums\OpenDecisionStatus;
use App\Enums\ResultStatus;
use App\Models\ActiveAnalysis;
use App\Models\AnalysisElementResult;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the Results & Quality Decisions read view.
 *
 * The page has NO new tables and NO new audit rows — it shapes
 * existing `analyses` rows and their `analysis_element_results` into
 * the V1.1 review payload. The four "decision domain" fields below are
 * derived per analysis row:
 *
 *   resultStatus       — passed / nok / invalid / incomplete (V1.1 §12.1)
 *   decisionStatus     — closed: approved/released/blocked/rejected/closed
 *                        open:   repeat_requested/pending_review/...
 *   certificateImpact  — allowed / blocked / generated / not_required
 *   actionOwner        — 'results' for closed rows, 'active_analyses' for open
 *
 * The `audit_logs` table is the source of truth for the decision-
 * record block (decision maker, justification, timestamp). The service
 * reads recent rows for the analysis entity when the FE opens detail.
 */
class QualityDecisionService
{
    /* ============================================================
     * Status derivation
     * ============================================================ */

    /**
     * Derive `resultStatus` from the latest attempt's 6 element results.
     * Returns one of ResultStatus::* values.
     *
     * @param  Collection<int, AnalysisElementResult>  $elements
     */
    public function deriveResultStatus(Collection $elements): string
    {
        if ($elements->isEmpty()) {
            return ResultStatus::INCOMPLETE;
        }

        $hasInvalid = $elements->contains(fn ($e) =>
            $e->status === AnalysisElementStatus::INVALID
        );
        $hasMissing = $elements->contains(fn ($e) =>
            in_array($e->status, [
                AnalysisElementStatus::MISSING,
                AnalysisElementStatus::NOT_TRANSFERRED,
            ], true)
        );
        $hasNok = $elements->contains(fn ($e) =>
            $e->status === AnalysisElementStatus::NOK
        );

        // V1.1 §11.4: invalid takes priority over nok when present —
        // an untrusted value is more dangerous than a clearly failed one.
        if ($hasInvalid) {
            return ResultStatus::INVALID;
        }
        if ($hasMissing) {
            return ResultStatus::INCOMPLETE;
        }
        if ($hasNok) {
            return ResultStatus::NOK;
        }
        return ResultStatus::PASSED;
    }

    /**
     * Derive `decisionStatus` from the analysis row's status. Returns a
     * tuple [status_value, is_open]. `is_open` drives the FE rule that
     * open rows can only show "Open in Active Analyses".
     *
     * @return array{0:string, 1:bool}
     */
    public function deriveDecisionStatus(ActiveAnalysis $a): array
    {
        return match ($a->status) {
            // CLOSED states
            ActiveAnalysisStatus::CLOSED => [
                // The "closed" status covers release / reject / manual approval.
                // We look at the cancellation_reason vs the related_result_id
                // to differentiate. Empty cancellation_reason + closed_at →
                // approved/released. Filled cancellation_reason → rejected.
                ! empty($a->cancellation_reason) ? ClosedDecisionStatus::REJECTED : ClosedDecisionStatus::RELEASED,
                false,
            ],
            ActiveAnalysisStatus::CANCELLED => [ClosedDecisionStatus::CLOSED, false],

            // OPEN states — only surfaced when Include open decisions=true
            ActiveAnalysisStatus::WAITING_DECISION => [OpenDecisionStatus::WAITING_FOR_DECISION, true],
            ActiveAnalysisStatus::NOK => [OpenDecisionStatus::PENDING_REVIEW, true],
            ActiveAnalysisStatus::INVALID => [OpenDecisionStatus::PENDING_REVIEW, true],
            ActiveAnalysisStatus::FAILED => [OpenDecisionStatus::PENDING_REVIEW, true],
            ActiveAnalysisStatus::ON_HOLD => [OpenDecisionStatus::PENDING_REVIEW, true],
            ActiveAnalysisStatus::QUEUED,
            ActiveAnalysisStatus::PREPARING,
            ActiveAnalysisStatus::PURGING,
            ActiveAnalysisStatus::RUNNING,
            ActiveAnalysisStatus::WAITING_RESULT,
            ActiveAnalysisStatus::RESULT_RECEIVED => [OpenDecisionStatus::PENDING_REVIEW, true],

            default => [OpenDecisionStatus::PENDING_REVIEW, true],
        };
    }

    /**
     * Derive `certificateImpact` from analysis_type + decisionStatus +
     * resultStatus.
     */
    public function deriveCertificateImpact(ActiveAnalysis $a, string $resultStatus, string $decisionStatus): string
    {
        // Retest / final analyses don't drive a certificate directly
        if (in_array($a->analysis_type, ['retest'], true)) {
            return CertificateImpact::NOT_REQUIRED;
        }

        // If the analysis is closed via reject / cancellation → blocked
        if (in_array($decisionStatus, [ClosedDecisionStatus::REJECTED, ClosedDecisionStatus::CLOSED], true)
            && $a->status === ActiveAnalysisStatus::CANCELLED) {
            return CertificateImpact::BLOCKED;
        }
        if ($decisionStatus === ClosedDecisionStatus::REJECTED) {
            return CertificateImpact::BLOCKED;
        }
        if ($decisionStatus === ClosedDecisionStatus::BLOCKED) {
            return CertificateImpact::BLOCKED;
        }
        if (in_array($decisionStatus, [ClosedDecisionStatus::APPROVED, ClosedDecisionStatus::RELEASED], true)
            && in_array($resultStatus, [ResultStatus::PASSED, ResultStatus::NOK], true)) {
            // PASSED → allowed; NOK + released = manual approval path (cert allowed)
            return $a->related_result_id ? CertificateImpact::GENERATED : CertificateImpact::ALLOWED;
        }
        if (in_array($resultStatus, [ResultStatus::INVALID, ResultStatus::INCOMPLETE], true)) {
            return CertificateImpact::BLOCKED;
        }

        return CertificateImpact::ALLOWED;
    }

    /**
     * Whether the row's decision is currently open (i.e. action belongs
     * to Active Analyses, not here).
     */
    public function isOpenDecision(ActiveAnalysis $a): bool
    {
        return in_array($a->status, ActiveAnalysisStatus::openStatuses(), true)
            && ! in_array($a->status, [
                ActiveAnalysisStatus::CLOSED,
                ActiveAnalysisStatus::CANCELLED,
            ], true);
    }

    /* ============================================================
     * Compact element summary (for the table column)
     * ============================================================ */

    /**
     * Generate the V1.1 §10.1 "Element Summary" cell content (e.g.
     * "6/6 OK", "O2 high, CO2 invalid", "5/6 received").
     *
     * @param  Collection<int, AnalysisElementResult>  $elements
     */
    public function elementSummary(Collection $elements): string
    {
        if ($elements->isEmpty()) {
            return '0/0 received';
        }

        $total = $elements->count();
        $issues = [];

        foreach ($elements as $e) {
            $tag = match ($e->status) {
                AnalysisElementStatus::NOK => "{$e->element} high",
                AnalysisElementStatus::INVALID => "{$e->element} invalid",
                AnalysisElementStatus::MISSING, AnalysisElementStatus::NOT_TRANSFERRED => "{$e->element} missing",
                default => null,
            };
            if ($tag) {
                $issues[] = $tag;
            }
        }

        if (empty($issues)) {
            return "{$total}/{$total} OK";
        }
        $clean = $total - count($issues);
        $tagsList = implode(', ', $issues);
        return "{$clean}/{$total} OK · {$tagsList}";
    }

    /**
     * Distinct failed elements list — used by the "Failed Parameters"
     * optional column.
     *
     * @param  Collection<int, AnalysisElementResult>  $elements
     * @return array<int,string>
     */
    public function failedParameters(Collection $elements): array
    {
        return $elements
            ->filter(fn ($e) => in_array($e->status, [
                AnalysisElementStatus::NOK,
                AnalysisElementStatus::INVALID,
                AnalysisElementStatus::MISSING,
                AnalysisElementStatus::NOT_TRANSFERRED,
            ], true))
            ->map(fn ($e) => $e->element)
            ->values()
            ->all();
    }

    /* ============================================================
     * Decision-record reconstruction (from audit_logs)
     * ============================================================ */

    /**
     * Map the analysis status + cancellation/closed metadata to the
     * canonical V1.4 §5 action that produced the current decision.
     * Returns null for rows that don't yet carry a closed decision.
     */
    public function inferLastDecisionAction(ActiveAnalysis $a): ?string
    {
        if ($a->status === ActiveAnalysisStatus::CLOSED) {
            if (! empty($a->cancellation_reason)) {
                return AnalysisUserAction::REJECT_LOADING_BLOCK_TRAILER;
            }
            if ($a->analysis_type === 'pre_analysis') {
                return AnalysisUserAction::RELEASE_LOADING;
            }
            return AnalysisUserAction::MANUAL_FUNCTIONAL_APPROVAL;
        }
        if ($a->status === ActiveAnalysisStatus::CANCELLED) {
            return AnalysisUserAction::CANCEL_ANALYSIS;
        }
        return null;
    }
}
