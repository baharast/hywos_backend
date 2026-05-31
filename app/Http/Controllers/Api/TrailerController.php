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

        $query = Trailer::query()->with(['carrier', 'customer', 'authMedia']);
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

        $rows = TrailerResource::collection($paginator->items());

        $summary = $this->summary();
        $lastUpdated = Trailer::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'Trailers retrieved');
    }

    public function store(StoreTrailerRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $data = $request->validated();

        // System generates trailer_code (TR-NNNN) so the admin form
        // doesn't have to. Provided values (idempotent imports) are
        // accepted as-is; otherwise pick the next monotonic suffix.
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
                'Trailer created'
            );
        });
    }

    public function show($id)
    {
        $trailer = Trailer::with(['carrier', 'customer', 'authMedia'])->find($id);
        if (! $trailer) {
            return $this->error('Trailer not found', 'TRAILER_NOT_FOUND', 404);
        }

        return $this->success(new TrailerResource($trailer), 'Trailer retrieved');
    }

    public function update(UpdateTrailerRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error('Trailer not found', 'TRAILER_NOT_FOUND', 404);
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
                'Trailer updated'
            );
        });
    }

    public function block(BlockTrailerRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error('Trailer not found', 'TRAILER_NOT_FOUND', 404);
        }
        if ($trailer->status === TrailerStatus::BLOCKED) {
            return $this->error('Trailer already blocked', 'ALREADY_BLOCKED', 409);
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
                'Trailer blocked'
            );
        });
    }

    public function unblock(UnblockTrailerRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error('Trailer not found', 'TRAILER_NOT_FOUND', 404);
        }
        if ($trailer->status !== TrailerStatus::BLOCKED) {
            return $this->error('Trailer is not blocked', 'NOT_BLOCKED', 409);
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
                'Trailer unblocked'
            );
        });
    }

    public function authMedia($id)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error('Trailer not found', 'TRAILER_NOT_FOUND', 404);
        }

        $media = $trailer->authMedia()
            ->where('status', AuthMediumStatus::ACTIVE)
            ->orderByDesc('created_at')
            ->get();

        return $this->success(AuthMediumResource::collection($media), 'Auth media retrieved');
    }

    public function plantVisits($id)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error('Trailer not found', 'TRAILER_NOT_FOUND', 404);
        }

        return $this->success(
            ['data' => [], 'note' => 'Plant visits module not yet implemented'],
            'Plant visits retrieved (placeholder)'
        );
    }

    public function loadings(Request $request, $id)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error('Trailer not found', 'TRAILER_NOT_FOUND', 404);
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

        return ApiResponse::list($paginator->items(), $paginator, null, $lastUpdated, 'Loadings retrieved');
    }

    public function documents($id)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error('Trailer not found', 'TRAILER_NOT_FOUND', 404);
        }

        return $this->success(
            ['data' => [], 'note' => 'Documents module not yet implemented'],
            'Documents retrieved (placeholder)'
        );
    }

    public function eventsAudit(Request $request, $id)
    {
        $trailer = Trailer::find($id);
        if (! $trailer) {
            return $this->error('Trailer not found', 'TRAILER_NOT_FOUND', 404);
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
        ], 'Trailer events & audit retrieved');
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
        ], 'Trailer export prepared');
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

        // ?assignable=true — mirrors trailerAssignmentConflict() exactly
        // so the assignment dropdown only shows trailers the order
        // assign endpoint would actually accept:
        //   - status != BLOCKED
        //   - has an ACTIVE chip_card (chip_state='assigned')
        // technical_suitability is intentionally NOT checked here — the
        // conflict predicate doesn't reject on that either, so we stay
        // in lock-step with the assign-side decision.
        if (filter_var($filters['assignable'] ?? null, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('status', '!=', TrailerStatus::BLOCKED)
                ->whereHas('authMedia', function ($q) {
                    $q->where('medium_type', 'chip_card')
                        ->where('status', AuthMediumStatus::ACTIVE);
                });
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
