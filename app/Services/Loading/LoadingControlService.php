<?php

namespace App\Services\Loading;

use App\Enums\AuditAction;
use App\Enums\BayLineStatus;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\LoadingStatus;
use App\Models\BayLine;
use App\Models\LoadingOperation;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Implements the FillTrack Loading Control UX Spec V3.2 backend surface:
 * Compact Status Tiles (§4.3), Station Board (§5) with display priority
 * (§5.2), Active Loadings list (§6), Selected / Full Loading Details
 * (§7 / §8).
 *
 * Read-mostly. The only write is {@see self::addNote()} which exists to
 * give operators a paper-trail of operational decisions; V3.2 §1.2 + §13
 * explicitly forbid any other write (no start/stop/pause/ESD/PLC/force).
 */
class LoadingControlService
{
    /** Bay/loading data older than this is rendered with a stale warning. */
    protected const STALE_THRESHOLD_SECONDS = 90;

    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events
    ) {
    }

    /**
     * Build Station-View cards: every configured BayLine plus its currently
     * active LoadingOperation (or null when the bay is free). When multiple
     * actives collide on one bay we emit a row per loading and flag every
     * row as `has_clarification` so the dispatcher cannot miss the
     * ambiguity — V3.2 §1.2 / §13 forbid auto-guessing.
     *
     * @return Collection<int, array{bay: BayLine, active: LoadingOperation|null}>
     */
    public function stationViewItems(array $filters = []): Collection
    {
        $bayQuery = BayLine::query();
        if (! empty($filters['site_id'])) {
            $bayQuery->where('site_id', $filters['site_id']);
        }
        if (! empty($filters['plant_area_id'])) {
            $bayQuery->where('plant_area_id', $filters['plant_area_id']);
        }

        $bays = $bayQuery->orderBy('code')->get();

        $bayIds = $bays->pluck('id')->all();

        $activeByBay = LoadingOperation::query()
            ->with('driver')
            ->whereIn('bay_line_id', $bayIds)
            ->active()
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('bay_line_id');

        $items = collect();

        foreach ($bays as $bay) {
            /** @var Collection<int, LoadingOperation> $actives */
            $actives = $activeByBay->get($bay->id, collect());

            if ($actives->isEmpty()) {
                $items->push(['bay' => $bay, 'active' => null]);
                continue;
            }

            if ($actives->count() > 1) {
                // Multiple active loadings on the same bay = ambiguity. We
                // never pick one; surface all with clarification flag so the
                // FE renders an attention card (V3.2 §5.2 priority 2).
                foreach ($actives as $active) {
                    $active->has_clarification = true;
                    $items->push(['bay' => $bay, 'active' => $active]);
                }
                continue;
            }

            $items->push(['bay' => $bay, 'active' => $actives->first()]);
        }

        if (! empty($filters['bay_status'])) {
            $wanted = strtolower((string) $filters['bay_status']);
            $items = $items->filter(function ($row) use ($wanted) {
                return BayLineStatus::derive($row['bay'], $row['active']) === $wanted;
            })->values();
        }

        return $items;
    }

    /**
     * Builder for the Active Loadings table. Resolves V3.2 §6.4 filters:
     * `bay_line`, `loading_state`, `analysis_state`, `issue_type`
     * (categorical), plus search and date-range refinements.
     */
    public function activeLoadingsQuery(array $filters = []): Builder
    {
        $query = LoadingOperation::query()->with(['bayLine', 'driver']);

        if (empty($filters['include_terminal'])) {
            $query->active();
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('display_no', 'like', $search)
                    ->orWhere('order_no', 'like', $search)
                    ->orWhere('sap_order_no', 'like', $search)
                    ->orWhere('trailer_label', 'like', $search)
                    ->orWhere('tractor_plate', 'like', $search)
                    ->orWhere('visit_no', 'like', $search);
            });
        }

        if (! empty($filters['bay_line'])) {
            $query->where('bay_line_id', $filters['bay_line']);
        }

        // V3.2 §11.1 — `loading_state` accepts the wire vocabulary. We map it
        // back to whichever DB value(s) it covers so legacy and V3.2 rows
        // both match.
        if (! empty($filters['loading_state'])) {
            $dbValues = $this->mapWireLoadingStateToDb($filters['loading_state']);
            if ($dbValues) {
                $query->whereIn('loading_status', $dbValues);
            }
        }

        if (! empty($filters['analysis_state'])) {
            $query->where('analysis_status', $filters['analysis_state']);
        }

        // V3.2 §6.4 — `issue_type` replaces the V2-era `has_clarification`
        // and `has_alarm` boolean filters. Categorical so the FE can render
        // a single dropdown.
        if (! empty($filters['issue_type'])) {
            $this->applyIssueTypeFilter($query, (string) $filters['issue_type']);
        }

        if (! empty($filters['started_from'])) {
            $query->where('started_at', '>=', $filters['started_from']);
        }
        if (! empty($filters['started_to'])) {
            $query->where('started_at', '<=', $filters['started_to']);
        }

        $sort = $filters['sort'] ?? '-started_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowedSort = ['started_at', 'updated_at', 'display_no', 'loading_status', 'progress_percent'];
        if (! in_array($column, $allowedSort, true)) {
            $column = 'started_at';
            $direction = 'desc';
        }
        $query->orderBy($column, $direction);

        return $query;
    }

    /**
     * Compact Status Tiles (V3.2 §4.3) — exactly 6 keys.
     *
     *   total           Total bay lines (always 6 in MVP).
     *   available       Bays ready/available for the next loading.
     *   inUse           Bays currently loading or assigned to an active loading.
     *   outOfService    Bays in maintenance/offline/service mode.
     *   blocked         Bays/loadings blocked by clarification, safety,
     *                   analysis or device issue.
     *   plcOnline       `"$online/$total"` string — degraded state links to
     *                   System & Devices on the FE.
     *
     * @return array{total: int, available: int, inUse: int, outOfService: int, blocked: int, plcOnline: string}
     */
    public function summary(): array
    {
        $bays = BayLine::query()->orderBy('code')->get();
        $bayIds = $bays->pluck('id')->all();

        $activesByBay = LoadingOperation::query()
            ->whereIn('bay_line_id', $bayIds)
            ->active()
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('bay_line_id');

        $total = $bays->count();
        $available = 0;
        $inUse = 0;
        $outOfService = 0;
        $blocked = 0;
        $plcOnline = 0;

        foreach ($bays as $bay) {
            $actives = $activesByBay->get($bay->id, collect());
            $active = $actives->first();
            $bayStatus = BayLineStatus::derive($bay, $active);

            switch ($bayStatus) {
                case BayLineStatus::AVAILABLE:
                    $available++;
                    break;
                case BayLineStatus::MAINTENANCE_OFFLINE:
                    $outOfService++;
                    break;
                case BayLineStatus::FAULT_BLOCKED:
                    $blocked++;
                    break;
                default:
                    // reserved, loading, waiting_analysis,
                    // completed_waiting_documents all count as in-use.
                    $inUse++;
            }

            // PLC tile derivation: a bay is considered "online" when the
            // active loading reports `connected` PLC or when the bay has no
            // active loading and is not faulty/offline. We're conservative —
            // unknown / degraded / failed count as not online.
            $plcStatus = $active?->plc_status;
            if ($bayStatus !== BayLineStatus::MAINTENANCE_OFFLINE
                && $bayStatus !== BayLineStatus::FAULT_BLOCKED
                && ($plcStatus === null || $plcStatus === 'connected')
            ) {
                $plcOnline++;
            }
        }

        return [
            'total' => $total,
            'available' => $available,
            'inUse' => $inUse,
            'outOfService' => $outOfService,
            'blocked' => $blocked,
            'plcOnline' => "{$plcOnline}/{$total}",
        ];
    }

    /**
     * Return the eager-loaded loading model for detail responses.
     */
    public function loadingDetail(LoadingOperation $loading): LoadingOperation
    {
        return $loading->load(['bayLine', 'driver']);
    }

    /**
     * Append an operational note. Wrapped in a transaction; emits audit +
     * event. The only write on this controller (V3.2 §1.2 + §13).
     */
    public function addNote(LoadingOperation $loading, string $note): LoadingOperation
    {
        return DB::transaction(function () use ($loading, $note) {
            $old = $this->audit->snapshotModel($loading);

            $existing = $loading->notes ? rtrim($loading->notes) . "\n---\n" : '';
            $stamp = now()->toIso8601String();
            $loading->update([
                'notes' => $existing . "[{$stamp}] " . $note,
                'last_event_at' => now(),
            ]);

            $this->audit->record(
                $loading,
                AuditAction::LOADING_NOTE_ADDED,
                $old,
                $this->audit->snapshotModel($loading->fresh()),
                $note,
                null
            );

            $this->events->record(
                'loading.note_added',
                $loading,
                "Note added to loading {$loading->display_no}",
                ['note_length' => strlen($note)],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $loading->fresh();
        });
    }

    /**
     * Map a V3.2 wire-level `loading_state` value back to the set of DB
     * `loading_status` strings that should match. Necessary because seeded
     * data + the LoadingOperation model still use legacy strings like
     * `assigned`, `released`, `paused`, `failed`, `cancelled`,
     * `quality_check_open`.
     *
     * @return list<string>|null
     */
    protected function mapWireLoadingStateToDb(string $wire): ?array
    {
        return match ($wire) {
            LoadingStatus::ASSIGNED_READY_FOR_BAY => [LoadingStatus::ASSIGNED_READY_FOR_BAY, LoadingStatus::ASSIGNED],
            LoadingStatus::READY_FOR_LOADING => [LoadingStatus::READY_FOR_LOADING, LoadingStatus::RELEASED],
            LoadingStatus::PAUSED_WAITING => [LoadingStatus::PAUSED_WAITING, LoadingStatus::PAUSED],
            LoadingStatus::DOCUMENTS_PENDING => [LoadingStatus::DOCUMENTS_PENDING, LoadingStatus::QUALITY_CHECK_OPEN],
            LoadingStatus::FAULT_DEVICE_ISSUE => [LoadingStatus::FAULT_DEVICE_ISSUE, LoadingStatus::FAILED],
            // Values that are unchanged between legacy and V3.2 vocab.
            LoadingStatus::WAITING_PRE_ANALYSIS,
            LoadingStatus::LOADING,
            LoadingStatus::COMPLETED,
            LoadingStatus::WAITING_MAIN_ANALYSIS,
            LoadingStatus::QUALITY_BLOCKED,
            LoadingStatus::CLARIFICATION_REQUIRED => [$wire],
            default => null,
        };
    }

    /**
     * V3.2 §6.4 categorical `issue_type` filter. Covers the common
     * blocker/issue buckets the operator would scan for.
     */
    protected function applyIssueTypeFilter(Builder $query, string $issueType): void
    {
        match ($issueType) {
            'clarification' => $query->where(function ($q) {
                $q->where('has_clarification', true)
                    ->orWhere('loading_status', LoadingStatus::CLARIFICATION_REQUIRED);
            }),

            'quality_blocked' => $query->where('loading_status', LoadingStatus::QUALITY_BLOCKED),

            'device_fault' => $query->where(function ($q) {
                $q->where('loading_status', LoadingStatus::FAULT_DEVICE_ISSUE)
                    ->orWhere('loading_status', LoadingStatus::FAILED)
                    ->orWhere('plc_status', 'failed')
                    ->orWhere('critical_alarm_count', '>', 0);
            }),

            'waiting_analysis' => $query->whereIn('loading_status', [
                LoadingStatus::WAITING_PRE_ANALYSIS,
                LoadingStatus::WAITING_MAIN_ANALYSIS,
            ]),

            'documents_pending' => $query->whereIn('loading_status', [
                LoadingStatus::DOCUMENTS_PENDING,
                LoadingStatus::QUALITY_CHECK_OPEN,
            ]),

            'paused' => $query->whereIn('loading_status', [
                LoadingStatus::PAUSED_WAITING,
                LoadingStatus::PAUSED,
            ]),

            'none' => $query->whereNotIn('loading_status', [
                LoadingStatus::CLARIFICATION_REQUIRED,
                LoadingStatus::QUALITY_BLOCKED,
                LoadingStatus::FAULT_DEVICE_ISSUE,
                LoadingStatus::FAILED,
                LoadingStatus::PAUSED_WAITING,
                LoadingStatus::PAUSED,
                LoadingStatus::WAITING_PRE_ANALYSIS,
                LoadingStatus::WAITING_MAIN_ANALYSIS,
                LoadingStatus::DOCUMENTS_PENDING,
                LoadingStatus::QUALITY_CHECK_OPEN,
            ])->where('has_clarification', false),

            default => null, // unknown issue type — silently skip filter
        };
    }
}
