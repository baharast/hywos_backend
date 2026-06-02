<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\BlockingImpact;
use App\Enums\ClarificationSeverity;
use App\Enums\ClarificationStatus;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Http\Requests\ClarificationCase\AcknowledgeClarificationCaseRequest;
use App\Http\Requests\ClarificationCase\AssignClarificationCaseRequest;
use App\Http\Requests\ClarificationCase\CloseClarificationCaseRequest;
use App\Http\Requests\ClarificationCase\CreateClarificationCaseRequest;
use App\Http\Requests\ClarificationCase\MoveToWaitingForOwnerRequest;
use App\Http\Requests\ClarificationCase\ResolveClarificationCaseRequest;
use App\Http\Resources\ClarificationCaseResource;
use App\Models\ClarificationCase;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClarificationCaseController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = ClarificationCase::query();
        $this->applyFilters($query, $request->all());

        $sort = (string) $request->query('sort', '-opened_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['opened_at', 'created_at', 'updated_at', 'severity', 'status', 'case_no'];
        if (! in_array($column, $allowed, true)) {
            $column = 'opened_at';
            $direction = 'desc';
        }

        $paginator = $query->orderBy($column, $direction)->paginate($perPage);
        $rows = ClarificationCaseResource::collection($paginator->items());

        $lastUpdated = ClarificationCase::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $this->summary(), $lastUpdated, __('clarification.messages.cases_retrieved'));
    }

    public function show($id)
    {
        $case = ClarificationCase::find($id);
        if (! $case) {
            return $this->error(__('clarification.messages.case_not_found'), 'CLARIFICATION_NOT_FOUND', 404);
        }

        return $this->success(new ClarificationCaseResource($case), __('clarification.messages.case_retrieved'));
    }

    public function store(CreateClarificationCaseRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $data = $request->validated();
        $severity = $data['severity'] ?? ClarificationSeverity::NORMAL;

        return DB::transaction(function () use ($data, $severity, $audit, $events) {
            // TODO: re-enable when auth lands — populate opened_by_user_id / created_by_user_id from request()->user().
            $case = ClarificationCase::create($data);

            $audit->record(
                $case,
                AuditAction::CLARIFICATION_CREATED,
                null,
                $audit->snapshotModel($case),
                $case->description,
                $case->reason_code
            );

            $eventSeverity = $severity === ClarificationSeverity::CRITICAL
                ? EventSeverity::CRITICAL
                : EventSeverity::WARNING;

            $events->record(
                'clarification.created',
                $case,
                "Clarification {$case->case_no} opened ({$case->source})",
                [
                    'source' => $case->source,
                    'category' => $case->category,
                    'entity_type' => $case->entity_type,
                    'entity_id' => $case->entity_id,
                    'severity' => $severity,
                    'blocking_impact' => $case->blocking_impact,
                    'is_blocking' => (bool) $case->is_blocking,
                ],
                EventCategory::OPERATIONS,
                $eventSeverity
            );

            return ApiResponse::success(new ClarificationCaseResource($case->fresh()), __('clarification.messages.case_created'), 201);
        });
    }

    /* ============================================================
     * Lifecycle actions (V1.3 §4.1).
     *
     * Transition matrix:
     *   open                → in_progress | waiting_for_owner | resolved
     *   in_progress         ↔ waiting_for_owner
     *   in_progress         → resolved
     *   waiting_for_owner   → resolved
     *   resolved            → closed (sticky — no further transitions)
     *
     * V1.3 has NO `cancelled` state. "Closing without resolution" is a
     * `closed` case whose `resolution_note` carries the explanation.
     *
     * Every action wraps in DB::transaction and emits one audit + one
     * event row. Auth is disabled in dev so `actor_user_id` is null.
     * ============================================================
     */

    /**
     * Acknowledge — `open → in_progress`. Captures `acknowledged_at`.
     *
     * No `reason` required — acknowledgement is a flow step, not a
     * critical state change. Re-acknowledging (status already past `open`)
     * returns 409 `ALREADY_ACKNOWLEDGED`.
     */
    public function acknowledge(
        AcknowledgeClarificationCaseRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $case = ClarificationCase::find($id);
        if (! $case) {
            return $this->error(__('clarification.messages.case_not_found'), 'CLARIFICATION_NOT_FOUND', 404);
        }

        if ($case->status !== ClarificationStatus::OPEN) {
            return $this->error(
                "Case is already past 'open' (current: {$case->status}).",
                'ALREADY_ACKNOWLEDGED',
                409
            );
        }

        return DB::transaction(function () use ($case, $audit, $events) {
            $old = $audit->snapshotModel($case);

            $case->status = ClarificationStatus::IN_PROGRESS;
            $case->acknowledged_at = now();
            // acknowledged_by_user_id stays null in dev — auth disabled.
            $case->save();

            $fresh = $case->fresh();
            $audit->record(
                $case,
                AuditAction::CLARIFICATION_ACKNOWLEDGED,
                $old,
                $audit->snapshotModel($fresh),
                null,
                null
            );
            $events->record(
                'clarification.acknowledged',
                $case,
                "Clarification {$case->case_no} acknowledged",
                [
                    'previous_status' => ClarificationStatus::OPEN,
                    'new_status' => ClarificationStatus::IN_PROGRESS,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                new ClarificationCaseResource($fresh),
                __('clarification.messages.case_acknowledged')
            );
        });
    }

    /**
     * Assign — set / re-assign `owner_role` (+ optional
     * `assigned_to_user_id`). Allowed from `open` / `in_progress` /
     * `waiting_for_owner`. Re-assigns overwrite previous values; audit
     * captures before/after.
     *
     * Re-routing is dispatcher work — no `reason` required.
     */
    public function assign(
        AssignClarificationCaseRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $case = ClarificationCase::find($id);
        if (! $case) {
            return $this->error(__('clarification.messages.case_not_found'), 'CLARIFICATION_NOT_FOUND', 404);
        }

        if (! in_array($case->status, [
            ClarificationStatus::OPEN,
            ClarificationStatus::IN_PROGRESS,
            ClarificationStatus::WAITING_FOR_OWNER,
        ], true)) {
            return $this->error(
                "Cannot assign a clarification in status '{$case->status}'.",
                'INVALID_STATE_TRANSITION',
                409
            );
        }

        $data = $request->validated();

        return DB::transaction(function () use ($case, $data, $audit, $events) {
            $old = $audit->snapshotModel($case);
            $previousOwnerRole = $case->owner_role;
            $previousAssignedTo = $case->assigned_to_user_id;

            $case->owner_role = $data['owner_role'];
            $case->assigned_to_user_id = $data['assigned_to_user_id'] ?? null;
            $case->save();

            $fresh = $case->fresh();
            $audit->record(
                $case,
                AuditAction::CLARIFICATION_ASSIGNED,
                $old,
                $audit->snapshotModel($fresh),
                null,
                null
            );
            $events->record(
                'clarification.assigned',
                $case,
                "Clarification {$case->case_no} assigned to {$case->owner_role}",
                [
                    'previous_owner_role' => $previousOwnerRole,
                    'new_owner_role' => $case->owner_role,
                    'previous_assigned_to_user_id' => $previousAssignedTo,
                    'new_assigned_to_user_id' => $case->assigned_to_user_id,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                new ClarificationCaseResource($fresh),
                __('clarification.messages.case_assigned')
            );
        });
    }

    /**
     * Move to `waiting_for_owner` (V1.3 §4.1 transition: `open |
     * in_progress → waiting_for_owner`).
     *
     * Used when the case depends on another role/module (IT, Analysis,
     * Documents, Dispatcher) and the current handler is parking it.
     * `reason` is required — this is the auditable moment that explains
     * WHY the case is parked.
     *
     * Reuses `CLARIFICATION_ASSIGNED` audit action because the wait state
     * is functionally an assignment to a role, not a separate audit kind.
     */
    public function moveToWaitingForOwner(
        MoveToWaitingForOwnerRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $case = ClarificationCase::find($id);
        if (! $case) {
            return $this->error(__('clarification.messages.case_not_found'), 'CLARIFICATION_NOT_FOUND', 404);
        }

        if (! in_array($case->status, [
            ClarificationStatus::OPEN,
            ClarificationStatus::IN_PROGRESS,
        ], true)) {
            return $this->error(
                "Cannot move to 'waiting_for_owner' from status '{$case->status}'.",
                'INVALID_STATE_TRANSITION',
                409
            );
        }

        $reason = $request->validated()['reason'];

        return DB::transaction(function () use ($case, $reason, $audit, $events) {
            $old = $audit->snapshotModel($case);
            $previousStatus = $case->status;

            // Stamp the reason onto notes with a timestamp prefix — the
            // notes column is the auditable narrative for the case.
            $stamp = now()->toIso8601String();
            $existing = $case->notes ? rtrim($case->notes) . "\n---\n" : '';
            $case->notes = $existing . "[{$stamp}] [WAITING_FOR_OWNER] {$reason}";

            $case->status = ClarificationStatus::WAITING_FOR_OWNER;
            $case->save();

            $fresh = $case->fresh();
            $audit->record(
                $case,
                AuditAction::CLARIFICATION_ASSIGNED,
                $old,
                $audit->snapshotModel($fresh),
                $reason,
                null
            );
            $events->record(
                'clarification.waiting_for_owner',
                $case,
                "Clarification {$case->case_no} moved to waiting for owner ({$case->owner_role})",
                [
                    'previous_status' => $previousStatus,
                    'new_status' => ClarificationStatus::WAITING_FOR_OWNER,
                    'reason' => $reason,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(
                new ClarificationCaseResource($fresh),
                __('clarification.messages.case_waiting_for_owner')
            );
        });
    }

    /**
     * Resolve — `open | in_progress | waiting_for_owner → resolved`.
     * Stamps `resolved_at` and stores `resolution_note` on the row.
     *
     * `resolution_note` is required (≥ 3, ≤ 5000) — this is the auditable
     * explanation. The blocking_impact is cleared back to `none` so any
     * downstream UI gating relaxes.
     */
    public function resolve(
        ResolveClarificationCaseRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $case = ClarificationCase::find($id);
        if (! $case) {
            return $this->error(__('clarification.messages.case_not_found'), 'CLARIFICATION_NOT_FOUND', 404);
        }

        if (! in_array($case->status, [
            ClarificationStatus::OPEN,
            ClarificationStatus::IN_PROGRESS,
            ClarificationStatus::WAITING_FOR_OWNER,
        ], true)) {
            return $this->error(
                "Cannot resolve a clarification in status '{$case->status}'.",
                'INVALID_STATE_TRANSITION',
                409
            );
        }

        $note = $request->validated()['resolution_note'];

        return DB::transaction(function () use ($case, $note, $audit, $events) {
            $old = $audit->snapshotModel($case);
            $previousStatus = $case->status;

            $case->status = ClarificationStatus::RESOLVED;
            $case->resolved_at = now();
            $case->resolution_note = $note;
            // Clear blocking_impact so downstream UI gating relaxes; the
            // model's updating hook keeps `is_blocking` in sync.
            $case->blocking_impact = BlockingImpact::NONE;
            $case->save();

            $fresh = $case->fresh();
            $audit->record(
                $case,
                AuditAction::CLARIFICATION_RESOLVED,
                $old,
                $audit->snapshotModel($fresh),
                $note,
                null
            );
            $events->record(
                'clarification.resolved',
                $case,
                "Clarification {$case->case_no} resolved",
                [
                    'previous_status' => $previousStatus,
                    'new_status' => ClarificationStatus::RESOLVED,
                    'resolution_note' => $note,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                new ClarificationCaseResource($fresh),
                __('clarification.messages.case_resolved')
            );
        });
    }

    /**
     * Close — `resolved → closed`. Sticky (no further transitions).
     *
     * No reason required — `resolved` already captured the explanation in
     * `resolution_note`. Allowed only from `resolved`.
     */
    public function close(
        CloseClarificationCaseRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $case = ClarificationCase::find($id);
        if (! $case) {
            return $this->error(__('clarification.messages.case_not_found'), 'CLARIFICATION_NOT_FOUND', 404);
        }

        if ($case->status !== ClarificationStatus::RESOLVED) {
            return $this->error(
                "Cannot close a clarification in status '{$case->status}'. Resolve it first.",
                'INVALID_STATE_TRANSITION',
                409
            );
        }

        return DB::transaction(function () use ($case, $audit, $events) {
            $old = $audit->snapshotModel($case);

            $case->status = ClarificationStatus::CLOSED;
            $case->closed_at = now();
            // closed_by_user_id stays null in dev — auth disabled.
            $case->save();

            $fresh = $case->fresh();
            $audit->record(
                $case,
                AuditAction::CLARIFICATION_CLOSED,
                $old,
                $audit->snapshotModel($fresh),
                null,
                null
            );
            $events->record(
                'clarification.closed',
                $case,
                "Clarification {$case->case_no} closed",
                [
                    'previous_status' => ClarificationStatus::RESOLVED,
                    'new_status' => ClarificationStatus::CLOSED,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                new ClarificationCaseResource($fresh),
                __('clarification.messages.case_closed')
            );
        });
    }

    // ---------- helpers ----------

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('case_no', 'like', $search)
                    ->orWhere('entity_label', 'like', $search);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (! empty($filters['blocking_impact'])) {
            $query->where('blocking_impact', $filters['blocking_impact']);
        }
        if (! empty($filters['owner_role'])) {
            $query->where('owner_role', $filters['owner_role']);
        }

        if (array_key_exists('is_blocking', $filters) && $filters['is_blocking'] !== null && $filters['is_blocking'] !== '') {
            $query->where('is_blocking', filter_var($filters['is_blocking'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    protected function summary(): array
    {
        $base = ClarificationCase::query();
        $openStatuses = ClarificationStatus::openStatuses();

        $totalOpen = (clone $base)
            ->whereIn('status', $openStatuses)
            ->count();

        $criticalOpen = (clone $base)
            ->whereIn('status', $openStatuses)
            ->where('severity', ClarificationSeverity::CRITICAL)
            ->count();

        // V1.3 §6.3 "Blocking loading" + "Blocking exit" summary cards rely on
        // blocking_impact, so we count the same way: any non-`none` impact
        // among open cases.
        $blockingOpen = (clone $base)
            ->whereIn('status', $openStatuses)
            ->where(function ($w) {
                $w->where('is_blocking', true)
                    ->orWhere(function ($x) {
                        $x->whereNotNull('blocking_impact')
                            ->where('blocking_impact', '!=', BlockingImpact::NONE);
                    });
            })
            ->count();

        $byOwnerRole = (clone $base)
            ->whereIn('status', $openStatuses)
            ->selectRaw('owner_role, COUNT(*) as c')
            ->groupBy('owner_role')
            ->pluck('c', 'owner_role')
            ->toArray();

        // V1.3 §6.3 compact summary line — surface per-impact counts so the
        // FE can render "Blocking loading", "Blocking exit", etc. without a
        // second round trip.
        $byBlockingImpact = (clone $base)
            ->whereIn('status', $openStatuses)
            ->selectRaw('blocking_impact, COUNT(*) as c')
            ->groupBy('blocking_impact')
            ->pluck('c', 'blocking_impact')
            ->toArray();

        return [
            'totalOpen' => $totalOpen,
            'criticalOpen' => $criticalOpen,
            'blockingOpen' => $blockingOpen,
            'byOwnerRole' => $byOwnerRole,
            'byBlockingImpact' => $byBlockingImpact,
        ];
    }
}
