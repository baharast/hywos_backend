<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AuditTrailResource;
use App\Models\AuditLog;
use App\Services\ApiResponse;
use App\Services\AlarmsEvents\AuditTrailService;
use Illuminate\Http\Request;

/**
 * V1 §8 — Change Log / Audit Trail.
 *
 * READ-ONLY surface per spec §8.7. NO write endpoints — audit_logs is
 * written only as a side-effect of other modules' actions via
 * App\Services\Audit\AuditLogger. The optional four-eyes approval
 * workflow (§8.6) is forward-contract and not exposed in V1.
 */
class AuditTrailController extends ApiController
{
    public function __construct(protected AuditTrailService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $paginator = $this->service->listForTab($request->all(), $perPage);
        $rows = AuditTrailResource::collection($paginator->items());

        $lastUpdated = AuditLog::query()->max('created_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->service->buildSummary(),
            $lastUpdated,
            __('logbook.messages.audit_retrieved')
        );
    }

    public function show(string $id)
    {
        if (! ctype_digit($id)) {
            return $this->error(__('logbook.messages.audit_record_not_found'), 'AUDIT_RECORD_NOT_FOUND', 404);
        }
        $row = AuditLog::query()->find((int) $id);
        if (! $row) {
            return $this->error(__('logbook.messages.audit_record_not_found'), 'AUDIT_RECORD_NOT_FOUND', 404);
        }

        $resource = (new AuditTrailResource($row))
            ->additional([
                'oldValues' => $row->old_values,
                'newValues' => $row->new_values,
                'userAgent' => $row->user_agent,
                'sessionId' => $row->session_id,
            ]);

        return $this->success($resource, __('logbook.messages.audit_record_detail'));
    }
}
