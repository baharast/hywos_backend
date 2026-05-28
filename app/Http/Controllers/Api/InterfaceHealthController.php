<?php

namespace App\Http\Controllers\Api;

use App\Enums\InterfaceBlockingLevel;
use App\Enums\InterfaceFamily;
use App\Enums\InterfaceStatus;
use App\Http\Requests\InterfaceHealth\RequestInterfaceRetryRequest;
use App\Http\Resources\InterfaceHealthDetailResource;
use App\Http\Resources\InterfaceHealthListResource;
use App\Models\AuditLog;
use App\Models\InterfaceHealth;
use App\Services\ApiResponse;
use App\Services\InterfaceHealth\InterfaceHealthService;
use Illuminate\Http\Request;

/**
 * V1.4 §9 Interface Health.
 *
 * Read-mostly: list, detail, and one safe write (manual retry request).
 * The retry endpoint stamps a timestamp + writes audit/event — real
 * connector orchestration is out of scope (V1.4 §10 forbids
 * write/start/stop of underlying connectors from this surface).
 */
class InterfaceHealthController extends ApiController
{
    public function __construct(protected InterfaceHealthService $service) {}

    /**
     * GET /api/interface-health
     *
     * Default sort per V1.4 §9: Fault/Critical first, then Operational
     * impact, then oldest unresolved. We translate that into:
     *   ORDER BY InterfaceStatus::displayPriority ASC,
     *            InterfaceBlockingLevel::displayPriority ASC,
     *            last_failure_at DESC NULLS LAST
     * via in-memory sort (the table is small — 11 demo rows + low
     * growth). Pagination still works because we paginate the result.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = InterfaceHealth::query();

        $this->applyFilters($query, $request);

        // Snapshot the base (filtered) query for the summary BEFORE
        // pagination so facet counts reflect the filtered set.
        $summary = $this->service->buildSummary(clone $query);

        // Sorting. The `sort` param accepts a comma-separated list; we
        // honour the first column and direction. If `sort` is the
        // default tag, we apply the V1.4 §9 priority sort.
        $sort = (string) $request->query('sort', '-last_failure_at,-last_success_at');
        $usingDefault = $this->isDefaultSort($sort);

        if ($usingDefault) {
            $rows = $query->get()->sort(function (InterfaceHealth $a, InterfaceHealth $b) {
                $pa = InterfaceStatus::displayPriority($a->status);
                $pb = InterfaceStatus::displayPriority($b->status);
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }
                $ba = InterfaceBlockingLevel::displayPriority($a->blocking_level);
                $bb = InterfaceBlockingLevel::displayPriority($b->blocking_level);
                if ($ba !== $bb) {
                    return $ba <=> $bb;
                }
                // last_failure_at DESC NULLS LAST — newest pain first
                $fa = $a->last_failure_at?->getTimestamp() ?? 0;
                $fb = $b->last_failure_at?->getTimestamp() ?? 0;
                return $fb <=> $fa;
            })->values();

            // Manual pagination on the sorted collection
            $page = (int) $request->query('page', 1);
            $total = $rows->count();
            $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

            $resourceRows = InterfaceHealthListResource::collection($items);
            $lastUpdated = max(
                InterfaceHealth::query()->max('last_success_at'),
                InterfaceHealth::query()->max('last_failure_at')
            );

            return ApiResponse::list(
                $resourceRows,
                [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
                    'first_visible_row' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                    'last_visible_row' => min($page * $perPage, $total),
                ],
                $summary,
                $lastUpdated,
                'Interfaces retrieved'
            );
        }

        // Whitelisted column sort
        [$column, $direction] = $this->parseSort($sort);
        $paginator = $query->orderBy($column, $direction)->paginate($perPage);

        $rows = InterfaceHealthListResource::collection($paginator->items());
        $lastUpdated = max(
            InterfaceHealth::query()->max('last_success_at'),
            InterfaceHealth::query()->max('last_failure_at')
        );

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'Interfaces retrieved');
    }

    public function show(string $id)
    {
        $interface = InterfaceHealth::find($id);
        if (! $interface) {
            return $this->error('Interface not found', 'INTERFACE_NOT_FOUND', 404);
        }

        $base = (new InterfaceHealthListResource($interface))->toArray(request());

        $audits = AuditLog::query()
            ->where('entity_type', $interface->getMorphClass())
            ->where('entity_id', (string) $interface->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $detail = array_merge($base, [
            'fallbackBehavior' => $interface->fallback_behavior,
            'lastRetryByUserId' => $interface->last_retry_by_user_id,
            'suggestedRoutes' => $this->suggestedRoutesFor($interface),
            'recentAuditEvents' => $audits,
        ]);

        return $this->success(
            new InterfaceHealthDetailResource($detail),
            'Interface retrieved'
        );
    }

    /**
     * POST /api/interface-health/{id}/retry
     *
     * Reason-gated demo stub. 409 on Mail/Notification (no retry surface
     * defined in V1.4), 422 on `disabled` (deliberate stop — operator
     * must re-enable first).
     */
    public function retry(RequestInterfaceRetryRequest $request, string $id)
    {
        $interface = InterfaceHealth::find($id);
        if (! $interface) {
            return $this->error('Interface not found', 'INTERFACE_NOT_FOUND', 404);
        }

        if ($interface->family === InterfaceFamily::MAIL_NOTIFICATION) {
            return $this->error(
                'Retry is not supported for mail / notification interfaces',
                'RETRY_NOT_SUPPORTED',
                409,
                ['family' => $interface->family]
            );
        }

        if ($interface->status === InterfaceStatus::DISABLED) {
            return $this->error(
                'Interface is disabled — re-enable it before retrying.',
                'INTERFACE_DISABLED',
                422,
                ['status' => $interface->status]
            );
        }

        $fresh = $this->service->requestRetry($interface, $request->validated()['reason']);

        return $this->success(
            new InterfaceHealthListResource($fresh),
            'Retry requested'
        );
    }

    /* ---------- helpers ---------- */

    protected function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $s = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($s) {
                $q->where('exact_interface_id', 'like', $s)
                    ->orWhere('name', 'like', $s)
                    ->orWhere('source_label', 'like', $s)
                    ->orWhere('target_label', 'like', $s)
                    ->orWhere('last_error_text', 'like', $s);
            });
        }
        foreach (['family', 'status', 'blocking_level', 'direction', 'protocol'] as $col) {
            if ($request->filled($col)) {
                $query->where($col, $request->query($col));
            }
        }
    }

    /**
     * Treat any of the default forms as "use the V1.4 §9 priority sort".
     */
    protected function isDefaultSort(string $sort): bool
    {
        $sort = trim($sort);
        return $sort === ''
            || $sort === 'default'
            || $sort === '-last_failure_at,-last_success_at';
    }

    /**
     * Whitelist + parse. Bad columns silently fall back to default.
     *
     * @return array{0:string,1:string}
     */
    protected function parseSort(string $sort): array
    {
        // Only honour the FIRST column in a CSV — we don't compose
        // multi-column SQL ORDER BY out of arbitrary user input.
        $first = explode(',', $sort)[0] ?? '';
        $direction = str_starts_with($first, '-') ? 'desc' : 'asc';
        $column = ltrim($first, '-');

        $allowed = ['last_failure_at', 'last_success_at', 'exact_interface_id', 'status', 'blocking_level'];
        if (! in_array($column, $allowed, true)) {
            return ['last_failure_at', 'desc'];
        }
        return [$column, $direction];
    }

    /**
     * V1.4 §9 "Actions" column: open log / open affected page / export
     * diagnostics / notify owner. We surface them as a routes block so
     * the FE doesn't have to hard-code per-family deep links.
     */
    protected function suggestedRoutesFor(InterfaceHealth $interface): array
    {
        $auditQs = "?entity_type=InterfaceHealth&entity_id={$interface->id}";

        $base = [
            'openAuditTrail' => "/alarms-events/audit-trail{$auditQs}",
            'openEventJournal' => "/alarms-events/event-journal{$auditQs}",
        ];

        $base['openAffectedPage'] = match ($interface->family) {
            InterfaceFamily::SAP => '/orders-scheduling/sap-sync',
            InterfaceFamily::PLC_FIELDBUS => '/operations/loading-control',
            InterfaceFamily::ANALYSIS_INTERFACE => '/operations/analysis-devices',
            InterfaceFamily::GATE_ETHERNET => '/operations/gate-terminal-monitor',
            InterfaceFamily::PRINTER_SERVICE => '/documents-reports/operational-documents',
            InterfaceFamily::CLOUD_SYNC, InterfaceFamily::VPN_REMOTE_SUPPORT,
            InterfaceFamily::MAIL_NOTIFICATION => null,
            default => null,
        };

        return array_filter($base, fn ($v) => $v !== null);
    }
}
