<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\ReportDateRangePreset;
use App\Enums\ReportId;
use App\Http\Requests\Reports\ExportReportRequest;
use App\Http\Resources\ReportHubItemResource;
use App\Services\ApiResponse;
use App\Services\Events\EventLogger;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\ReportsService;
use Illuminate\Http\Request;

/**
 * Reports module — read-only aggregator over already-built modules.
 *
 * Endpoints (all GET except export):
 *   GET  /api/documents-reports/reports                       — Hub (9 reports + availability + lastRefreshedAt)
 *   GET  /api/documents-reports/reports/{reportId}            — Full detail shape (§7)
 *   GET  /api/documents-reports/reports/{reportId}/drill-down — Paginated drill-down rows only
 *   POST /api/documents-reports/reports/{reportId}/export     — Sync export (JSON payload + report.exported event)
 *
 * Hard rules (V2.1 §2.1, §3, §9):
 *   - No POST/PUT/PATCH/DELETE on source modules from here.
 *   - No mutation of source tables.
 *   - No bulk endpoints, no row-selection.
 *   - Drill-down is a returned `routePath` string for the FE to navigate to.
 *   - `report.exported` event MUST fire on every successful export call.
 */
class ReportsController extends ApiController
{
    public function __construct(protected ReportsService $reports)
    {
    }

    /**
     * GET /api/documents-reports/reports — Reports Hub (§6).
     */
    public function hub()
    {
        $items = $this->reports->hub();

        $lastUpdated = collect($items)
            ->pluck('lastRefreshedAt')
            ->filter()
            ->max();

        return ApiResponse::list(
            ReportHubItemResource::collection($items),
            null,
            ['totalReports' => count($items)],
            $lastUpdated,
            __('masterdata.messages.reports_retrieved')
        );
    }

    /**
     * GET /api/documents-reports/reports/{reportId} — Full detail (§7).
     */
    public function show(Request $request, string $reportId)
    {
        $canonical = ReportId::normaliseId($reportId);
        if ($canonical === null) {
            return $this->error(__('masterdata.messages.report_not_found'), 'REPORT_NOT_FOUND', 404, [
                'allowed' => ReportId::all(),
            ]);
        }

        $meta = ReportRegistry::find($canonical);
        $range = ReportDateRangePreset::resolve(
            $request->query('range_preset'),
            $request->query('range_from'),
            $request->query('range_to')
        );
        $filters = $this->extractFilters($request);

        // Restricted / not-ready reports still resolve so the FE can render
        // an empty-state with the placeholder reason rather than 404.
        $payload = $this->reports->build($canonical, $range, $filters);

        return $this->success(
            $payload,
            __('masterdata.messages.report_retrieved'),
            [
                'dataSourceAvailable' => (bool) ($meta['dataSourceAvailable'] ?? true),
                'placeholderReason' => $meta['placeholderReason'] ?? null,
                'availability' => $meta['availability'] ?? 'available',
            ]
        );
    }

    /**
     * GET /api/documents-reports/reports/{reportId}/drill-down — Paginated
     * drill-down rows only (for FE that wants to refresh just the table
     * after a filter change without re-fetching the full detail payload).
     */
    public function drillDown(Request $request, string $reportId)
    {
        $canonical = ReportId::normaliseId($reportId);
        if ($canonical === null) {
            return $this->error(__('masterdata.messages.report_not_found'), 'REPORT_NOT_FOUND', 404);
        }

        $range = ReportDateRangePreset::resolve(
            $request->query('range_preset'),
            $request->query('range_from'),
            $request->query('range_to')
        );
        $filters = $this->extractFilters($request);
        $perPage = (int) $request->query('per_page', 50);
        $page = (int) $request->query('page', 1);

        $result = $this->reports->drillDown($canonical, $range, $filters, $perPage, $page);

        $lastUpdated = $this->reports->lastRefreshedFor($canonical);

        return ApiResponse::list(
            $result['rows'],
            [
                'current_page' => $result['page'],
                'per_page' => $result['perPage'],
                'total' => $result['total'],
                'last_page' => max(1, (int) ceil($result['total'] / max(1, $result['perPage']))),
            ],
            null,
            $lastUpdated,
            __('masterdata.messages.report_drill_down')
        );
    }

    /**
     * POST /api/documents-reports/reports/{reportId}/export — Sync export.
     *
     * MVP returns the report payload as JSON with `format` echoed back and
     * fires a `report.exported` event. Binary XLSX/PDF is deferred to the
     * Master Data Export job runner; the FE flag-driven download flow uses
     * `format=json` today and will switch when the renderers land.
     */
    public function export(ExportReportRequest $request, string $reportId, EventLogger $events)
    {
        $canonical = ReportId::normaliseId($reportId);
        if ($canonical === null) {
            return $this->error(__('masterdata.messages.report_not_found'), 'REPORT_NOT_FOUND', 404);
        }

        $format = $request->input('format', 'json');
        $range = ReportDateRangePreset::resolve(
            $request->input('range_preset'),
            $request->input('range_from'),
            $request->input('range_to')
        );
        $filters = (array) $request->input('filters', []);

        $payload = $this->reports->build($canonical, $range, $filters);
        $meta = ReportRegistry::find($canonical);

        // Mandatory event-log row per spec §9 — exports must be traceable.
        $events->record(
            'report.exported',
            'report',
            "Report {$canonical} exported as {$format}",
            [
                'report_id' => $canonical,
                'format' => $format,
                'range_preset' => $range['preset'],
                'range_from' => $range['from']->toIso8601String(),
                'range_to' => $range['to']->toIso8601String(),
                'filters' => $filters,
                'row_count' => $payload['drillDownTotal'] ?? 0,
            ],
            EventCategory::OPERATIONS,
            EventSeverity::INFO,
            $canonical
        );

        return $this->success(
            [
                'format' => $format,
                'report' => $payload,
                'note' => $format === 'json'
                    ? 'Returning JSON payload — binary XLSX/PDF rendering not yet implemented for the per-report Export button (Master Data Export covers the multi-category background flow).'
                    : "Binary {$format} rendering not yet implemented; returning JSON payload for now.",
            ],
            __('masterdata.messages.report_export'),
            [
                'dataSourceAvailable' => (bool) ($meta['dataSourceAvailable'] ?? true),
                'placeholderReason' => $meta['placeholderReason'] ?? null,
            ]
        );
    }

    /**
     * Collect arbitrary `filters[*]` query params + scalar query params that
     * aren't reserved for range / sort / paging. Lets each report's
     * aggregator consume whatever filter keys it knows about and ignore
     * the rest, without the controller having to declare a union of every
     * report's filter set.
     */
    protected function extractFilters(Request $request): array
    {
        $filters = (array) $request->query('filters', []);
        if (! is_array($filters)) {
            $filters = [];
        }

        $reserved = ['range_preset', 'range_from', 'range_to', 'sort', 'page', 'per_page', 'filters'];
        foreach ($request->query() as $k => $v) {
            if (! in_array($k, $reserved, true) && ! array_key_exists($k, $filters)) {
                $filters[$k] = $v;
            }
        }

        return $filters;
    }
}
