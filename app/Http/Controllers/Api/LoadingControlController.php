<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\LoadingControl\AddLoadingNoteRequest;
use App\Http\Resources\ActiveLoadingListItemResource;
use App\Http\Resources\LoadingDetailResource;
use App\Http\Resources\StationViewItemResource;
use App\Models\AuditLog;
use App\Models\BayLine;
use App\Models\EventLog;
use App\Models\LoadingOperation;
use App\Services\ApiResponse;
use App\Services\Loading\LoadingControlService;
use Illuminate\Http\Request;

/**
 * Operational dashboard for active loadings.
 *
 * IMPORTANT: this controller is read-only with one write endpoint (addNote).
 * Creating / starting / completing loadings is OUT OF SCOPE here and will land
 * with the Orders / PlantVisit / device-gateway modules. The dashboard cannot
 * mint loadings on its own.
 */
class LoadingControlController extends ApiController
{
    public function __construct(protected LoadingControlService $service)
    {
    }

    public function stationView(Request $request)
    {
        $items = $this->service->stationViewItems($request->only([
            'site_id', 'plant_area_id', 'station_status',
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

    public function activeLoadings(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $filters = $request->only([
            'search', 'station_id', 'loading_status', 'analysis_status',
            'has_clarification', 'has_alarm', 'started_from', 'started_to',
            'sort', 'include_terminal',
        ]);

        $paginator = $this->service->activeLoadingsQuery($filters)->paginate($perPage);

        $rows = ActiveLoadingListItemResource::collection($paginator->items());

        $summary = $this->service->summary();
        $lastUpdated = LoadingOperation::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'Active loadings retrieved');
    }

    public function show($id)
    {
        $loading = LoadingOperation::find($id);
        if (! $loading) {
            return $this->error('Loading not found', 'LOADING_NOT_FOUND', 404);
        }

        $loading = $this->service->loadingDetail($loading);

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

        return ApiResponse::list($paginator->items(), $paginator, null, $lastUpdated, 'Loading events retrieved');
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

        return ApiResponse::list($paginator->items(), $paginator, null, $lastUpdated, 'Loading audit retrieved');
    }

    public function addNote(AddLoadingNoteRequest $request, $id)
    {
        $loading = LoadingOperation::find($id);
        if (! $loading) {
            return $this->error('Loading not found', 'LOADING_NOT_FOUND', 404);
        }

        $loading = $this->service->addNote($loading, (string) $request->input('note'));

        return $this->success(new LoadingDetailResource($this->service->loadingDetail($loading)), 'Note added');
    }
}
