<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnalysisDeviceHealthStatus;
use App\Enums\AnalysisDeviceType;
use App\Http\Resources\AnalysisDeviceCardResource;
use App\Http\Resources\AnalysisDeviceChannelResource;
use App\Http\Resources\AnalysisDeviceDetailResource;
use App\Http\Resources\AnalysisDeviceLatestReadingResource;
use App\Models\AnalysisDevice;
use App\Models\EventLog;
use App\Services\AnalysisDevice\AnalysisDeviceService;
use App\Services\ApiResponse;
use Illuminate\Http\Request;

/**
 * Analysis Devices V1 — read-only.
 *
 * No write surface in MVP per V1 §4.2 ("Do not implement acknowledge
 * alarm, inhibit, reset, switch stream, start analyser, stop analyser…").
 * The audit constants `ANALYSIS_DEVICE_*` are reserved for the future
 * write controller but are NEVER emitted here.
 */
class AnalysisDeviceController extends ApiController
{
    public function __construct(protected AnalysisDeviceService $service) {}

    /**
     * GET /api/analysis-devices
     *
     * Compact health strip summary + the 3 device cards, priority-sorted
     * (alarm/fault first, healthy last).
     */
    public function index(Request $request)
    {
        $devices = AnalysisDevice::query()
            ->orderBy('code')
            ->get();

        // Sort by V1 §8 visual priority. PHP's usort isn't stable across
        // every version but the secondary sort by `code` keeps the demo
        // output deterministic.
        $cards = $devices
            ->map(fn (AnalysisDevice $d) => $this->service->buildCardForDevice($d))
            ->sort(function (array $a, array $b) {
                $pa = AnalysisDeviceHealthStatus::displayPriority($a['healthStatus']['value']);
                $pb = AnalysisDeviceHealthStatus::displayPriority($b['healthStatus']['value']);
                if ($pa === $pb) {
                    return strcmp($a['code'], $b['code']);
                }
                return $pa <=> $pb;
            })
            ->values()
            ->all();

        $abnormalCount = $devices->where('health_status', '!=', AnalysisDeviceHealthStatus::HEALTHY)->count();
        $allHealthy = $abnormalCount === 0;
        $lastUpdatedAt = $devices->max('updated_at');

        return ApiResponse::success([
            'summary' => [
                'allHealthy' => $allHealthy,
                'abnormalCount' => $abnormalCount,
                'lastUpdatedAt' => $lastUpdatedAt?->toIso8601String(),
            ],
            'cards' => AnalysisDeviceCardResource::collection($cards),
        ], __('analysis.messages.devices_retrieved'));
    }

    /**
     * GET /api/analysis-devices/{id} — full detail page.
     */
    public function show(string $id)
    {
        $device = AnalysisDevice::find($id);
        if (! $device) {
            return $this->error(__('analysis.messages.device_not_found'), 'ANALYSIS_DEVICE_NOT_FOUND', 404);
        }

        $card = $this->service->buildCardForDevice($device);

        $channels = $device->channels()
            ->orderBy('channel_code')
            ->get()
            ->map(fn ($c) => (new AnalysisDeviceChannelResource($c))->toArray(request()))
            ->all();

        $latestReadings = $device->latestReadings()
            ->orderBy('component')
            ->get()
            ->map(fn ($r) => (new AnalysisDeviceLatestReadingResource($r))->toArray(request()))
            ->all();

        $events = EventLog::query()
            ->where('entity_type', $device->getMorphClass())
            ->where('entity_id', (string) $device->id)
            ->orderByDesc('occurred_at')
            ->limit(20)
            ->get();

        $detail = $card + [
            'channels' => $channels,
            'latestReadings' => $latestReadings,
            'recentEvents' => $events,
        ];

        return $this->success(new AnalysisDeviceDetailResource($detail), __('analysis.messages.device_retrieved'));
    }

    /**
     * GET /api/analysis-devices/{id}/channels — REGARD-only channel list.
     */
    public function channels(string $id)
    {
        $device = AnalysisDevice::find($id);
        if (! $device) {
            return $this->error(__('analysis.messages.device_not_found'), 'ANALYSIS_DEVICE_NOT_FOUND', 404);
        }
        if ($device->device_type !== AnalysisDeviceType::GAS_WARNING_CONTROLLER) {
            return $this->error(
                __('analysis.messages.device_no_channels'),
                'ANALYSIS_DEVICE_NO_CHANNELS',
                404,
                ['deviceType' => $device->device_type]
            );
        }

        $channels = $device->channels()->orderBy('channel_code')->get();

        return $this->success(
            AnalysisDeviceChannelResource::collection($channels),
            __('analysis.messages.device_channels')
        );
    }

    /**
     * GET /api/analysis-devices/{id}/events — paginated event_logs slice.
     */
    public function events(Request $request, string $id)
    {
        $device = AnalysisDevice::find($id);
        if (! $device) {
            return $this->error(__('analysis.messages.device_not_found'), 'ANALYSIS_DEVICE_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 20);

        $paginator = EventLog::query()
            ->where('entity_type', $device->getMorphClass())
            ->where('entity_id', (string) $device->id)
            ->orderByDesc('occurred_at')
            ->paginate($perPage);

        return ApiResponse::list(
            $paginator->items(),
            $paginator,
            null,
            $device->updated_at,
            __('analysis.messages.device_events')
        );
    }

    /**
     * GET /api/analysis-devices/status-table
     *
     * V1 §9 — flat row-per-device-or-channel for sorting/export. Sorted by
     * health priority ASC then last_value_at DESC.
     */
    public function statusTable(Request $request)
    {
        $devices = AnalysisDevice::query()->with('channels')->get();

        $rows = [];
        foreach ($devices as $device) {
            $rows[] = [
                'rowType' => 'device',
                'deviceId' => $device->id,
                'channelId' => null,
                'displayName' => $device->name,
                'code' => $device->code,
                'deviceType' => [
                    'value' => $device->device_type,
                    'label' => AnalysisDeviceType::label($device->device_type),
                ],
                'healthStatus' => [
                    'value' => $device->health_status,
                    'label' => AnalysisDeviceHealthStatus::label($device->health_status),
                    'tone' => AnalysisDeviceHealthStatus::tone($device->health_status),
                ],
                'operationalState' => $this->operationalStateFor($device),
                'latestMessage' => $device->last_message,
                'measuredValue' => null,
                'unit' => null,
                'isStale' => $this->service->getStaleness($device),
                'lastValueAt' => $device->last_value_at?->toIso8601String(),
                'lastHeartbeatAt' => $device->last_heartbeat_at?->toIso8601String(),
                'priority' => AnalysisDeviceHealthStatus::displayPriority($device->health_status),
            ];

            foreach ($device->channels as $channel) {
                $rows[] = [
                    'rowType' => 'channel',
                    'deviceId' => $device->id,
                    'channelId' => $channel->id,
                    'displayName' => $channel->label,
                    'code' => $channel->channel_code,
                    'deviceType' => [
                        'value' => 'gas_warning_channel',
                        'label' => __('analysis.messages.gas_warning_channel'),
                    ],
                    'healthStatus' => [
                        'value' => $this->channelHealthFor($channel->severity),
                        'label' => AnalysisDeviceHealthStatus::label($this->channelHealthFor($channel->severity)),
                        'tone' => AnalysisDeviceHealthStatus::tone($this->channelHealthFor($channel->severity)),
                    ],
                    'operationalState' => strtoupper($channel->severity ?? 'normal'),
                    'latestMessage' => $channel->last_message,
                    'measuredValue' => $channel->measured_value,
                    'unit' => $channel->unit,
                    'isStale' => false,
                    'lastValueAt' => $channel->last_value_at?->toIso8601String(),
                    'lastHeartbeatAt' => null,
                    'priority' => AnalysisDeviceHealthStatus::displayPriority(
                        $this->channelHealthFor($channel->severity)
                    ),
                ];
            }
        }

        usort($rows, function (array $a, array $b) {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            $aTs = $a['lastValueAt'] ?? '';
            $bTs = $b['lastValueAt'] ?? '';
            return strcmp($bTs, $aTs); // newer first within the same priority
        });

        // Strip the sort key — useful internally, not on the wire.
        foreach ($rows as &$row) {
            unset($row['priority']);
        }
        unset($row);

        return $this->success([
            'rows' => $rows,
            'total' => count($rows),
        ], __('analysis.messages.devices_status_table'));
    }

    /**
     * One-liner operational state for the status table, derived from the
     * device-type-specific columns.
     */
    protected function operationalStateFor(AnalysisDevice $device): string
    {
        return match ($device->device_type) {
            AnalysisDeviceType::ANALYSER => $device->run_state ?: 'idle',
            AnalysisDeviceType::GAS_WARNING_CONTROLLER => $device->safety_state ?: 'normal',
            AnalysisDeviceType::SAMPLE_SWITCHING_MODULE => $device->routing_state ?: 'unknown',
            default => '',
        };
    }

    /**
     * Map a channel severity into the AnalysisDeviceHealthStatus tone the
     * FE already knows how to render — keeps the status table visually
     * consistent with the cards.
     */
    protected function channelHealthFor(?string $severity): string
    {
        return match (strtolower($severity ?? 'normal')) {
            'a1' => AnalysisDeviceHealthStatus::WARNING,
            'a2', 'a3' => AnalysisDeviceHealthStatus::ALARM,
            'f1', 'f2' => AnalysisDeviceHealthStatus::FAULT,
            default => AnalysisDeviceHealthStatus::HEALTHY,
        };
    }
}
