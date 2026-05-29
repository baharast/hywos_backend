<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventCategory;
use App\Http\Resources\SecurityEventResource;
use App\Models\EventLog;
use App\Services\ApiResponse;
use App\Services\AlarmsEvents\SecurityEventsService;
use Illuminate\Http\Request;

/**
 * V1 §7.6 — Security Events (restricted subview).
 *
 * READ-ONLY in V1. The `security_events.view` permission gates access
 * (admin / IT / operations_manager only). The mark-reviewed write
 * surface is forward-contract — the underlying review_status column
 * doesn't exist yet.
 *
 * Per V1 §7.6 forbidden actions: do not expose sensitive full chip/TAN
 * values, passwords, service tokens or unrestricted payloads. The
 * detail endpoint sanitizes the stored `details` JSON before returning.
 */
class SecurityEventController extends ApiController
{
    /**
     * Keys in event_logs.details that may carry sensitive data — masked
     * before the row is returned.
     */
    protected const SENSITIVE_KEYS = [
        'password', 'password_hash', 'token', 'access_token',
        'refresh_token', 'api_key', 'service_token', 'session_token',
        'chip_uid', 'chip_raw', 'tan_raw', 'tan_value',
        'identifier_value', 'identifier_hash', 'authorization',
        'cookie', 'set_cookie',
    ];

    public function __construct(protected SecurityEventsService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $paginator = $this->service->listForTab($request->all(), $perPage);
        $rows = SecurityEventResource::collection($paginator->items());

        $lastUpdated = EventLog::query()
            ->where('event_category', EventCategory::SECURITY)
            ->max('occurred_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->service->buildSummary(),
            $lastUpdated,
            'Security events retrieved'
        );
    }

    public function show(string $id)
    {
        if (! ctype_digit($id)) {
            return $this->error('Security event not found', 'SECURITY_EVENT_NOT_FOUND', 404);
        }
        $row = EventLog::query()
            ->where('event_category', EventCategory::SECURITY)
            ->find((int) $id);
        if (! $row) {
            return $this->error('Security event not found', 'SECURITY_EVENT_NOT_FOUND', 404);
        }

        $sanitizedDetails = $this->sanitizeDetails($row->details);

        $resource = (new SecurityEventResource($row))
            ->additional(['details' => $sanitizedDetails]);

        return $this->success($resource, 'Security event detail retrieved');
    }

    /**
     * Recursively scrub sensitive keys from a details payload before
     * exposing it through the API.
     */
    protected function sanitizeDetails($value): mixed
    {
        if (! is_array($value)) return $value;
        $out = [];
        foreach ($value as $k => $v) {
            $kLower = strtolower((string) $k);
            if (in_array($kLower, self::SENSITIVE_KEYS, true)) {
                $out[$k] = '***';
                continue;
            }
            $out[$k] = is_array($v) ? $this->sanitizeDetails($v) : $v;
        }
        return $out;
    }
}
