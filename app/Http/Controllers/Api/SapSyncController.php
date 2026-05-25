<?php

namespace App\Http\Controllers\Api;

use App\Enums\SapHandlingStatus;
use App\Enums\SapSyncResultStatus;
use App\Http\Resources\SapSyncRecordDetailResource;
use App\Http\Resources\SapSyncRecordResource;
use App\Models\SapSyncRecord;
use App\Services\ApiResponse;
use Illuminate\Http\Request;

/**
 * SAP Sync / Order Import Status — V1.5 read-only diagnostic.
 *
 * MVP scope per V1.5 §2.2 is STRICTLY read-only:
 *   - no manual retry endpoint (§4.2 / §13 "No retry action by default")
 *   - no order creation / editing / assignment (lives in Loading Orders)
 *   - no quality decisions / document printing / SAP config editing
 *   - no bulk endpoints, no DELETE, no POST in this slice at all
 *
 * Audit constants `App\Enums\AuditAction::SAP_SYNC_*` are reserved for the
 * future write surface (manual retry, mark-resolved) but are NEVER emitted
 * by this controller — that's a deliberate forward contract, not a TODO.
 */
class SapSyncController extends ApiController
{
    public const SORT_WHITELIST = [
        'event_time',
        'last_attempt_at',
        'result_status',
        'direction',
        'sap_reference',
    ];

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = SapSyncRecord::query();
        $this->applyFilters($query, $request->all());

        // Sort whitelist (prefix `-` = desc; default `-event_time`).
        $sort = (string) $request->query('sort', '-event_time');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (! in_array($column, self::SORT_WHITELIST, true)) {
            $column = 'event_time';
            $direction = 'desc';
        }

        $paginator = $query->orderBy($column, $direction)
            // Stable tiebreaker so a draw on event_time doesn't reorder
            // across paginator calls.
            ->orderBy('id')
            ->paginate($perPage);

        $rows = SapSyncRecordResource::collection($paginator->items());

        $summary = $this->summary();
        $lastUpdated = SapSyncRecord::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'SAP sync records retrieved');
    }

    public function show($id)
    {
        $record = SapSyncRecord::find($id);
        if (! $record) {
            return $this->error('SAP sync record not found', 'SAP_SYNC_RECORD_NOT_FOUND', 404);
        }

        return $this->success(new SapSyncRecordDetailResource($record), 'SAP sync record retrieved');
    }

    // ---------- helpers ----------

    /**
     * Apply V1.5 §8.5 / §8.6 filters. All filters are optional; absence means
     * "no constraint". Global `search` covers SAP reference / order_no /
     * customer / carrier / issue text / feedback type.
     */
    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('sap_reference', 'like', $search)
                    ->orWhere('order_no', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('carrier_name', 'like', $search)
                    ->orWhere('issue_reason', 'like', $search)
                    ->orWhere('feedback_type', 'like', $search);
            });
        }

        foreach ([
            'direction', 'result_status', 'handling_status', 'feedback_type',
            'owner_role', 'customer_id', 'carrier_id', 'interface_id', 'sync_run_id',
        ] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        // Date range on event_time (V1.5 §8.5).
        if (! empty($filters['event_from'])) {
            $query->where('event_time', '>=', $filters['event_from']);
        }
        if (! empty($filters['event_to'])) {
            $query->where('event_time', '<=', $filters['event_to']);
        }
    }

    /**
     * Summary counts (V1.5 §2.3) — all computed against the FULL table, not
     * the filtered set, so the overview header stays stable while users
     * filter the list (per 00-conventions §4.3).
     */
    protected function summary(): array
    {
        $base = SapSyncRecord::query();

        $byResult = (clone $base)
            ->selectRaw('result_status, COUNT(*) as c')
            ->groupBy('result_status')
            ->pluck('c', 'result_status')
            ->toArray();

        return [
            'total' => (clone $base)->count(),
            'success' => (int) ($byResult[SapSyncResultStatus::SUCCESS] ?? 0),
            'pending' => (int) ($byResult[SapSyncResultStatus::PENDING] ?? 0),
            'failed' => (int) ($byResult[SapSyncResultStatus::FAILED] ?? 0),
            'resolved' => (int) ($byResult[SapSyncResultStatus::RESOLVED] ?? 0),
            'needsSupport' => (clone $base)
                ->where('handling_status', SapHandlingStatus::NEEDS_SUPPORT)
                ->count(),
        ];
    }
}
