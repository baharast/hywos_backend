<?php

namespace App\Services\Reports;

use App\Enums\ReportId;
use App\Models\AuditLog;
use App\Models\BayLine;
use App\Models\ClarificationCase;
use App\Models\EventLog;
use App\Models\Gate;
use App\Models\LoadingOperation;
use App\Models\LoadingOrder;
use App\Models\OperationalDocument;
use App\Models\PlantVisit;
use App\Models\SapSyncRecord;
use App\Models\TerminalPanel;
use App\Models\TerminalSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregation surface for the 9 MVP reports.
 *
 * One public `build*` method per report — each one returns a shape that
 * matches §11 / §7 of the Reports V2.1 spec:
 *   - selectedRange      (echo back from the resolver)
 *   - lastRefreshedAt    (now())
 *   - decisionStrip      (compact KPI tiles)
 *   - primaryVisualization (chart/timeline/breakdown/matrix payload)
 *   - drillDownRows + drillDownTotal
 *   - filters            (echo back applied filters)
 *
 * Reports never write. Source-table state mutations belong to their owning
 * modules. Missing source tables are flagged by ReportRegistry::all() and
 * surfaced via meta.dataSourceAvailable in the controller.
 */
class ReportsService
{
    public function __construct(protected ReportRegistry $registry)
    {
    }

    /**
     * Reports Hub list (§6) — every report's metadata + a per-report
     * lastRefreshedAt computed from the underlying source table when
     * available. The FE renders this as the compact selector.
     *
     * @return array<int, array<string, mixed>>
     */
    public function hub(): array
    {
        $items = ReportRegistry::all();
        foreach ($items as &$item) {
            $item['lastRefreshedAt'] = $this->lastRefreshedFor($item['id']);
        }
        return $items;
    }

    /**
     * Dispatch to the right per-report build method. Throws no exception
     * on unknown id — the controller already validates against ReportId
     * before getting here.
     */
    public function build(string $reportId, array $range, array $filters): array
    {
        return match ($reportId) {
            ReportId::DAILY_OPERATIONS => $this->buildDailyOperations($range, $filters),
            ReportId::LOADING_HISTORY => $this->buildLoadingHistory($range, $filters),
            ReportId::QUANTITY_THROUGHPUT => $this->buildQuantityThroughput($range, $filters),
            ReportId::STATION_UTILIZATION => $this->buildStationUtilization($range, $filters),
            ReportId::ANALYSIS_QUALITY => $this->buildAnalysisQuality($range, $filters),
            ReportId::DOCUMENTS_PRINT => $this->buildDocumentsPrint($range, $filters),
            ReportId::CLARIFICATIONS_ALARMS_AUDIT => $this->buildClarificationsAlarmsAudit($range, $filters),
            ReportId::GATE_ACCESS_EXIT => $this->buildGateAccessExit($range, $filters),
            ReportId::DEVICE_HEALTH => $this->buildDeviceHealth($range, $filters),
        };
    }

    /**
     * Drill-down rows isolated from the full detail payload — used by the
     * FE when it wants to refresh just the drill-down table after a filter
     * change without re-fetching the heavy visualization payload.
     */
    public function drillDown(string $reportId, array $range, array $filters, int $perPage = 50, int $page = 1): array
    {
        $full = $this->build($reportId, $range, $filters);
        $rows = $full['drillDownRows'] ?? [];
        $total = $full['drillDownTotal'] ?? count($rows);

        $offset = max(0, ($page - 1) * $perPage);
        return [
            'reportId' => $reportId,
            'rows' => array_slice($rows, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    // ===================================================================
    // REP-01 — Daily Operations
    // ===================================================================
    protected function buildDailyOperations(array $range, array $filters): array
    {
        [$from, $to] = [$range['from'], $range['to']];

        $loadingsCompleted = LoadingOperation::query()
            ->whereBetween('completed_at', [$from, $to])
            ->where('loading_status', 'completed')
            ->count();

        $loadingsInProgress = LoadingOperation::query()
            ->whereNotIn('loading_status', ['completed', 'failed', 'cancelled'])
            ->whereBetween('started_at', [$from, $to])
            ->count();

        $openBlockers = ClarificationCase::query()
            ->where('is_blocking', true)
            ->whereIn('status', ['open', 'in_review', 'waiting_for_owner'])
            ->count();

        $documentsBlocked = OperationalDocument::query()
            ->where('is_exit_blocking', true)
            ->whereIn('lifecycle_status', ['blocked', 'print_failed', 'pending'])
            ->count();

        $deviceFaults = TerminalSession::query()
            ->where('session_state', 'device_fault')
            ->count();

        $sapFailed = SapSyncRecord::query()
            ->where('result_status', 'failed')
            ->whereBetween('event_time', [$from, $to])
            ->count();

        return [
            'reportId' => ReportId::DAILY_OPERATIONS,
            'title' => 'Daily Operations Report',
            'purpose' => 'Shows if the day is normal or blocked.',
            'selectedRange' => $this->shapeRange($range),
            'lastRefreshedAt' => now()->toIso8601String(),
            'decisionStrip' => $this->stripCompact([
                ['label' => 'Completed loadings', 'value' => $loadingsCompleted, 'tone' => 'success'],
                ['label' => 'Loadings in progress', 'value' => $loadingsInProgress, 'tone' => 'info'],
                ['label' => 'Open blockers', 'value' => $openBlockers, 'tone' => $openBlockers ? 'danger' : 'success'],
                ['label' => 'Blocking documents', 'value' => $documentsBlocked, 'tone' => $documentsBlocked ? 'orange' : 'success'],
                ['label' => 'Device faults', 'value' => $deviceFaults, 'tone' => $deviceFaults ? 'danger' : 'success'],
                ['label' => 'SAP sync failures', 'value' => $sapFailed, 'tone' => $sapFailed ? 'danger' : 'success'],
            ]),
            'primaryVisualization' => [
                'type' => 'breakdown',
                'data' => [
                    'output' => [
                        ['key' => 'completed', 'label' => 'Completed', 'value' => $loadingsCompleted, 'tone' => 'success'],
                        ['key' => 'in_progress', 'label' => 'In Progress', 'value' => $loadingsInProgress, 'tone' => 'info'],
                    ],
                    'blockers' => [
                        ['key' => 'clarifications', 'label' => 'Open clarifications', 'value' => $openBlockers, 'tone' => 'orange'],
                        ['key' => 'documents', 'label' => 'Exit-blocking documents', 'value' => $documentsBlocked, 'tone' => 'orange'],
                        ['key' => 'devices', 'label' => 'Device faults', 'value' => $deviceFaults, 'tone' => 'danger'],
                        ['key' => 'sap', 'label' => 'SAP failures', 'value' => $sapFailed, 'tone' => 'danger'],
                    ],
                ],
            ],
            'drillDownRows' => $this->dailyOperationsDrillRows($from, $to),
            'drillDownTotal' => LoadingOperation::query()
                ->whereBetween('completed_at', [$from, $to])
                ->where('loading_status', 'completed')
                ->count(),
            'filters' => $filters,
        ];
    }

    protected function dailyOperationsDrillRows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return LoadingOperation::query()
            ->whereBetween('completed_at', [$from, $to])
            ->where('loading_status', 'completed')
            ->orderByDesc('completed_at')
            ->limit(100)
            ->get()
            ->map(fn (LoadingOperation $op) => [
                'id' => $op->id,
                'displayNo' => $op->display_no,
                'orderNo' => $op->order_no,
                'customerName' => $op->customer_name,
                'productQuality' => $op->product_quality,
                'targetQuantity' => $op->target_quantity !== null ? (float) $op->target_quantity : null,
                'actualQuantity' => $op->actual_quantity !== null ? (float) $op->actual_quantity : null,
                'unit' => $op->unit,
                'completedAt' => $op->completed_at?->toIso8601String(),
                'routePath' => $op->order_id ? "/operations/loading-orders/{$op->order_id}" : null,
            ])
            ->all();
    }

    // ===================================================================
    // REP-02 — Loading History / Order Execution
    // ===================================================================
    protected function buildLoadingHistory(array $range, array $filters): array
    {
        [$from, $to] = [$range['from'], $range['to']];

        $query = LoadingOrder::query();
        if (! empty($filters['order_search'])) {
            $term = '%' . $filters['order_search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('order_no', 'like', $term)
                    ->orWhere('sap_reference', 'like', $term)
                    ->orWhere('external_reference', 'like', $term);
            });
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['carrier_id'])) {
            $query->where('carrier_id', $filters['carrier_id']);
        }
        if (! empty($filters['driver_id'])) {
            $query->where('assigned_driver_id', $filters['driver_id']);
        }
        if (! empty($filters['trailer_id'])) {
            $query->where('assigned_trailer_id', $filters['trailer_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $query->whereBetween('created_at', [$from, $to]);

        $orders = $query->orderByDesc('created_at')->limit(200)->get();

        $rows = $orders->map(function (LoadingOrder $o) {
            $doc = OperationalDocument::query()
                ->where('order_id', $o->id)
                ->orderByDesc('generated_at')
                ->first();
            $visit = $o->active_plant_visit_id
                ? PlantVisit::query()->find($o->active_plant_visit_id)
                : null;

            return [
                'orderId' => $o->id,
                'orderNo' => $o->order_no,
                'sapOrderNumber' => $o->sap_reference,
                'customerId' => $o->customer_id,
                'customerName' => $o->customer_name,
                'carrierId' => $o->carrier_id,
                'carrierName' => $o->carrier_name,
                'plantVisitId' => $visit?->id,
                'plantVisitNo' => $visit?->visit_no,
                'driverId' => $o->assigned_driver_id,
                'driverName' => $o->assigned_driver_name,
                'tractorPlate' => $visit?->tractor_plate,
                'trailerId' => $o->assigned_trailer_id,
                'trailerIdentifier' => $o->assigned_trailer_label ?? $o->assigned_trailer_plate,
                'qualitySpec' => $o->product_quality,
                'targetQuantity' => $o->target_quantity !== null ? (float) $o->target_quantity : null,
                'actualQuantity' => $visit?->order_snapshot['actual_loaded_quantity'] ?? null,
                'status' => $o->status,
                'documentStatus' => $doc?->lifecycle_status ?? 'none',
                'exitStatus' => $visit?->exit_time ? 'exited' : ($visit?->visit_status ?? 'open'),
                'routePath' => "/operations/loading-orders/{$o->id}",
            ];
        })->all();

        return [
            'reportId' => ReportId::LOADING_HISTORY,
            'title' => 'Loading History / Order Execution Report',
            'purpose' => 'Full traceability for one order/loading/plant visit from SAP order to exit.',
            'selectedRange' => $this->shapeRange($range),
            'lastRefreshedAt' => now()->toIso8601String(),
            'decisionStrip' => $this->stripCompact([
                ['label' => 'Orders in range', 'value' => $orders->count(), 'tone' => 'info'],
                ['label' => 'Completed', 'value' => $orders->where('status', 'completed')->count(), 'tone' => 'success'],
                ['label' => 'Blocked', 'value' => $orders->where('status', 'blocked')->count(), 'tone' => 'danger'],
                ['label' => 'Cancelled', 'value' => $orders->where('status', 'cancelled')->count(), 'tone' => 'offline'],
            ]),
            'primaryVisualization' => [
                'type' => 'timeline',
                'data' => [
                    'note' => 'One vertical timeline per drill-down row; FE composes the per-order timeline from documents, visits and loading_operations using the routePath links.',
                ],
            ],
            'drillDownRows' => $rows,
            'drillDownTotal' => $orders->count(),
            'filters' => $filters,
        ];
    }

    // ===================================================================
    // REP-03 — Quantity & Throughput
    // ===================================================================
    protected function buildQuantityThroughput(array $range, array $filters): array
    {
        [$from, $to] = [$range['from'], $range['to']];

        $query = LoadingOperation::query()
            ->whereBetween('completed_at', [$from, $to])
            ->where('loading_status', 'completed');

        if (! empty($filters['product_quality'])) {
            $query->where('product_quality', $filters['product_quality']);
        }
        if (! empty($filters['bay_line_id'])) {
            $query->where('bay_line_id', $filters['bay_line_id']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        $totalActual = (float) (clone $query)->sum('actual_quantity');
        $totalTarget = (float) (clone $query)->sum('target_quantity');
        $loadingCount = (clone $query)->count();

        // Trend: group by completed date.
        $trend = (clone $query)
            ->select(DB::raw('DATE(completed_at) as day'), DB::raw('SUM(actual_quantity) as actual'), DB::raw('COUNT(*) as loadings'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->day,
                'actual' => (float) $row->actual,
                'loadings' => (int) $row->loadings,
            ])->all();

        // Drill-down: per (quality, customer, station)
        $rows = (clone $query)
            ->select(
                'product_quality',
                'customer_id',
                'customer_name',
                'bay_line_id',
                DB::raw('SUM(actual_quantity) as actual_total'),
                DB::raw('SUM(target_quantity) as target_total'),
                DB::raw('COUNT(*) as loading_count'),
                DB::raw('MAX(unit) as unit')
            )
            ->groupBy('product_quality', 'customer_id', 'customer_name', 'bay_line_id')
            ->orderByDesc('actual_total')
            ->limit(200)
            ->get()
            ->map(fn ($row) => [
                'productQuality' => $row->product_quality,
                'customerId' => $row->customer_id,
                'customerName' => $row->customer_name,
                'bayLineId' => $row->bay_line_id,
                'actualTotal' => (float) $row->actual_total,
                'targetTotal' => (float) $row->target_total,
                'loadingCount' => (int) $row->loading_count,
                'unit' => $row->unit ?? 'kg',
            ])->all();

        return [
            'reportId' => ReportId::QUANTITY_THROUGHPUT,
            'title' => 'Quantity & Throughput Report',
            'purpose' => 'Shows loaded volume, throughput and segment durations.',
            'selectedRange' => $this->shapeRange($range),
            'lastRefreshedAt' => now()->toIso8601String(),
            'decisionStrip' => $this->stripCompact([
                ['label' => 'Total loaded', 'value' => $totalActual, 'unit' => 'kg', 'tone' => 'success'],
                ['label' => 'Target', 'value' => $totalTarget, 'unit' => 'kg', 'tone' => 'neutral'],
                ['label' => 'Completed loadings', 'value' => $loadingCount, 'tone' => 'info'],
                ['label' => 'Avg per loading', 'value' => $loadingCount > 0 ? round($totalActual / $loadingCount, 2) : 0, 'unit' => 'kg', 'tone' => 'neutral'],
            ]),
            'primaryVisualization' => [
                'type' => 'trend',
                'data' => [
                    'series' => $trend,
                    'xKey' => 'date',
                    'yKey' => 'actual',
                ],
            ],
            'drillDownRows' => $rows,
            'drillDownTotal' => count($rows),
            'filters' => $filters,
        ];
    }

    // ===================================================================
    // REP-04 — Filling Station Utilization
    // ===================================================================
    protected function buildStationUtilization(array $range, array $filters): array
    {
        [$from, $to] = [$range['from'], $range['to']];

        $stations = BayLine::query()->where('is_active', true)->get();
        $rows = [];

        foreach ($stations as $bay) {
            $opQuery = LoadingOperation::query()
                ->where('bay_line_id', $bay->id)
                ->whereBetween('started_at', [$from, $to]);

            $loadingCount = (clone $opQuery)->count();
            $completed = (clone $opQuery)->where('loading_status', 'completed')->count();
            $failed = (clone $opQuery)->where('loading_status', 'failed')->count();
            $totalQty = (float) (clone $opQuery)->sum('actual_quantity');

            $rows[] = [
                'bayLineId' => $bay->id,
                'bayLineCode' => $bay->code,
                'bayLineName' => $bay->name,
                'currentStatus' => $bay->status_code,
                'loadingCount' => $loadingCount,
                'completedCount' => $completed,
                'failedCount' => $failed,
                'totalLoadedQuantity' => $totalQty,
                'routePath' => "/operations/loading-control?bay_line_id={$bay->id}",
            ];
        }

        usort($rows, fn ($a, $b) => $b['loadingCount'] <=> $a['loadingCount']);

        $totalLoadings = array_sum(array_column($rows, 'loadingCount'));
        $totalFailures = array_sum(array_column($rows, 'failedCount'));

        return [
            'reportId' => ReportId::STATION_UTILIZATION,
            'title' => 'Filling Station Utilization Report',
            'purpose' => 'Compares station load, idle time and fault time.',
            'selectedRange' => $this->shapeRange($range),
            'lastRefreshedAt' => now()->toIso8601String(),
            'decisionStrip' => $this->stripCompact([
                ['label' => 'Active stations', 'value' => count($rows), 'tone' => 'info'],
                ['label' => 'Total loadings', 'value' => $totalLoadings, 'tone' => 'success'],
                ['label' => 'Failed loadings', 'value' => $totalFailures, 'tone' => $totalFailures ? 'danger' : 'success'],
            ]),
            'primaryVisualization' => [
                'type' => 'bars',
                'data' => [
                    'series' => array_map(fn ($r) => [
                        'label' => $r['bayLineCode'],
                        'value' => $r['loadingCount'],
                        'tone' => $r['failedCount'] > 0 ? 'orange' : 'info',
                    ], $rows),
                ],
            ],
            'drillDownRows' => $rows,
            'drillDownTotal' => count($rows),
            'filters' => $filters,
        ];
    }

    // ===================================================================
    // REP-05 — Analysis & Quality   (data source not implemented yet)
    // ===================================================================
    protected function buildAnalysisQuality(array $range, array $filters): array
    {
        return [
            'reportId' => ReportId::ANALYSIS_QUALITY,
            'title' => 'Analysis & Quality Report',
            'purpose' => 'Controls quality decisions and blocked analysis cases.',
            'selectedRange' => $this->shapeRange($range),
            'lastRefreshedAt' => now()->toIso8601String(),
            'decisionStrip' => $this->stripCompact([
                ['label' => 'Analyses in range', 'value' => 0, 'tone' => 'neutral'],
                ['label' => 'Blocking decisions', 'value' => 0, 'tone' => 'neutral'],
            ]),
            'primaryVisualization' => [
                'type' => 'breakdown',
                'data' => ['note' => 'Analysis & Quality module not yet implemented.'],
            ],
            'drillDownRows' => [],
            'drillDownTotal' => 0,
            'filters' => $filters,
        ];
    }

    // ===================================================================
    // REP-06 — Documents & Print
    // ===================================================================
    protected function buildDocumentsPrint(array $range, array $filters): array
    {
        [$from, $to] = [$range['from'], $range['to']];

        $query = OperationalDocument::query()
            ->whereBetween('created_at', [$from, $to]);

        if (! empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }
        if (! empty($filters['print_status'])) {
            $query->where('print_status', $filters['print_status']);
        }
        if (array_key_exists('exit_blocking', $filters)) {
            $query->where('is_exit_blocking', filter_var($filters['exit_blocking'], FILTER_VALIDATE_BOOLEAN));
        }

        $generated = (clone $query)->where('lifecycle_status', '!=', 'pending')->count();
        $printed = (clone $query)->where('lifecycle_status', 'printed')->count();
        $reprinted = (clone $query)->where('reprint_count', '>', 0)->count();
        $handedOver = (clone $query)->whereNotNull('handed_over_at')->count();
        $failed = (clone $query)->where('lifecycle_status', 'print_failed')->count();
        $exitBlocking = (clone $query)->where('is_exit_blocking', true)
            ->whereIn('lifecycle_status', ['pending', 'blocked', 'print_failed'])
            ->count();

        $rows = (clone $query)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (OperationalDocument $d) => [
                'documentId' => $d->id,
                'documentNo' => $d->document_no,
                'documentType' => $d->document_type,
                'lifecycleStatus' => $d->lifecycle_status,
                'printStatus' => $d->print_status,
                'isExitBlocking' => (bool) $d->is_exit_blocking,
                'blockingReason' => $d->blocking_reason,
                'reprintCount' => (int) $d->reprint_count,
                'orderNo' => $d->order_no,
                'driverName' => $d->driver_name,
                'printerName' => $d->printer_name,
                'generatedAt' => $d->generated_at?->toIso8601String(),
                'printedAt' => $d->printed_at?->toIso8601String(),
                'handedOverAt' => $d->handed_over_at?->toIso8601String(),
                'routePath' => "/documents-reports/operational-documents/{$d->id}",
            ])->all();

        return [
            'reportId' => ReportId::DOCUMENTS_PRINT,
            'title' => 'Documents & Print Report',
            'purpose' => 'Shows generated, printed, reprinted, handed-over or failed mandatory documents.',
            'selectedRange' => $this->shapeRange($range),
            'lastRefreshedAt' => now()->toIso8601String(),
            'decisionStrip' => $this->stripCompact([
                ['label' => 'Generated', 'value' => $generated, 'tone' => 'info'],
                ['label' => 'Printed', 'value' => $printed, 'tone' => 'success'],
                ['label' => 'Reprinted', 'value' => $reprinted, 'tone' => 'warning'],
                ['label' => 'Handed over', 'value' => $handedOver, 'tone' => 'success'],
                ['label' => 'Print failures', 'value' => $failed, 'tone' => $failed ? 'danger' : 'success'],
                ['label' => 'Exit-blocking', 'value' => $exitBlocking, 'tone' => $exitBlocking ? 'orange' : 'success'],
            ]),
            'primaryVisualization' => [
                'type' => 'breakdown',
                'data' => [
                    'lifecycle' => [
                        ['key' => 'generated', 'value' => $generated, 'tone' => 'info'],
                        ['key' => 'printed', 'value' => $printed, 'tone' => 'success'],
                        ['key' => 'reprinted', 'value' => $reprinted, 'tone' => 'warning'],
                        ['key' => 'handed_over', 'value' => $handedOver, 'tone' => 'success'],
                        ['key' => 'print_failed', 'value' => $failed, 'tone' => 'danger'],
                    ],
                ],
            ],
            'drillDownRows' => $rows,
            'drillDownTotal' => (clone $query)->count(),
            'filters' => $filters,
        ];
    }

    // ===================================================================
    // REP-07 — Clarifications + Alarms + Audit
    // ===================================================================
    protected function buildClarificationsAlarmsAudit(array $range, array $filters): array
    {
        [$from, $to] = [$range['from'], $range['to']];

        $cases = ClarificationCase::query()
            ->whereBetween('opened_at', [$from, $to]);

        if (! empty($filters['severity'])) {
            $cases->where('severity', $filters['severity']);
        }
        if (! empty($filters['status'])) {
            $cases->where('status', $filters['status']);
        }
        if (! empty($filters['owner_role'])) {
            $cases->where('owner_role', $filters['owner_role']);
        }

        $open = (clone $cases)->whereIn('status', ['open', 'in_review', 'waiting_for_owner'])->count();
        $resolved = (clone $cases)->where('status', 'resolved')->count();
        $closed = (clone $cases)->where('status', 'closed')->count();
        $critical = (clone $cases)->where('severity', 'critical')->count();

        // Manual-override audit count for the same window.
        $manualOverrides = AuditLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('action', ['plant_visit.force_closed', 'parking_slot.cleared', 'loading.failed'])
            ->count();

        // Recent audit + clarification rows for the drill-down tabs.
        $clarificationRows = (clone $cases)
            ->orderByDesc('opened_at')
            ->limit(100)
            ->get()
            ->map(fn (ClarificationCase $c) => [
                'tab' => 'clarifications',
                'id' => $c->id,
                'caseNo' => $c->case_no,
                'severity' => $c->severity,
                'status' => $c->status,
                'category' => $c->category,
                'ownerRole' => $c->owner_role,
                'openedAt' => $c->opened_at?->toIso8601String(),
                'title' => $c->title,
                'routePath' => "/operations/clarification-cases/{$c->id}",
            ])->all();

        $auditRows = AuditLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (AuditLog $a) => [
                'tab' => 'audit',
                'id' => $a->id,
                'action' => $a->action,
                'entityType' => $a->entity_type,
                'entityId' => $a->entity_id,
                'actorName' => $a->actor_name,
                'reasonCode' => $a->reason_code,
                'reason' => $a->reason,
                'createdAt' => $a->created_at?->toIso8601String(),
                'routePath' => null,
            ])->all();

        return [
            'reportId' => ReportId::CLARIFICATIONS_ALARMS_AUDIT,
            'title' => 'Clarification, Alarm & Audit Report',
            'purpose' => 'Controls exceptions, alarms, manual corrections and audited decisions.',
            'selectedRange' => $this->shapeRange($range),
            'lastRefreshedAt' => now()->toIso8601String(),
            'decisionStrip' => $this->stripCompact([
                ['label' => 'Open clarifications', 'value' => $open, 'tone' => $open ? 'orange' : 'success'],
                ['label' => 'Critical severity', 'value' => $critical, 'tone' => $critical ? 'danger' : 'success'],
                ['label' => 'Resolved', 'value' => $resolved, 'tone' => 'success'],
                ['label' => 'Closed', 'value' => $closed, 'tone' => 'neutral'],
                ['label' => 'Manual overrides', 'value' => $manualOverrides, 'tone' => $manualOverrides ? 'warning' : 'success'],
            ]),
            'primaryVisualization' => [
                'type' => 'tabs',
                'data' => [
                    'tabs' => [
                        ['key' => 'clarifications', 'label' => 'Clarifications', 'count' => count($clarificationRows)],
                        ['key' => 'alarms', 'label' => 'Alarms', 'count' => 0],
                        ['key' => 'manual_overrides', 'label' => 'Manual Overrides', 'count' => $manualOverrides],
                        ['key' => 'audit_events', 'label' => 'Audit Events', 'count' => count($auditRows)],
                    ],
                ],
            ],
            'drillDownRows' => array_merge($clarificationRows, $auditRows),
            'drillDownTotal' => count($clarificationRows) + count($auditRows),
            'filters' => $filters,
        ];
    }

    // ===================================================================
    // REP-08 — Gate Access & Exit
    // ===================================================================
    protected function buildGateAccessExit(array $range, array $filters): array
    {
        [$from, $to] = [$range['from'], $range['to']];

        $sessions = TerminalSession::query()
            ->whereBetween('last_activity_at', [$from, $to]);

        if (! empty($filters['touchpoint'])) {
            $sessions->where('touchpoint', $filters['touchpoint']);
        }
        if (! empty($filters['session_state'])) {
            $sessions->where('session_state', $filters['session_state']);
        }

        $entryCount = (clone $sessions)->where('touchpoint', 'entry_gate')->count();
        $exitCount = (clone $sessions)->where('touchpoint', 'exit_gate')->count();
        $entryDenied = (clone $sessions)->where('touchpoint', 'entry_gate')->where('session_state', 'denied')->count();
        $exitBlocked = (clone $sessions)->where('touchpoint', 'exit_gate')->whereIn('session_state', ['denied', 'needs_operator'])->count();

        // Denial reason breakdown from `issue_reason` text — top 10.
        $denials = (clone $sessions)
            ->where('session_state', 'denied')
            ->select('issue_reason', DB::raw('COUNT(*) as count'))
            ->groupBy('issue_reason')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['reason' => $r->issue_reason ?: 'Unknown', 'count' => (int) $r->count])
            ->all();

        $rows = (clone $sessions)
            ->orderByDesc('last_activity_at')
            ->limit(200)
            ->get()
            ->map(fn (TerminalSession $s) => [
                'sessionId' => $s->id,
                'sessionNo' => $s->session_no,
                'touchpoint' => $s->touchpoint,
                'sessionState' => $s->session_state,
                'driverName' => $s->driver_name,
                'plantVisitNo' => $s->visit_no,
                'orderNo' => $s->order_no,
                'trailerLabel' => $s->trailer_label,
                'issueReason' => $s->issue_reason,
                'actionNeeded' => $s->action_needed,
                'lastActivityAt' => $s->last_activity_at?->toIso8601String(),
                'routePath' => "/operations/gate-terminal-monitor/sessions/{$s->id}",
            ])->all();

        return [
            'reportId' => ReportId::GATE_ACCESS_EXIT,
            'title' => 'Gate Access & Exit Report',
            'purpose' => 'Shows entry/exit activity, denials and exit release decisions.',
            'selectedRange' => $this->shapeRange($range),
            'lastRefreshedAt' => now()->toIso8601String(),
            'decisionStrip' => $this->stripCompact([
                ['label' => 'Entry sessions', 'value' => $entryCount, 'tone' => 'info'],
                ['label' => 'Exit sessions', 'value' => $exitCount, 'tone' => 'info'],
                ['label' => 'Entry denied', 'value' => $entryDenied, 'tone' => $entryDenied ? 'danger' : 'success'],
                ['label' => 'Exit blocked', 'value' => $exitBlocked, 'tone' => $exitBlocked ? 'danger' : 'success'],
            ]),
            'primaryVisualization' => [
                'type' => 'breakdown',
                'data' => [
                    'denialReasons' => $denials,
                ],
            ],
            'drillDownRows' => $rows,
            'drillDownTotal' => (clone $sessions)->count(),
            'filters' => $filters,
        ];
    }

    // ===================================================================
    // REP-09 — Interface & Device Health   (inventory only; no fault history yet)
    // ===================================================================
    protected function buildDeviceHealth(array $range, array $filters): array
    {
        $bayLineCount = BayLine::query()->where('is_active', true)->count();
        $gateCount = Gate::query()->where('is_active', true)->count();
        $terminalCount = TerminalPanel::query()->where('is_active', true)->count();

        // Device-fault sessions still serve as the "active fault" surface
        // until a dedicated device_faults table lands.
        $activeFaults = TerminalSession::query()
            ->where('session_state', 'device_fault')
            ->count();

        $rows = TerminalSession::query()
            ->where('session_state', 'device_fault')
            ->orderByDesc('last_activity_at')
            ->limit(100)
            ->get()
            ->map(fn (TerminalSession $s) => [
                'deviceLabel' => $s->device_label,
                'touchpoint' => $s->touchpoint,
                'deviceHealth' => $s->device_health,
                'issueReason' => $s->issue_reason,
                'actionNeeded' => $s->action_needed,
                'lastActivityAt' => $s->last_activity_at?->toIso8601String(),
                'routePath' => "/operations/gate-terminal-monitor/sessions/{$s->id}",
            ])->all();

        return [
            'reportId' => ReportId::DEVICE_HEALTH,
            'title' => 'Interface & Device Health Report',
            'purpose' => 'Monitors technical components that can stop the process.',
            'selectedRange' => $this->shapeRange($range),
            'lastRefreshedAt' => now()->toIso8601String(),
            'decisionStrip' => $this->stripCompact([
                ['label' => 'Bay lines', 'value' => $bayLineCount, 'tone' => 'info'],
                ['label' => 'Gates', 'value' => $gateCount, 'tone' => 'info'],
                ['label' => 'Terminals', 'value' => $terminalCount, 'tone' => 'info'],
                ['label' => 'Active faults', 'value' => $activeFaults, 'tone' => $activeFaults ? 'danger' : 'success'],
            ]),
            'primaryVisualization' => [
                'type' => 'matrix',
                'data' => [
                    'inventory' => [
                        ['category' => 'Bay lines', 'count' => $bayLineCount],
                        ['category' => 'Gates', 'count' => $gateCount],
                        ['category' => 'Terminals', 'count' => $terminalCount],
                    ],
                    'note' => 'Per-device fault history not yet implemented; only currently-faulted terminal sessions are surfaced.',
                ],
            ],
            'drillDownRows' => $rows,
            'drillDownTotal' => count($rows),
            'filters' => $filters,
        ];
    }

    // ===================================================================
    // helpers
    // ===================================================================

    /**
     * Resolve the most-recent updated_at across whichever source tables
     * back the given report. Returns null when there are no rows.
     */
    public function lastRefreshedFor(string $reportId): ?string
    {
        $stamp = match ($reportId) {
            ReportId::DAILY_OPERATIONS => LoadingOperation::query()->max('updated_at'),
            ReportId::LOADING_HISTORY => LoadingOrder::query()->max('updated_at'),
            ReportId::QUANTITY_THROUGHPUT => LoadingOperation::query()->max('completed_at'),
            ReportId::STATION_UTILIZATION => LoadingOperation::query()->max('updated_at'),
            ReportId::ANALYSIS_QUALITY => null,
            ReportId::DOCUMENTS_PRINT => OperationalDocument::query()->max('updated_at'),
            ReportId::CLARIFICATIONS_ALARMS_AUDIT => ClarificationCase::query()->max('updated_at'),
            ReportId::GATE_ACCESS_EXIT => TerminalSession::query()->max('last_activity_at'),
            ReportId::DEVICE_HEALTH => TerminalSession::query()->max('updated_at'),
            default => null,
        };

        if ($stamp === null) {
            return null;
        }
        if ($stamp instanceof \DateTimeInterface) {
            return $stamp->format(\DateTimeInterface::ATOM);
        }
        return (string) $stamp;
    }

    protected function shapeRange(array $range): array
    {
        return [
            'preset' => $range['preset'],
            'from' => $range['from']->toIso8601String(),
            'to' => $range['to']->toIso8601String(),
        ];
    }

    /**
     * Normalise decisionStrip entries — every tile has value+label+tone,
     * optional unit. Coerce numeric values to int when whole, float otherwise.
     */
    protected function stripCompact(array $tiles): array
    {
        return array_map(function ($tile) {
            if (isset($tile['value']) && is_numeric($tile['value'])) {
                $f = (float) $tile['value'];
                $tile['value'] = $f == (int) $f ? (int) $f : round($f, 2);
            }
            $tile['tone'] = $tile['tone'] ?? 'neutral';
            return $tile;
        }, $tiles);
    }
}
