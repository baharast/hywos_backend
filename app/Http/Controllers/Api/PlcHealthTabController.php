<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PlcHealthTabResource;
use App\Models\AuditLog;
use App\Models\HardwareDevice;
use App\Services\ApiResponse;
use App\Services\HardwareDevice\PlcHealthTabService;
use Illuminate\Http\Request;

/**
 * V1.4 §8 — internal PLC / OPC UA Health tab.
 *
 * Read-only composite over hardware_devices. NO write endpoints —
 * V1.4 §8 boundary note explicitly forbids bypass / reset controls.
 * Service-mode writes live on the parent registry
 * (/api/hardware-devices); connector orchestration lives in Interface
 * Health.
 */
class PlcHealthTabController extends ApiController
{
    public function __construct(protected PlcHealthTabService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $paginator = $this->service->listForTab($request->all(), $perPage);
        $rows = PlcHealthTabResource::collection($paginator->items());

        $lastUpdated = HardwareDevice::query()
            ->whereIn('device_type', PlcHealthTabService::IN_SCOPE_TYPES)
            ->max('last_event_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->service->buildSummary(),
            $lastUpdated,
            __('hardware.messages.plc_endpoints_retrieved')
        );
    }

    public function show(string $deviceId)
    {
        $device = HardwareDevice::query()
            ->whereIn('device_type', PlcHealthTabService::IN_SCOPE_TYPES)
            ->find($deviceId);
        if (! $device) {
            return $this->error(__('hardware.messages.plc_endpoint_not_found'), 'PLC_ENDPOINT_NOT_FOUND', 404);
        }

        $auditRows = AuditLog::query()
            ->where('entity_type', $device->getMorphClass())
            ->where('entity_id', $device->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'actorUserId' => $a->actor_user_id,
                'actorName' => $a->actor_name,
                'reason' => $a->reason,
                'createdAt' => $a->created_at?->toIso8601String(),
            ])
            ->all();

        $resource = (new PlcHealthTabResource($device))
            ->additional(['auditRows' => $auditRows]);

        return $this->success($resource, __('hardware.messages.plc_endpoint_detail'));
    }
}
