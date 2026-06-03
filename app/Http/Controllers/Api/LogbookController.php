<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Logbook\AddFollowUpRequest;
use App\Http\Requests\Logbook\CorrectLogbookEntryRequest;
use App\Http\Requests\Logbook\CreateLogbookEntryRequest;
use App\Http\Requests\Logbook\MarkFollowUpDoneRequest;
use App\Http\Resources\LogbookEntryResource;
use App\Models\LogbookEntry;
use App\Services\ApiResponse;
use App\Services\AlarmsEvents\LogbookService;
use Illuminate\Http\Request;

/**
 * V1 §7.5 — Safety & Operations Logbook.
 *
 * Read + the 4 write actions allowed by spec §7.5:
 *   - POST /                                create entry
 *   - POST /{id}/follow-up                  add follow-up (owner + due_at)
 *   - POST /{id}/follow-up/done             mark follow-up done
 *   - POST /{id}/correct                    correct entry (snapshots old text)
 *
 * Per §7.5: no silent overwrite of logbook text. Correction snapshots
 * the previous values into the logbook_entry_corrections sibling table
 * and emits a LOGBOOK_ENTRY_CORRECTED audit row with full before/after.
 */
class LogbookController extends ApiController
{
    public function __construct(protected LogbookService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $paginator = $this->service->listForTab($request->all(), $perPage);
        $rows = LogbookEntryResource::collection($paginator->items());

        $lastUpdated = LogbookEntry::query()->max('updated_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->service->buildSummary(),
            $lastUpdated,
            __('logbook.messages.entries_retrieved')
        );
    }

    public function show(string $id)
    {
        $entry = LogbookEntry::query()->find($id);
        if (! $entry) {
            return $this->error(__('logbook.messages.entry_not_found'), 'LOGBOOK_ENTRY_NOT_FOUND', 404);
        }

        $corrections = $this->service->correctionsFor($entry);

        $resource = (new LogbookEntryResource($entry))
            ->additional(['corrections' => $corrections]);

        return $this->success($resource, __('logbook.messages.entry_retrieved'));
    }

    public function store(CreateLogbookEntryRequest $request)
    {
        $entry = $this->service->create($request->validated());

        return $this->success(
            new LogbookEntryResource($entry),
            __('logbook.messages.entry_created'),
            201
        );
    }

    public function addFollowUp(AddFollowUpRequest $request, string $id)
    {
        $entry = LogbookEntry::query()->find($id);
        if (! $entry) {
            return $this->error(__('logbook.messages.entry_not_found'), 'LOGBOOK_ENTRY_NOT_FOUND', 404);
        }

        try {
            $entry = $this->service->addFollowUp($entry, $request->validated());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'LOGBOOK_FOLLOWUP_ALREADY_OPEN', 409);
        }

        return $this->success(new LogbookEntryResource($entry), __('logbook.messages.follow_up_added'));
    }

    public function markFollowUpDone(MarkFollowUpDoneRequest $request, string $id)
    {
        $entry = LogbookEntry::query()->find($id);
        if (! $entry) {
            return $this->error(__('logbook.messages.entry_not_found'), 'LOGBOOK_ENTRY_NOT_FOUND', 404);
        }

        try {
            $entry = $this->service->markFollowUpDone($entry, $request->validated());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'LOGBOOK_FOLLOWUP_INVALID_STATE', 409);
        }

        return $this->success(new LogbookEntryResource($entry), __('logbook.messages.follow_up_done'));
    }

    public function correct(CorrectLogbookEntryRequest $request, string $id)
    {
        $entry = LogbookEntry::query()->find($id);
        if (! $entry) {
            return $this->error(__('logbook.messages.entry_not_found'), 'LOGBOOK_ENTRY_NOT_FOUND', 404);
        }

        try {
            $entry = $this->service->correct($entry, $request->validated());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'LOGBOOK_CORRECTION_NO_CHANGE', 422);
        }

        return $this->success(new LogbookEntryResource($entry), __('logbook.messages.entry_corrected'));
    }
}
