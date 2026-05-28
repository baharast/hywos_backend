<?php

namespace App\Http\Controllers\Api;

use App\Enums\GateTerminalSessionState;
use App\Http\Resources\TerminalPanelTabResource;
use App\Models\AuditLog;
use App\Models\HardwareDevice;
use App\Services\ApiResponse;
use App\Services\HardwareDevice\TerminalPanelTabService;
use Illuminate\Http\Request;

/**
 * V1.4 §5 — internal Terminals & Panels tab.
 *
 * Read-only composite over hardware_devices + terminal_sessions. NO
 * write endpoints here — service-mode writes live on the parent
 * Hardware Devices registry (/api/hardware-devices), and session
 * state mutations are owned by Gate & Terminal Monitor.
 */
class TerminalPanelTabController extends ApiController
{
    public function __construct(protected TerminalPanelTabService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $paginator = $this->service->listForTab($request->all(), $perPage);
        $rows = TerminalPanelTabResource::collection($paginator->items());

        $lastUpdated = HardwareDevice::query()->max('last_event_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->service->buildSummary(),
            $lastUpdated,
            'Terminals & panels retrieved'
        );
    }

    public function show(string $deviceId)
    {
        $device = HardwareDevice::query()->find($deviceId);
        if (! $device) {
            return $this->error('Hardware device not found', 'HARDWARE_DEVICE_NOT_FOUND', 404);
        }

        // Last 20 sessions for this device's touchpoint, newest first.
        $sessionHistory = $this->service->sessionHistoryFor($device)->map(fn ($s) => [
            'id' => $s->id,
            'sessionNo' => $s->session_no,
            'touchpoint' => $s->touchpoint,
            'sessionState' => [
                'value' => $s->session_state,
                'label' => GateTerminalSessionState::label($s->session_state ?? ''),
                'tone' => GateTerminalSessionState::tone($s->session_state ?? ''),
            ],
            'driverName' => $s->driver_name,
            'driverCode' => $s->driver_code,
            'currentScreen' => $s->current_screen,
            'linkedVisitNo' => $s->visit_no,
            'linkedOrderNo' => $s->order_no,
            'needsOperator' => (bool) $s->needs_operator,
            'lastActivityAt' => $s->last_activity_at?->toIso8601String(),
        ])->all();

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

        $resource = (new TerminalPanelTabResource($device))
            ->additional([
                'sessionHistory' => $sessionHistory,
                'auditRows' => $auditRows,
            ]);

        return $this->success($resource, 'Terminal / panel detail retrieved');
    }
}
