<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActiveAnalysisStatus;
use App\Enums\ActiveAnalysisType;
use App\Enums\AnalysisUserAction;
use App\Enums\SamplingTrigger;
use App\Http\Requests\ActiveAnalysis\CancelAnalysisRequest;
use App\Http\Requests\ActiveAnalysis\ManualFunctionalApprovalRequest;
use App\Http\Requests\ActiveAnalysis\OpenFaultCaseRequest;
use App\Http\Requests\ActiveAnalysis\PutOnHoldRequest;
use App\Http\Requests\ActiveAnalysis\RejectLoadingRequest;
use App\Http\Requests\ActiveAnalysis\ReleaseLoadingRequest;
use App\Http\Requests\ActiveAnalysis\RepeatMeasurementRequest;
use App\Http\Requests\ActiveAnalysis\RequestRepeatAnalysisRequest;
use App\Http\Resources\ActiveAnalysisDetailResource;
use App\Http\Resources\ActiveAnalysisListItemResource;
use App\Models\ActiveAnalysis;
use App\Services\ActiveAnalysis\ActiveAnalysisService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;

/**
 * Active Analyses (V1.4) — action-first queue + workbench.
 *
 * Endpoints:
 *   GET  /api/active-analyses                    list + summary
 *   GET  /api/active-analyses/{id}               workbench detail
 *   POST /api/active-analyses/{id}/hold
 *   POST /api/active-analyses/{id}/repeat
 *   POST /api/active-analyses/{id}/cancel
 *   POST /api/active-analyses/{id}/release-loading
 *   POST /api/active-analyses/{id}/reject-loading
 *   POST /api/active-analyses/{id}/open-fault-case
 *   POST /api/active-analyses/{id}/repeat-measurement
 *   POST /api/active-analyses/{id}/manual-approval
 *
 * The controller is a thin HTTP shell. ALL action logic + the V1.4 §6
 * state-action matrix lives in ActiveAnalysisService.
 */
class ActiveAnalysisController extends ApiController
{
    public function __construct(protected ActiveAnalysisService $service) {}

    /* ============================================================
     * READ
     * ============================================================ */

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = ActiveAnalysis::query()->with('latestAttempt');

        $this->applyFilters($query, $request->all());

        // Default sort per V1.4 §10: action-required first, then most
        // recently updated. The action_required flag is approximated by
        // checking required_action IS NOT NULL.
        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (! in_array($column, ['updated_at', 'created_at', 'display_no', 'status', 'analysis_type'], true)) {
            $column = 'updated_at';
            $direction = 'desc';
        }
        if ($column === 'updated_at' && $direction === 'desc' && ! $request->has('sort')) {
            // Action-required to the top, then by recency.
            $query->orderByRaw('required_action IS NULL')->orderByDesc('updated_at');
        } else {
            $query->orderBy($column, $direction);
        }

        $paginator = $query->paginate($perPage);
        $rows = ActiveAnalysisListItemResource::collection($paginator->items());

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->summary(),
            ActiveAnalysis::query()->max('updated_at'),
            'Active analyses retrieved'
        );
    }

    public function show(string $id)
    {
        $a = ActiveAnalysis::with(['attempts', 'elementResults'])->find($id);
        if (! $a) {
            return $this->error('Analysis not found', 'ANALYSIS_NOT_FOUND', 404);
        }
        return $this->success(new ActiveAnalysisDetailResource($a), 'Analysis retrieved');
    }

    /* ============================================================
     * WRITE — 8 action endpoints
     * ============================================================ */

    public function hold(PutOnHoldRequest $request, string $id)
    {
        $a = $this->resolveOr404($id);
        if (! $a) return $this->notFound();

        $r = $this->service->putOnHold($a, $request->validated()['reason']);
        return $this->respondAction($r, 'Analysis on hold');
    }

    public function repeat(RequestRepeatAnalysisRequest $request, string $id)
    {
        $a = $this->resolveOr404($id);
        if (! $a) return $this->notFound();

        $r = $this->service->requestRepeat($a, $request->validated()['reason']);
        return $this->respondAction($r, 'Repeat / retest requested');
    }

    public function cancel(CancelAnalysisRequest $request, string $id)
    {
        $a = $this->resolveOr404($id);
        if (! $a) return $this->notFound();

        $r = $this->service->cancel($a, $request->validated()['reason']);
        return $this->respondAction($r, 'Analysis cancelled');
    }

    public function releaseLoading(ReleaseLoadingRequest $request, string $id)
    {
        $a = $this->resolveOr404($id);
        if (! $a) return $this->notFound();

        $r = $this->service->releaseLoading($a, $request->validated()['reason'] ?? null);
        return $this->respondAction($r, 'Loading released');
    }

    public function rejectLoading(RejectLoadingRequest $request, string $id)
    {
        $a = $this->resolveOr404($id);
        if (! $a) return $this->notFound();

        $r = $this->service->rejectLoading($a, $request->validated()['reason']);
        return $this->respondAction($r, 'Loading rejected / trailer blocked');
    }

    public function openFaultCase(OpenFaultCaseRequest $request, string $id)
    {
        $a = $this->resolveOr404($id);
        if (! $a) return $this->notFound();

        $data = $request->validated();
        $r = $this->service->openFaultCase(
            $a,
            $data['reason'],
            $data['affected_device_bmk'] ?? null,
            $data['affected_element'] ?? null
        );
        return $this->respondAction($r, 'Fault case opened');
    }

    public function repeatMeasurement(RepeatMeasurementRequest $request, string $id)
    {
        $a = $this->resolveOr404($id);
        if (! $a) return $this->notFound();

        $r = $this->service->repeatMeasurement($a, $request->validated()['reason']);
        return $this->respondAction($r, 'Measurement repeat queued');
    }

    public function manualApproval(ManualFunctionalApprovalRequest $request, string $id)
    {
        $a = $this->resolveOr404($id);
        if (! $a) return $this->notFound();

        $data = $request->validated();
        $r = $this->service->manualFunctionalApproval(
            $a,
            $data['reason'],
            $data['justification_category'] ?? null
        );
        return $this->respondAction($r, 'Analysis manually approved');
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    protected function resolveOr404(string $id): ?ActiveAnalysis
    {
        return ActiveAnalysis::with(['attempts', 'elementResults'])->find($id);
    }

    protected function notFound()
    {
        return $this->error('Analysis not found', 'ANALYSIS_NOT_FOUND', 404);
    }

    protected function respondAction(array $r, string $okMessage)
    {
        if (! ($r['ok'] ?? false)) {
            $code = $r['code'] ?? 'INVALID_STATE_TRANSITION';
            return $this->error(
                $this->codeMessage($code),
                $code,
                409
            );
        }
        $a = $r['analysis']->load(['attempts', 'elementResults']);
        return $this->success(new ActiveAnalysisDetailResource($a), $okMessage);
    }

    protected function codeMessage(string $code): string
    {
        return match ($code) {
            'INVALID_STATE_TRANSITION' => 'This action is not allowed from the current analysis state.',
            'ANALYSIS_MAX_ATTEMPTS_REACHED' => 'Maximum allowed attempts reached; open a fault case instead.',
            'ANALYSIS_TECHNICAL_REPEAT_USED' => 'The single technical repeat has already been used; open a fault case.',
            default => 'Action not allowed.',
        };
    }

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $query->where(function ($w) use ($like) {
                $w->where('display_no', 'like', $like)
                    ->orWhere('order_no', 'like', $like)
                    ->orWhere('sap_order_no', 'like', $like)
                    ->orWhere('trailer_label', 'like', $like)
                    ->orWhere('station_name', 'like', $like)
                    ->orWhere('device_name', 'like', $like)
                    ->orWhere('device_bmk', 'like', $like);
            });
        }

        if (! empty($filters['analysis_type'])) {
            $query->where('analysis_type', $filters['analysis_type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['required_action'])) {
            $query->where('required_action', $filters['required_action']);
        }
        if (! empty($filters['sampling_trigger'])) {
            $query->where('sampling_trigger', $filters['sampling_trigger']);
        }
        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }
        if (! empty($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }
        if (! empty($filters['bay_line_id'])) {
            $query->where('bay_line_id', $filters['bay_line_id']);
        }

        // The page is "open analyses only" by default per V1.4 §4.1.
        // FE can opt into showing terminal rows by passing
        // include_closed=true.
        $includeClosed = filter_var($filters['include_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $includeClosed) {
            $query->whereIn('status', ActiveAnalysisStatus::openStatuses());
        }
    }

    protected function summary(): array
    {
        $base = ActiveAnalysis::query();

        $openCount = (clone $base)->whereIn('status', ActiveAnalysisStatus::openStatuses())->count();
        $actionRequired = (clone $base)->whereNotNull('required_action')->count();
        $runningOrWaiting = (clone $base)->whereIn('status', [
            ActiveAnalysisStatus::QUEUED,
            ActiveAnalysisStatus::PREPARING,
            ActiveAnalysisStatus::PURGING,
            ActiveAnalysisStatus::RUNNING,
            ActiveAnalysisStatus::WAITING_RESULT,
        ])->count();
        $nokOrInvalid = (clone $base)->whereIn('status', [
            ActiveAnalysisStatus::NOK,
            ActiveAnalysisStatus::INVALID,
            ActiveAnalysisStatus::FAILED,
        ])->count();
        $blockingOps = $nokOrInvalid; // simple alias for the demo

        return [
            'openAnalyses' => $openCount,
            'actionRequired' => $actionRequired,
            'runningOrWaiting' => $runningOrWaiting,
            'nokOrInvalid' => $nokOrInvalid,
            'blockingOperations' => $blockingOps,
        ];
    }
}
