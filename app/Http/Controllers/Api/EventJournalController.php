<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EventJournalResource;
use App\Models\EventLog;
use App\Services\ApiResponse;
use App\Services\AlarmsEvents\EventJournalService;
use Illuminate\Http\Request;

/**
 * V1 §7.4 — Event Journal (default subview).
 *
 * READ-ONLY surface over the existing event_logs table. Security
 * events (event_category='security') are excluded — they live in the
 * restricted Security Events subview behind separate permission.
 *
 * Per V1 §7.4 forbidden actions: no editing, deleting, overwriting
 * or manually reordering. Writes are emitted by other modules via
 * App\Services\Events\EventLogger.
 */
class EventJournalController extends ApiController
{
    public function __construct(protected EventJournalService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $paginator = $this->service->listForTab($request->all(), $perPage);
        $rows = EventJournalResource::collection($paginator->items());

        $lastUpdated = EventLog::query()->max('occurred_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->service->buildSummary(),
            $lastUpdated,
            __('alarms.messages.journal_retrieved')
        );
    }

    public function show(string $id)
    {
        if (! ctype_digit($id)) {
            return $this->error(__('alarms.messages.event_not_found'), 'EVENT_NOT_FOUND', 404);
        }
        $row = EventLog::query()->find((int) $id);
        if (! $row) {
            return $this->error(__('alarms.messages.event_not_found'), 'EVENT_NOT_FOUND', 404);
        }
        // Block direct access to security events from this controller —
        // they live behind a separate permission boundary in the
        // Security Events subview.
        if ($row->event_category === \App\Enums\EventCategory::SECURITY) {
            return $this->error(__('alarms.messages.event_not_found'), 'EVENT_NOT_FOUND', 404);
        }

        $resource = (new EventJournalResource($row))
            ->additional(['details' => $row->details]);

        return $this->success($resource, __('alarms.messages.event_detail'));
    }
}
