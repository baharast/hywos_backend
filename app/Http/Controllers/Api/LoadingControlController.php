<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\LoadingControl\AddLoadingNoteRequest;
use App\Http\Resources\ActiveLoadingListItemResource;
use App\Http\Resources\LoadingDetailResource;
use App\Http\Resources\SelectedLoadingDetailsResource;
use App\Http\Resources\StationViewItemResource;
use App\Models\AuditLog;
use App\Models\BayLine;
use App\Models\EventLog;
use App\Models\LoadingOperation;
use App\Services\ApiResponse;
use App\Services\Loading\LoadingControlService;
use Illuminate\Http\Request;

/**
 * Operational dashboard for Loading Control. Aligned with FillTrack Loading
 * Control UX Spec V3.2.
 *
 * Read-mostly. The only write is `addNote`. V3.2 §1.2 + §13 explicitly forbid
 * any other write — no start/stop/pause/ESD, no quality decisions, no
 * document print/reprint, no order assignment, no PLC commands.
 */
class LoadingControlController extends ApiController
{
    public function __construct(protected LoadingControlService $service)
    {
    }

    /**
     * Station View — V3.2 §5 station board feeding the 3×2 Bay Line cards.
     * Always returns every configured bay line (even faulty / offline) so
     * the FE never has to hide a card.
     */
    public function stationView(Request $request)
    {
        $items = $this->service->stationViewItems($request->only([
            'site_id', 'plant_area_id', 'bay_status',
        ]));

        $summary = $this->service->summary();

        $lastUpdated = LoadingOperation::query()->max('updated_at')
            ?? BayLine::query()->max('updated_at');

        return ApiResponse::list(
            StationViewItemResource::collection($items),
            null,
            $summary,
            $lastUpdated,
            'Station view retrieved'
        );
    }

    /**
     * Active Loadings — V3.2 §6 process-level table. Primary filters are
     * `bay_line`, `loading_state`, `analysis_state`, `issue_type` per §6.4.
     * The legacy `has_clarification` / `has_alarm` boolean filters are
     * replaced by the `issue_type` categorical.
     */
    public function activeLoadings(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $filters = $request->only([
            'search',
            'bay_line',
            'loading_state',
            'analysis_state',
            'issue_type',
            'started_from',
            'started_to',
            'sort',
            'include_terminal',
        ]);

        $paginator = $this->service->activeLoadingsQuery($filters)->paginate($perPage);

        $rows = ActiveLoadingListItemResource::collection($paginator->items());

        $summary = $this->service->summary();
        $lastUpdated = LoadingOperation::query()->max('updated_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $summary,
            $lastUpdated,
            'Active loadings retrieved'
        );
    }

    /**
     * GET `/api/loading-control/loadings/{id}` — returns either the full
     * deep-review shape (V3.2 §8) or the lighter inline selected-panel
     * shape (V3.2 §7) when `?view=selected`.
     *
     * Same URL, two response shapes so the FE doesn't need a second
     * endpoint for the inline panel.
     */
    public function show(Request $request, $id)
    {
        $loading = LoadingOperation::find($id);
        if (! $loading) {
            return $this->error('Loading not found', 'LOADING_NOT_FOUND', 404);
        }

        $loading = $this->service->loadingDetail($loading);

        if ($request->query('view') === 'selected') {
            return $this->success(
                new SelectedLoadingDetailsResource($loading),
                'Selected loading details retrieved'
            );
        }

        return $this->success(new LoadingDetailResource($loading), 'Loading retrieved');
    }

    public function events(Request $request, $id)
    {
        $loading = LoadingOperation::find($id);
        if (! $loading) {
            return $this->error('Loading not found', 'LOADING_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);

        $entityType = $loading->getMorphClass();

        $paginator = EventLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $loading->id)
            ->orderByDesc('occurred_at')
            ->paginate($perPage);

        $lastUpdated = EventLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $loading->id)
            ->max('occurred_at');

        return ApiResponse::list(
            $paginator->items(),
            $paginator,
            null,
            $lastUpdated,
            'Loading events retrieved'
        );
    }

    public function audit(Request $request, $id)
    {
        $loading = LoadingOperation::find($id);
        if (! $loading) {
            return $this->error('Loading not found', 'LOADING_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);

        $entityType = $loading->getMorphClass();

        $paginator = AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $loading->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $lastUpdated = AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $loading->id)
            ->max('created_at');

        return ApiResponse::list(
            $paginator->items(),
            $paginator,
            null,
            $lastUpdated,
            'Loading audit retrieved'
        );
    }

    public function addNote(AddLoadingNoteRequest $request, $id)
    {
        $loading = LoadingOperation::find($id);
        if (! $loading) {
            return $this->error('Loading not found', 'LOADING_NOT_FOUND', 404);
        }

        $loading = $this->service->addNote($loading, (string) $request->input('note'));

        return $this->success(
            new LoadingDetailResource($this->service->loadingDetail($loading)),
            'Note added'
        );
    }
}
