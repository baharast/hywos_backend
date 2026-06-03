<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\AuthMediumStatus;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\InspectionState;
use App\Enums\TrailerStatus;
use App\Http\Requests\Trailer\BlockTrailerRequest;
use App\Http\Requests\Trailer\StoreTrailerRequest;
use App\Http\Requests\Trailer\UnblockTrailerRequest;
use App\Http\Requests\Trailer\UpdateTrailerRequest;
use App\Http\Resources\AuthMediumResource;
use App\Http\Resources\TrailerResource;
use App\Models\AuditLog;
use App\Models\EventLog;
use App\Models\LoadingOperation;
use App\Models\LoadingOrder;
use App\Models\Trailer;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrailerController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = Trailer::query()->with(['carrier', 'customer', 'authMedia', 'currentParking']);
        $this->applyFilters($query, $request->all());

        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['trailer_code', 'plate', 'inspection_expiry_date', 'updated_at', 'created_at', 'status'];
        if (! in_array($column, $allowed, true)) {
            $column = 'updated_at';
            $direction = 'desc';
        }
        $paginator = $query->orderBy($column, $direction)->paginate($perPage);

        // Pre-enrich the paginated rows with active-order + last-loaded
        // metadata. Two extra queries total instead of one per row.
        $this->enrichTrailers($paginator->items());

        // `current_context_kind` filter runs in PHP because it's a
        // derived field. Done AFTER pagination so we don't desync
        // meta.total — see method doc for the trade-off.
        $items = $this->applyDerivedContextKindFilter(
            $paginator->items(),
            (string) $request->query('current_context_kind', '')
        );

        $rows = TrailerResource::collection($items);

        $summary = $this->summary();
        $lastUpdated = Trailer::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, __('vehicles.messages.trailers_retrieved'));
    }

    public function store(StoreTrailerRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $data = $request->validated();

        // trailer_code is always system-minted: StoreTrailerRequest strips
        // any client-supplied value, so the next monotonic TR-NNNN is
        // generated here. The empty() check is a defensive backstop.
        if (empty($data['trailer_code'])) {
            $data['trailer_code'] = $this->nextTrailerCode();
        }

        return DB::transaction(function () use ($data, $audit, $events) {
            $trailer = Trailer::create($data);

            $audit->record(
                $trailer,
                AuditAction::TRAILER_CREATED,
                null,
                $audit->snapshotModel($trailer),
                null,
                null
            );

            $events->record(
                'trailer.created',
                $trailer,
                "Trailer {$trailer->trailer_code} created",
                ['trailer_code' => $trailer->trailer_code],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->created(
                new TrailerResource($trailer->fresh()->load(['carrier', 'customer', 'authMedia'])),
                __('vehicles.messages.trailer_created')
            );
        });
    }

    public function show($id)
    {
        $trailer = Trailer::with(['carrier', 'customer', 'authMedia'])->find($id);
        if (! $trailer) {
            return $this->error(__('vehicles.messages.trailer_not_found'), 'TRAILER_NOT_FOUND', 404);
        }

        return $this->success(new TrailerResource($trailer), __('vehicles.messages.trailer_retrieved'));
    }

    public function update(UpdateTrailerRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error(__('vehicles.messages.trailer_not_found'), 'TRAILER_NOT_FOUND', 404);
        }

        $data = $request->validated();

        return DB::transaction(function () use ($trailer, $data, $audit, $events) {
            $old = $audit->snapshotModel($trailer);

            $trailer->update($data);

            $audit->record(
                $trailer,
                AuditAction::TRAILER_UPDATED,
                $old,
                $audit->snapshotModel($trailer->fresh()),
                null,
                null
            );

            $events->record(
                'trailer.updated',
                $trailer,
                "Trailer {$trailer->trailer_code} updated",
                null,
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                new TrailerResource($trailer->fresh()->load(['carrier', 'customer', 'authMedia'])),
                __('vehicles.messages.trailer_updated')
            );
        });
    }

    public function block(BlockTrailerRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error(__('vehicles.messages.trailer_not_found'), 'TRAILER_NOT_FOUND', 404);
        }
        if ($trailer->status === TrailerStatus::BLOCKED) {
            return $this->error(__('vehicles.messages.trailer_already_blocked'), 'ALREADY_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($trailer, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($trailer);

            // TODO: re-enable when auth lands — set blocked_by_user_id from request()->user().
            // Block does NOT silently cancel ongoing loadings; spec out-of-scope here.
            $trailer->update([
                'status' => TrailerStatus::BLOCKED,
                'block_reason' => $reason,
                'blocked_at' => now(),
            ]);

            $audit->record(
                $trailer,
                AuditAction::TRAILER_BLOCKED,
                $old,
                $audit->snapshotModel($trailer->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'trailer.blocked',
                $trailer,
                "Trailer {$trailer->trailer_code} blocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(
                new TrailerResource($trailer->fresh()->load(['carrier', 'customer', 'authMedia'])),
                __('vehicles.messages.trailer_blocked')
            );
        });
    }

    public function unblock(UnblockTrailerRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error(__('vehicles.messages.trailer_not_found'), 'TRAILER_NOT_FOUND', 404);
        }
        if ($trailer->status !== TrailerStatus::BLOCKED) {
            return $this->error(__('vehicles.messages.trailer_not_blocked'), 'NOT_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($trailer, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($trailer);

            $trailer->update([
                'status' => TrailerStatus::ACTIVE,
                'block_reason' => null,
                'blocked_at' => null,
                'blocked_by_user_id' => null,
            ]);

            $audit->record(
                $trailer,
                AuditAction::TRAILER_UNBLOCKED,
                $old,
                $audit->snapshotModel($trailer->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'trailer.unblocked',
                $trailer,
                "Trailer {$trailer->trailer_code} unblocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(
                new TrailerResource($trailer->fresh()->load(['carrier', 'customer', 'authMedia'])),
                __('vehicles.messages.trailer_unblocked')
            );
        });
    }

    public function authMedia($id)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error(__('vehicles.messages.trailer_not_found'), 'TRAILER_NOT_FOUND', 404);
        }

        $media = $trailer->authMedia()
            ->where('status', AuthMediumStatus::ACTIVE)
            ->orderByDesc('created_at')
            ->get();

        return $this->success(AuthMediumResource::collection($media), __('vehicles.messages.auth_media'));
    }

    public function plantVisits($id)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error(__('vehicles.messages.trailer_not_found'), 'TRAILER_NOT_FOUND', 404);
        }

        return $this->success(
            ['data' => [], 'note' => 'Plant visits module not yet implemented'],
            __('vehicles.messages.plant_visits_placeholder')
        );
    }

    public function loadings(Request $request, $id)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error(__('vehicles.messages.trailer_not_found'), 'TRAILER_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);

        $paginator = LoadingOperation::query()
            ->where('trailer_id', $trailer->id)
            ->orderByDesc('started_at')
            ->paginate($perPage, [
                'id', 'display_no', 'bay_line_id', 'driver_id',
                'order_no', 'sap_order_no', 'trailer_label',
                'loading_status', 'analysis_status',
                'target_quantity', 'actual_quantity', 'unit', 'progress_percent',
                'started_at', 'completed_at', 'updated_at',
            ]);

        $lastUpdated = LoadingOperation::query()
            ->where('trailer_id', $trailer->id)
            ->max('updated_at');

        return ApiResponse::list($paginator->items(), $paginator, null, $lastUpdated, __('vehicles.messages.trailer_loadings'));
    }

    public function documents($id)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error(__('vehicles.messages.trailer_not_found'), 'TRAILER_NOT_FOUND', 404);
        }

        return $this->success(
            ['data' => [], 'note' => 'Documents module not yet implemented'],
            __('vehicles.messages.documents_placeholder')
        );
    }

    public function eventsAudit(Request $request, $id)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error(__('vehicles.messages.trailer_not_found'), 'TRAILER_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $entityType = $trailer->getMorphClass();

        $audits = AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $trailer->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $events = EventLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $trailer->id)
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();

        return $this->success([
            'audits' => $audits,
            'events' => $events,
            'meta' => [
                'per_page' => $perPage,
                'audits_count' => $audits->count(),
                'events_count' => $events->count(),
            ],
        ], __('vehicles.messages.trailer_events_audit'));
    }

    public function export(Request $request)
    {
        $visibleColumns = (array) $request->input('visible_columns', []);
        $filters = (array) $request->input('filters', []);
        $search = $request->input('search');

        $query = Trailer::query()->with(['carrier', 'customer', 'authMedia']);
        $this->applyFilters($query, array_merge(['search' => $search], $filters));

        $rows = TrailerResource::collection($query->orderByDesc('updated_at')->get());

        // CSV/XLSX is TBC; for now return JSON honoring visible_columns metadata.
        return $this->success([
            'visibleColumns' => $visibleColumns,
            'rows' => $rows,
            'note' => 'Binary export (CSV/XLSX) not yet implemented; returning JSON payload.',
        ], __('vehicles.messages.trailer_export'));
    }

    /**
     * Pre-compute two maps for the paginated trailer list:
     *   - active_order_id / _code         from loading_orders where
     *                                     assigned_trailer_id = trailer.id
     *                                     AND status NOT IN (completed, cancelled).
     *                                     Tied earliest planned_window_start wins.
     *   - last_loaded_at                  max(completed_at) from
     *                                     loading_operations where
     *                                     trailer_id = trailer.id
     *                                     AND completed_at IS NOT NULL.
     *
     * Both run as a SINGLE query each over the paginated id slice, then
     * attach the results back onto the model instances as synthetic
     * attributes (__active_order_*, __last_loaded_at). The resource
     * reads from there — no N+1, no model method indirection.
     *
     * Empty trailer list short-circuits to a no-op.
     *
     * @param  array<int,Trailer>  $trailers
     */
    protected function enrichTrailers(array $trailers): void
    {
        if (empty($trailers)) {
            return;
        }

        $ids = array_map(fn ($t) => $t->id, $trailers);

        // 1) Active orders. We pick the canonical "current" order per
        //    trailer with a window function fallback — simpler: order
        //    by (priority of status, planned_window_start ASC) and
        //    keep the first occurrence per trailer.
        $orders = LoadingOrder::query()
            ->whereIn('assigned_trailer_id', $ids)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByRaw("CASE status
                WHEN 'in_progress' THEN 1
                WHEN 'ready' THEN 2
                WHEN 'needs_assignment' THEN 3
                WHEN 'draft' THEN 4
                WHEN 'blocked' THEN 5
                ELSE 9
            END")
            ->orderByRaw('COALESCE(planned_window_start, created_at) ASC')
            ->get(['id', 'order_no', 'assigned_trailer_id']);

        $orderByTrailerId = [];
        foreach ($orders as $o) {
            $tid = $o->assigned_trailer_id;
            if (! isset($orderByTrailerId[$tid])) {
                $orderByTrailerId[$tid] = $o;
            }
        }

        // 2) Last loaded — max(completed_at) per trailer.
        $lastLoaded = LoadingOperation::query()
            ->whereIn('trailer_id', $ids)
            ->whereNotNull('completed_at')
            ->selectRaw('trailer_id, MAX(completed_at) AS last_completed_at')
            ->groupBy('trailer_id')
            ->get()
            ->keyBy('trailer_id');

        foreach ($trailers as $t) {
            $order = $orderByTrailerId[$t->id] ?? null;
            $t->setAttribute('__active_order_id', $order?->id);
            $t->setAttribute('__active_order_code', $order?->order_no);
            $t->setAttribute(
                '__last_loaded_at',
                isset($lastLoaded[$t->id])
                    ? $lastLoaded[$t->id]->last_completed_at
                    : null
            );
        }
    }

    /**
     * Compute the `current_context_kind` enum from the trailer's state
     * (mirrors the same derivation the resource performs). Used both by
     * the resource AND by the post-pagination filter.
     */
    protected function deriveContextKind(Trailer $t): ?string
    {
        // Active order beats everything — the trailer is "loading".
        if (! empty($t->getAttribute('__active_order_id'))) {
            return 'loading';
        }
        if (! empty($t->current_parking_id)) {
            return 'parking';
        }
        if ($t->status === TrailerStatus::BLOCKED) {
            return 'maintenance';
        }
        // Clarification module not yet wired — never returns 'clarification'
        // until LogbookEntry / ClarificationCase exposure lands here.
        return null;
    }

    /**
     * Filter the paginated rows by `current_context_kind`.
     *
     * Trade-off: this runs in PHP AFTER pagination because the context
     * kind is derived from join data + trailer status — pushing it to
     * SQL would mean replicating the whole orderByRaw + IFNULL ladder.
     * Side effect: `meta.total` does not shrink when the filter is
     * active (it reflects the pre-derived-filter set). FE should treat
     * the filter as a client-side narrowing of the page contents.
     *
     * @param  array<int,Trailer>  $rows
     * @return array<int,Trailer>
     */
    protected function applyDerivedContextKindFilter(array $rows, string $kind): array
    {
        $allowed = ['loading', 'parking', 'clarification', 'maintenance'];
        if (! in_array($kind, $allowed, true)) {
            return $rows;
        }
        return array_values(array_filter(
            $rows,
            fn (Trailer $t) => $this->deriveContextKind($t) === $kind
        ));
    }

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('trailer_code', 'like', $search)
                    ->orWhere('trailer_label', 'like', $search)
                    ->orWhere('plate', 'like', $search)
                    ->orWhere('current_context', 'like', $search)
                    ->orWhereHas('carrier', fn ($c) => $c->where('name', 'like', $search))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $search));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['technical_suitability'])) {
            $query->where('technical_suitability', $filters['technical_suitability']);
        }
        if (! empty($filters['trailer_type'])) {
            $query->where('trailer_type', $filters['trailer_type']);
        }
        if (! empty($filters['pressure_class'])) {
            $query->where('pressure_class', $filters['pressure_class']);
        }
        if (! empty($filters['carrier_id'])) {
            $query->where('carrier_id', $filters['carrier_id']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        if (! empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        if (! empty($filters['inspection_state'])) {
            $this->applyInspectionStateFilter($query, $filters['inspection_state']);
        }

        if (! empty($filters['chip_state'])) {
            $this->applyChipStateFilter($query, $filters['chip_state']);
        }

        if (! empty($filters['attention'])) {
            // Composite: missing chip OR inspection expired/expiring OR suitability != approved
            $query->where(function ($q) {
                $q->where('technical_suitability', '!=', 'approved')
                    ->orWhereNull('inspection_expiry_date')
                    ->orWhereDate('inspection_expiry_date', '<=', now()->addDays(30))
                    ->orWhereDoesntHave('authMedia', function ($q2) {
                        $q2->where('medium_type', 'chip_card')
                            ->where('status', AuthMediumStatus::ACTIVE);
                    });
            });
        }

        // ?needs_attention=1 — matches summary.needsAttention exactly
        // (chip/inspection/suitability issue) but also excludes blocked
        // and inactive trailers, so the FE list keeps the operational
        // intent intact. The legacy `attention=true` filter above is
        // kept as-is for backward compatibility; `needs_attention=1`
        // is the new snake_case API and additionally narrows away
        // blocked / inactive rows.
        if (filter_var($filters['needs_attention'] ?? null, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_active', true)
                ->where('status', '!=', TrailerStatus::BLOCKED)
                ->where(function ($q) {
                    $q->where('technical_suitability', '!=', 'approved')
                        ->orWhereNull('inspection_expiry_date')
                        ->orWhereDate('inspection_expiry_date', '<=', now()->addDays(30))
                        ->orWhereDoesntHave('authMedia', function ($q2) {
                            $q2->where('medium_type', 'chip_card')
                                ->where('status', AuthMediumStatus::ACTIVE);
                        });
                });
        }

        // ?assignable=true — narrows to trailers the order assign endpoint
        // would actually accept. Delegates to Trailer::scopeAssignable so
        // it stays in lock-step with trailerAssignmentConflict() and the
        // resource's `assignment` block: ACTIVE + is_active + at least one
        // active chip OR TAN. technical_suitability is intentionally NOT
        // checked — the conflict predicate doesn't reject on that either.
        //
        // NOTE: the redesigned picker (Loading Orders V2.2 §9.2) now lists
        // EVERY trailer and disables ineligible rows from the resource's
        // `assignment.assignable` flag instead of passing this filter; it
        // remains for callers that still want a server-side narrowing.
        if (filter_var($filters['assignable'] ?? null, FILTER_VALIDATE_BOOLEAN)) {
            $query->assignable();
        }
    }

    protected function applyInspectionStateFilter($query, string $state): void
    {
        $today = now()->startOfDay();
        $in30 = now()->addDays(30)->endOfDay();

        switch ($state) {
            case InspectionState::MISSING:
                $query->whereNull('inspection_expiry_date');
                break;
            case InspectionState::EXPIRED:
                $query->whereNotNull('inspection_expiry_date')
                    ->whereDate('inspection_expiry_date', '<', $today);
                break;
            case InspectionState::EXPIRING_SOON:
                $query->whereNotNull('inspection_expiry_date')
                    ->whereDate('inspection_expiry_date', '>=', $today)
                    ->whereDate('inspection_expiry_date', '<=', $in30);
                break;
            case InspectionState::VALID:
                $query->whereNotNull('inspection_expiry_date')
                    ->whereDate('inspection_expiry_date', '>', $in30);
                break;
        }
    }

    protected function applyChipStateFilter($query, string $state): void
    {
        switch ($state) {
            case 'assigned':
                $query->whereHas('authMedia', function ($q) {
                    $q->where('medium_type', 'chip_card')->where('status', AuthMediumStatus::ACTIVE);
                });
                break;
            case 'missing':
                $query->whereDoesntHave('authMedia', function ($q) {
                    $q->where('medium_type', 'chip_card')->where('status', AuthMediumStatus::ACTIVE);
                })->where('status', '!=', TrailerStatus::BLOCKED);
                break;
            case 'blocked':
                $query->where('status', TrailerStatus::BLOCKED);
                break;
                // 'mismatch' / 'not_required' have no stored signal yet — no-op filter.
        }
    }

    protected function summary(): array
    {
        $total = Trailer::query()->count();
        $blocked = Trailer::query()->where('status', TrailerStatus::BLOCKED)->count();
        $ready = Trailer::query()
            ->where('status', TrailerStatus::ACTIVE)
            ->where('technical_suitability', 'approved')
            ->whereHas('authMedia', function ($q) {
                $q->where('medium_type', 'chip_card')->where('status', AuthMediumStatus::ACTIVE);
            })
            ->where(function ($q) {
                $q->whereNull('inspection_expiry_date')
                    ->orWhereDate('inspection_expiry_date', '>', now()->addDays(30));
            })
            ->count();

        $needsAttention = Trailer::query()
            ->where(function ($q) {
                $q->where('technical_suitability', '!=', 'approved')
                    ->orWhereNull('inspection_expiry_date')
                    ->orWhereDate('inspection_expiry_date', '<=', now()->addDays(30))
                    ->orWhereDoesntHave('authMedia', function ($q2) {
                        $q2->where('medium_type', 'chip_card')->where('status', AuthMediumStatus::ACTIVE);
                    });
            })
            ->count();

        return [
            'totalTrailers' => $total,
            'ready' => $ready,
            'blocked' => $blocked,
            'needsAttention' => $needsAttention,
        ];
    }

    /**
     * Next trailer_code in the `TR-NNNN` family. Uses a max+1 scan on
     * the matching prefix so gaps from deleted demo rows don't reset
     * the counter, and starts at 1001 to mirror the seeded range.
     */
    protected function nextTrailerCode(): string
    {
        $prefix = 'TR-';

        $lastNo = Trailer::query()
            ->where('trailer_code', 'like', $prefix . '%')
            ->selectRaw("MAX(CAST(SUBSTRING(trailer_code, " . (strlen($prefix) + 1) . ") AS UNSIGNED)) AS max_no")
            ->value('max_no');

        $next = max(1001, ((int) ($lastNo ?? 0)) + 1);
        return $prefix . $next;
    }
}
