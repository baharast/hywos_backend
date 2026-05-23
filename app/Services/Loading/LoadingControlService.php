<?php

namespace App\Services\Loading;

use App\Enums\AuditAction;
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

class LoadingControlService
{
    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events
    ) {
    }

    /**
     * Build Station-View cards: every configured BayLine plus its currently active
     * LoadingOperation (or null when free). When more than one active loading
     * resolves to the same bay we emit a row per loading and flag the duplicates
     * so the frontend can surface a clarification — we never silently pick one.
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

            // Defensive: if multiple actives collide on the same bay, expose all
            // and flag clarification on every row so dispatcher cannot miss it.
            if ($actives->count() > 1) {
                foreach ($actives as $active) {
                    $active->has_clarification = true;
                    $items->push(['bay' => $bay, 'active' => $active]);
                }
                continue;
            }

            $items->push(['bay' => $bay, 'active' => $actives->first()]);
        }

        if (! empty($filters['station_status'])) {
            $wanted = strtolower((string) $filters['station_status']);
            $items = $items->filter(function ($row) use ($wanted) {
                return \App\Enums\StationStatus::derive($row['bay'], $row['active']) === $wanted;
            })->values();
        }

        return $items;
    }

    /**
     * Builder for the Active Loadings table. Resolves filters from the request.
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
                    ->orWhere('tractor_plate', 'like', $search);
            });
        }

        if (! empty($filters['station_id'])) {
            $query->where('bay_line_id', $filters['station_id']);
        }
        if (! empty($filters['loading_status'])) {
            $query->where('loading_status', $filters['loading_status']);
        }
        if (! empty($filters['analysis_status'])) {
            $query->where('analysis_status', $filters['analysis_status']);
        }
        if (isset($filters['has_clarification'])) {
            $query->where('has_clarification', (bool) $filters['has_clarification']);
        }
        if (! empty($filters['has_alarm'])) {
            $query->where('alarm_count', '>', 0);
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
     * Overview-card aggregates for the dashboard header.
     *
     * @return array{stationsLoading: int, freeStations: int, waitingForAnalysis: int, faultsOrBlockers: int}
     */
    public function summary(): array
    {
        $stationsLoading = LoadingOperation::query()
            ->where('loading_status', LoadingStatus::LOADING)
            ->whereNotNull('bay_line_id')
            ->distinct('bay_line_id')
            ->count('bay_line_id');

        $busyBayIds = LoadingOperation::query()
            ->active()
            ->whereNotNull('bay_line_id')
            ->pluck('bay_line_id')
            ->unique()
            ->all();

        $totalBays = BayLine::query()->count();
        $freeStations = max(0, $totalBays - count($busyBayIds));

        $waitingForAnalysis = LoadingOperation::query()
            ->whereIn('loading_status', [
                LoadingStatus::WAITING_PRE_ANALYSIS,
                LoadingStatus::WAITING_MAIN_ANALYSIS,
                LoadingStatus::QUALITY_CHECK_OPEN,
            ])->count();

        $faultsOrBlockers = LoadingOperation::query()
            ->where(function ($q) {
                $q->whereIn('loading_status', [
                    LoadingStatus::QUALITY_BLOCKED,
                    LoadingStatus::CLARIFICATION_REQUIRED,
                    LoadingStatus::FAILED,
                ])->orWhere('critical_alarm_count', '>', 0);
            })->count();

        return [
            'stationsLoading' => $stationsLoading,
            'freeStations' => $freeStations,
            'waitingForAnalysis' => $waitingForAnalysis,
            'faultsOrBlockers' => $faultsOrBlockers,
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
     * Append an operational note. Wrapped in a transaction; emits audit + event.
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
}
