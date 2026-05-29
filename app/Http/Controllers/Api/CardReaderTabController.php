<?php

namespace App\Http\Controllers\Api;

use App\Enums\GateTerminalSessionState;
use App\Http\Resources\CardReaderTabResource;
use App\Models\AuditLog;
use App\Models\HardwareDevice;
use App\Services\ApiResponse;
use App\Services\HardwareDevice\CardReaderTabService;
use Illuminate\Http\Request;

/**
 * V1.4 §7 — internal Card Readers / Trailer-Chip Readers tab.
 *
 * Read-only composite over hardware_devices + terminal_sessions. NO
 * write endpoints — V1.4 §7's action-menu rule explicitly forbids force-
 * match, force-success, gate open, loading release or safety bypass.
 * Service-mode writes live on the parent registry
 * (/api/hardware-devices); clarification cases are raised through
 * /api/clarification-cases.
 */
class CardReaderTabController extends ApiController
{
    public function __construct(protected CardReaderTabService $service) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $paginator = $this->service->listForTab($request->all(), $perPage);
        $rows = CardReaderTabResource::collection($paginator->items());

        $lastUpdated = HardwareDevice::query()
            ->whereIn('device_type', [
                \App\Enums\HardwareDeviceType::RFID_READER,
                \App\Enums\HardwareDeviceType::TRAILER_CHIP_READER,
            ])
            ->max('last_event_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->service->buildSummary(),
            $lastUpdated,
            'Card readers retrieved'
        );
    }

    public function show(string $deviceId)
    {
        $device = HardwareDevice::query()
            ->whereIn('device_type', [
                \App\Enums\HardwareDeviceType::RFID_READER,
                \App\Enums\HardwareDeviceType::TRAILER_CHIP_READER,
            ])
            ->find($deviceId);
        if (! $device) {
            return $this->error('Card reader not found', 'CARD_READER_NOT_FOUND', 404);
        }

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

        $resource = (new CardReaderTabResource($device))
            ->additional([
                'sessionHistory' => $sessionHistory,
                'auditRows' => $auditRows,
            ]);

        return $this->success($resource, 'Card reader detail retrieved');
    }
}
