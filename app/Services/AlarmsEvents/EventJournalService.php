<?php

namespace App\Services\AlarmsEvents;

use App\Enums\EventCategory;
use App\Enums\EventJournalCategory;
use App\Enums\EventJournalResult;
use App\Enums\EventSeverity;
use App\Models\EventLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Source-facing read for V1 §7.4 Event Journal (default subview).
 *
 * Pure read surface over the existing `event_logs` table. No new tables,
 * no new audit constants. List + detail endpoints derive the V1 §7.4
 * 11-value journal category and the typed result at read time.
 *
 * Per §7.4 forbidden actions: NO editing, deleting, overwriting or
 * manually reordering event journal entries. Corrections must land in
 * audit_logs (Track E) or as a new logbook entry (Track C). No write
 * endpoints exist on this surface.
 *
 * Subview routing per spec §7.2:
 *   - Default subview: this service. Security subview is a separate
 *     service (Track D) so the permission boundary is enforced at the
 *     route layer.
 */
class EventJournalService
{
    public const ALLOWED_SORT_COLUMNS = [
        'occurred_at', 'created_at', 'event_type', 'severity',
    ];

    public function listForTab(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = EventLog::query();
        $this->excludeSecurity($query);
        $this->applyFilters($query, $filters);

        $sort = (string) ($filters['sort'] ?? '-occurred_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (! in_array($column, self::ALLOWED_SORT_COLUMNS, true)) {
            $column = 'occurred_at';
            $direction = 'desc';
        }
        $query->orderBy($column, $direction)->orderBy('id', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * V1 §7.2: Security events are a restricted subview under the SAME
     * physical table. The default Event Journal must EXCLUDE security
     * rows so unauthorized roles never see them by accident.
     */
    protected function excludeSecurity(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->where('event_category', '!=', EventCategory::SECURITY)
                ->orWhereNull('event_category');
        });
    }

    /**
     * Compose the FE-facing row from one event_logs record.
     *
     * @return array<string,mixed>
     */
    public function enrichRow(EventLog $row): array
    {
        $journalCategory = EventJournalCategory::deriveFrom(
            $row->event_category,
            $row->event_type,
        );
        $result = EventJournalResult::deriveFrom(
            $row->event_type,
            $row->severity,
        );

        return [
            'journalCategory' => [
                'value' => $journalCategory,
                'label' => EventJournalCategory::label($journalCategory),
                'tone' => EventJournalCategory::tone($journalCategory),
            ],
            'result' => [
                'value' => $result,
                'label' => EventJournalResult::label($result),
                'tone' => EventJournalResult::tone($result),
            ],
            'relatedRecords' => $this->resolveRelatedRecords($row),
        ];
    }

    /**
     * V1 §7.4 "Correlation & links" detail section.
     *
     * @return array{
     *   entityRoute:string|null,
     *   auditTrailByCorrelation:string|null,
     *   eventJournalByCorrelation:string|null
     * }
     */
    public function resolveRelatedRecords(EventLog $row): array
    {
        $entityRoute = $this->resolveEntityRoute(
            (string) $row->entity_type,
            (string) $row->entity_id,
        );
        $auditTrailByCorrelation = $row->correlation_id
            ? "/alarms-events/audit-trail?correlation_id={$row->correlation_id}"
            : null;
        $eventJournalByCorrelation = $row->correlation_id
            ? "/alarms-events/event-journal?correlation_id={$row->correlation_id}"
            : null;

        return [
            'entityRoute' => $entityRoute,
            'auditTrailByCorrelation' => $auditTrailByCorrelation,
            'eventJournalByCorrelation' => $eventJournalByCorrelation,
        ];
    }

    protected function resolveEntityRoute(string $entityType, string $entityId): ?string
    {
        if ($entityType === '' || $entityId === '') return null;
        $short = class_basename($entityType);
        return match ($short) {
            'User' => "/administration/users/{$entityId}",
            'Driver' => "/master-data/drivers/{$entityId}",
            'Trailer' => "/master-data/trailers/{$entityId}",
            'TractorVehicle' => "/master-data/tractors-vehicles/{$entityId}",
            'Customer' => "/master-data/customers/{$entityId}",
            'Carrier' => "/master-data/freight-forwarders-carriers/{$entityId}",
            'ChipCard' => "/master-data/chip-cards/{$entityId}",
            'Tan' => "/master-data/tans/{$entityId}",
            'LoadingOrder' => "/operations/loading-orders/{$entityId}",
            'PlantVisit' => "/operations/plant-visits/{$entityId}",
            'ClarificationCase' => "/operations/clarification-cases/{$entityId}",
            'OperationalDocument' => "/documents-reports/operational-documents/{$entityId}",
            'HardwareDevice' => "/system-devices/hardware-devices/{$entityId}",
            'InterfaceHealth', 'Interface' => "/system-devices/interface-health/{$entityId}",
            'Analysis' => "/analysis-quality/active-analyses/{$entityId}",
            'AnalysisDevice' => "/analysis-quality/analysis-devices/{$entityId}",
            'TerminalSession' => "/operations/gate-terminal-monitor/sessions/{$entityId}",
            default => null,
        };
    }

    /* ============================================================
     * Summary bar
     * ============================================================ */

    /**
     * @return array{
     *   totalToday:int, errorsToday:int, deniedToday:int,
     *   timeoutsToday:int, oldestUnreviewedAt:string|null,
     *   availableFilterValues:array<string,array<int,string>>
     * }
     */
    public function buildSummary(): array
    {
        $todayStart = Carbon::today();

        $todayQ = function () use ($todayStart) {
            $q = EventLog::query()->where('occurred_at', '>=', $todayStart);
            $this->excludeSecurity($q);
            return $q;
        };

        $totalToday = $todayQ()->count();
        $errorsToday = (clone $todayQ())
            ->whereIn('severity', [EventSeverity::DANGER, EventSeverity::CRITICAL])
            ->count();

        // Outcome-driven counters — derived from event_type tail, so
        // bucketing happens in PHP over today's rows only.
        $todayRows = (clone $todayQ())->get(['event_type', 'severity', 'occurred_at']);

        $deniedToday = 0;
        $timeoutsToday = 0;
        foreach ($todayRows as $r) {
            $res = EventJournalResult::deriveFrom($r->event_type, $r->severity);
            if ($res === EventJournalResult::DENIED) $deniedToday++;
            if ($res === EventJournalResult::TIMEOUT) $timeoutsToday++;
        }

        return [
            'totalToday' => $totalToday,
            'errorsToday' => $errorsToday,
            'deniedToday' => $deniedToday,
            'timeoutsToday' => $timeoutsToday,
            'availableFilterValues' => $this->availableFilterValues(),
        ];
    }

    public function availableFilterValues(): array
    {
        $since = Carbon::now()->subDays(7);
        $q = EventLog::query()->where('occurred_at', '>=', $since);
        $this->excludeSecurity($q);
        $rows = $q->get(['event_category', 'event_type', 'severity', 'entity_type']);

        $eventTypes = $rows->pluck('event_type')->filter()->unique()->values()->take(30)->all();
        $severities = $rows->pluck('severity')->filter()->unique()->values()->all();
        $entityTypes = $rows->pluck('entity_type')->filter()->map(fn ($e) => class_basename($e))->unique()->values()->all();

        $journalCategories = $rows
            ->map(fn ($r) => EventJournalCategory::deriveFrom($r->event_category, $r->event_type))
            ->filter()->unique()->values()->all();

        $results = $rows
            ->map(fn ($r) => EventJournalResult::deriveFrom($r->event_type, $r->severity))
            ->filter()->unique()->values()->all();

        return [
            'journalCategories' => $journalCategories,
            'results' => $results,
            'severities' => $severities,
            'eventTypes' => $eventTypes,
            'entityTypes' => $entityTypes,
        ];
    }

    /* ============================================================
     * Filter plumbing
     * ============================================================ */

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('event_type', 'like', $search)
                    ->orWhere('event_category', 'like', $search)
                    ->orWhere('entity_type', 'like', $search)
                    ->orWhere('entity_id', 'like', $search)
                    ->orWhere('message', 'like', $search)
                    ->orWhere('actor_name', 'like', $search)
                    ->orWhere('correlation_id', 'like', $search);
            });
        }

        if (! empty($filters['journal_category']) && in_array($filters['journal_category'], EventJournalCategory::all(), true)) {
            // Push down to SQL via event_type prefix groups for this
            // FE-facing category.
            $prefixes = $this->prefixesForJournalCategory($filters['journal_category']);
            $storedCategory = $this->storedCategoryForJournalCategory($filters['journal_category']);

            $query->where(function (Builder $q) use ($prefixes, $storedCategory) {
                foreach ($prefixes as $p) {
                    $q->orWhere('event_type', 'like', $p . '.%');
                }
                if ($storedCategory !== null) {
                    $q->orWhere('event_category', $storedCategory);
                }
            });
        }

        if (! empty($filters['event_category']) && in_array($filters['event_category'], $this->storedCategoryAll(), true)) {
            $query->where('event_category', $filters['event_category']);
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['severity'])) {
            $values = array_filter(array_map('trim', explode(',', (string) $filters['severity'])));
            $allowed = [EventSeverity::INFO, EventSeverity::WARNING, EventSeverity::DANGER, EventSeverity::CRITICAL];
            $values = array_values(array_intersect($values, $allowed));
            if ($values) {
                $query->whereIn('severity', $values);
            }
        }

        if (! empty($filters['result']) && in_array($filters['result'], EventJournalResult::all(), true)) {
            // Result is derived from event_type tail patterns; push down
            // via LIKE.
            $patterns = $this->tailPatternsForResult($filters['result']);
            if (! empty($patterns)) {
                $query->where(function (Builder $q) use ($patterns) {
                    foreach ($patterns as $p) {
                        $q->orWhere('event_type', 'like', '%' . $p . '%');
                    }
                });
            }
        }

        if (! empty($filters['entity_type'])) {
            $needle = $filters['entity_type'];
            $query->where(function (Builder $q) use ($needle) {
                $q->where('entity_type', $needle)
                    ->orWhere('entity_type', 'like', '%\\\\' . $needle);
            });
        }

        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (! empty($filters['correlation_id'])) {
            $query->where('correlation_id', $filters['correlation_id']);
        }

        if (! empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', $filters['actor_user_id']);
        }

        if (! empty($filters['date_from'])) {
            try { $query->where('occurred_at', '>=', Carbon::parse($filters['date_from'])); } catch (\Throwable) {}
        }
        if (! empty($filters['date_to'])) {
            try { $query->where('occurred_at', '<=', Carbon::parse($filters['date_to'])); } catch (\Throwable) {}
        }

        $timeRange = $filters['time_range'] ?? null;
        if ($timeRange === 'today') {
            $query->where('occurred_at', '>=', Carbon::today());
        } elseif ($timeRange === 'last_24h') {
            $query->where('occurred_at', '>=', Carbon::now()->subDay());
        } elseif ($timeRange === 'last_7d') {
            $query->where('occurred_at', '>=', Carbon::now()->subDays(7));
        } elseif ($timeRange === 'current_shift') {
            // Shift convention is plant-local; without a stored shift
            // table, surface a 12h window as a sane default.
            $query->where('occurred_at', '>=', Carbon::now()->subHours(12));
        }
    }

    protected function prefixesForJournalCategory(string $category): array
    {
        return match ($category) {
            EventJournalCategory::GATE_ACCESS => ['gate', 'gate_terminal', 'access', 'identification'],
            EventJournalCategory::DRIVER_TERMINAL => ['driver_terminal', 'terminal', 'terminal_workflow', 'terminal_training'],
            EventJournalCategory::FILLING_STATION => ['filling', 'filling_station', 'bay_line'],
            EventJournalCategory::LOADING => ['loading', 'loading_order', 'loading_control', 'plant_visit', 'clarification_case'],
            EventJournalCategory::ANALYSIS => ['analysis', 'active_analysis', 'analysis_device', 'product_spec', 'calibration_profile'],
            EventJournalCategory::DOCUMENT_PRINT => ['document', 'operational_document', 'document_print_attempt', 'report', 'printer_job'],
            EventJournalCategory::DEVICE_COMMUNICATION => ['hardware_device', 'interface_health', 'plc', 'opc_ua'],
            EventJournalCategory::INTERFACE_SAP => ['sap', 'sap_sync', 'interface'],
            EventJournalCategory::SECURITY => [], // Excluded from default subview.
            EventJournalCategory::AUDIT_REFERENCE => ['audit', 'audit_log'],
            EventJournalCategory::SYSTEM => ['system', 'scheduled_job', 'health'],
            default => [],
        };
    }

    protected function storedCategoryForJournalCategory(string $category): ?string
    {
        return match ($category) {
            EventJournalCategory::LOADING,
            EventJournalCategory::GATE_ACCESS,
            EventJournalCategory::DRIVER_TERMINAL,
            EventJournalCategory::FILLING_STATION => EventCategory::OPERATIONS,
            EventJournalCategory::ANALYSIS => EventCategory::QUALITY,
            EventJournalCategory::DOCUMENT_PRINT => EventCategory::DOCUMENT,
            EventJournalCategory::DEVICE_COMMUNICATION => EventCategory::DEVICE,
            EventJournalCategory::INTERFACE_SAP => EventCategory::INTEGRATION,
            EventJournalCategory::SYSTEM => EventCategory::SYSTEM,
            default => null,
        };
    }

    protected function storedCategoryAll(): array
    {
        return [
            EventCategory::OPERATIONS,
            EventCategory::INTEGRATION,
            EventCategory::SECURITY,
            EventCategory::SYSTEM,
            EventCategory::QUALITY,
            EventCategory::DOCUMENT,
            EventCategory::DEVICE,
        ];
    }

    protected function tailPatternsForResult(string $result): array
    {
        return match ($result) {
            EventJournalResult::DENIED => ['denied'],
            EventJournalResult::TIMEOUT => ['timeout'],
            EventJournalResult::BLOCKED => ['blocked'],
            EventJournalResult::FAILED => ['failed', 'failure'],
            EventJournalResult::COMPLETED => ['completed', 'closed', 'finished'],
            EventJournalResult::STARTED => ['started', 'opened', 'queued'],
            EventJournalResult::STOPPED => ['stopped', 'paused', 'cancelled'],
            EventJournalResult::SUCCESS => ['success', 'accepted', 'granted'],
            default => [],
        };
    }
}
