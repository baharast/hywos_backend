<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActiveAnalysisStatus;
use App\Enums\ActiveAnalysisType;
use App\Enums\ClosedDecisionStatus;
use App\Enums\OpenDecisionStatus;
use App\Enums\ResultStatus;
use App\Http\Resources\QualityDecisionDetailResource;
use App\Http\Resources\QualityDecisionListItemResource;
use App\Models\ActiveAnalysis;
use App\Models\AuditLog;
use App\Services\ApiResponse;
use App\Services\QualityDecision\QualityDecisionService;
use Illuminate\Http\Request;

/**
 * Results & Quality Decisions (V1.1) — read-only review surface.
 *
 * Default list returns only closed/registered records (V1.1 §4). The
 * `include_open_decisions=true` filter widens the result to also include
 * open analyses, but those rows are clearly marked `actionOwner=active_analyses`
 * and the FE must route action clicks back to that module.
 *
 * No write endpoints. No bulk endpoints. No certificate / document
 * mutation.
 */
class QualityDecisionController extends ApiController
{
    public function __construct(protected QualityDecisionService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);
        $includeOpen = filter_var(
            $request->query('include_open_decisions', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $query = ActiveAnalysis::query()
            ->with(['latestAttempt', 'elementResults']);

        // V1.1 §4 default: closed/cancelled rows only.
        if (! $includeOpen) {
            $query->whereIn('status', [
                ActiveAnalysisStatus::CLOSED,
                ActiveAnalysisStatus::CANCELLED,
            ]);
        }

        $this->applyFilters($query, $request->all());

        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['updated_at', 'closed_at', 'created_at', 'display_no'];
        if (! in_array($column, $allowed, true)) {
            $column = 'updated_at';
            $direction = 'desc';
        }

        $paginator = $query->orderBy($column, $direction)->paginate($perPage);
        $rows = QualityDecisionListItemResource::collection($paginator->items());

        $lastUpdated = ActiveAnalysis::query()->max('updated_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->summary($includeOpen),
            $lastUpdated,
            __('analysis.messages.results_retrieved')
        );
    }

    public function show(string $id)
    {
        $analysis = ActiveAnalysis::query()
            ->with(['latestAttempt', 'elementResults'])
            ->find($id);

        if (! $analysis) {
            return $this->error(__('analysis.messages.result_not_found'), 'RESULT_NOT_FOUND', 404);
        }

        // Reconstruct a decision-record audit slice. The resource composes
        // it into the response; we don't issue extra queries from there.
        $auditRows = AuditLog::query()
            ->where('entity_type', $analysis->getMorphClass())
            ->where('entity_id', $analysis->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'actorUserId' => $a->actor_user_id,
                'actorName' => $a->actor_name,
                'reason' => $a->reason,
                'reasonCode' => $a->reason_code,
                'createdAt' => $a->created_at?->toIso8601String(),
            ])
            ->all();

        $resource = (new QualityDecisionDetailResource($analysis))
            ->additional(['auditRows' => $auditRows]);

        return $this->success($resource, __('analysis.messages.result_retrieved'));
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('display_no', 'like', $search)
                    ->orWhere('order_no', 'like', $search)
                    ->orWhere('sap_order_no', 'like', $search)
                    ->orWhere('trailer_label', 'like', $search)
                    ->orWhere('driver_name', 'like', $search)
                    ->orWhere('visit_no', 'like', $search)
                    ->orWhere('station_name', 'like', $search)
                    ->orWhere('product_code', 'like', $search);
            });
        }

        if (! empty($filters['analysis_type'])) {
            $type = $filters['analysis_type'];
            if (in_array($type, ActiveAnalysisType::all(), true)) {
                $query->where('analysis_type', $type);
            }
        }

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }
        if (! empty($filters['trailer_id'])) {
            $query->where('trailer_id', $filters['trailer_id']);
        }
        if (! empty($filters['analysis_id'])) {
            $query->where('id', $filters['analysis_id']);
        }
        if (! empty($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }
        if (! empty($filters['product_spec_id'])) {
            $query->where('product_spec_id', $filters['product_spec_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // `result_status` and `decision_status` are DERIVED — we can't
        // filter at the SQL layer. We approximate by mapping common FE
        // selections onto the underlying analysis `status` column:
        if (! empty($filters['decision_status'])) {
            $ds = $filters['decision_status'];
            if (in_array($ds, [ClosedDecisionStatus::BLOCKED, ClosedDecisionStatus::REJECTED], true)) {
                $query->where('status', ActiveAnalysisStatus::CLOSED)->whereNotNull('cancellation_reason');
            } elseif (in_array($ds, [ClosedDecisionStatus::APPROVED, ClosedDecisionStatus::RELEASED], true)) {
                $query->where('status', ActiveAnalysisStatus::CLOSED)->whereNull('cancellation_reason');
            } elseif ($ds === ClosedDecisionStatus::CLOSED) {
                $query->where('status', ActiveAnalysisStatus::CANCELLED);
            } elseif (in_array($ds, OpenDecisionStatus::all(), true)) {
                // Surface open rows only — caller is using include_open_decisions
                $query->whereIn('status', ActiveAnalysisStatus::openStatuses());
            }
        }

        if (! empty($filters['result_status'])) {
            // `result_status` is derived per-row. We only narrow when
            // the FE asks for incomplete/invalid — backed by the cached
            // element_summary string ("missing", "invalid").
            $rs = $filters['result_status'];
            if ($rs === ResultStatus::INVALID) {
                $query->where('element_summary', 'like', '%invalid%');
            } elseif ($rs === ResultStatus::INCOMPLETE) {
                $query->where('element_summary', 'like', '%missing%');
            } elseif ($rs === ResultStatus::NOK) {
                $query->where('element_summary', 'like', '%high%');
            } elseif ($rs === ResultStatus::PASSED) {
                $query->where('element_summary', 'not like', '%invalid%')
                    ->where('element_summary', 'not like', '%missing%')
                    ->where('element_summary', 'not like', '%high%');
            }
        }
    }

    protected function summary(bool $includeOpen): array
    {
        $base = ActiveAnalysis::query();

        // V1.1 §8.2 — collapse to a calm "no open" message if everything is zero.
        $closedScope = (clone $base)->whereIn('status', [
            ActiveAnalysisStatus::CLOSED,
            ActiveAnalysisStatus::CANCELLED,
        ]);

        $today = now()->startOfDay();
        $resultsToday = (clone $closedScope)->where('updated_at', '>=', $today)->count();

        $passedReleased = (clone $closedScope)
            ->where('status', ActiveAnalysisStatus::CLOSED)
            ->whereNull('cancellation_reason')
            ->count();

        $blockedRejected = (clone $closedScope)
            ->where('status', ActiveAnalysisStatus::CLOSED)
            ->whereNotNull('cancellation_reason')
            ->count();

        $invalidIncomplete = (clone $closedScope)
            ->where(function ($q) {
                $q->where('element_summary', 'like', '%invalid%')
                    ->orWhere('element_summary', 'like', '%missing%');
            })
            ->count();

        $summary = [
            'resultsToday' => $resultsToday,
            'passedReleased' => $passedReleased,
            'blockedRejected' => $blockedRejected,
            'invalidIncomplete' => $invalidIncomplete,
        ];

        if ($includeOpen) {
            $summary['openDecisions'] = (clone $base)
                ->whereIn('status', ActiveAnalysisStatus::openStatuses())
                ->count();
        }

        return $summary;
    }
}
