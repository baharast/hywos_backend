<?php

namespace App\Services\AnalysisDevice;

use App\Enums\AnalysisDeviceCalibrationStatus;
use App\Enums\AnalysisDeviceHealthStatus;
use App\Enums\AnalysisDeviceRunState;
use App\Enums\AnalysisDeviceType;
use App\Enums\GasComponent;
use App\Models\AnalysisDevice;

/**
 * V1 §8 — assembles the per-device card payload.
 *
 * The controller reads model rows; the service computes the THREE
 * derived blobs that the FE card needs but the table doesn't store:
 *   - channelSummary (REGARD)
 *   - streamSummary  (SAM)
 *   - componentSummary (analyser — counts missing / invalid components)
 *
 * Also owns the staleness threshold and the "next action" hint.
 */
class AnalysisDeviceService
{
    /**
     * V1 §17 freshness rule. 5 minutes is enough wiggle room for a healthy
     * minute-cadence heartbeat but tight enough to flag a frozen device.
     */
    public const STALE_AFTER_SECONDS = 300;

    /**
     * Channel severity ordering — used by the REGARD channelSummary's
     * `highestSeverity` field. Higher index = louder.
     */
    public const CHANNEL_SEVERITY_ORDER = [
        'normal' => 0,
        'a1' => 1,
        'a2' => 2,
        'a3' => 3,
        'f1' => 4,
        'f2' => 5,
    ];

    public function buildCardForDevice(AnalysisDevice $device): array
    {
        $deviceType = $device->device_type;
        $health = $device->health_status ?: AnalysisDeviceHealthStatus::HEALTHY;

        $card = [
            'id' => $device->id,
            'code' => $device->code,
            'name' => $device->name,
            'deviceType' => [
                'value' => $deviceType,
                'label' => AnalysisDeviceType::label($deviceType),
            ],
            'healthStatus' => [
                'value' => $health,
                'label' => AnalysisDeviceHealthStatus::label($health),
                'tone' => AnalysisDeviceHealthStatus::tone($health),
            ],
            'latestMessage' => $device->last_message,
            'affectingAnalysisId' => null,
            'lastHeartbeatAt' => $device->last_heartbeat_at?->toIso8601String(),
            'lastValueAt' => $device->last_value_at?->toIso8601String(),
            'isStale' => $this->getStaleness($device),
            'actionPath' => $this->resolveActionPath($device),
        ];

        if ($deviceType === AnalysisDeviceType::ANALYSER) {
            $runState = $device->run_state ?: AnalysisDeviceRunState::IDLE;
            $card['runState'] = [
                'value' => $runState,
                'label' => AnalysisDeviceRunState::label($runState),
            ];
            if ($device->calibration_status) {
                $card['calibration'] = [
                    'value' => $device->calibration_status,
                    'label' => AnalysisDeviceCalibrationStatus::label($device->calibration_status),
                    'tone' => AnalysisDeviceCalibrationStatus::tone($device->calibration_status),
                    'nextDueAt' => $device->next_calibration_due_at?->toIso8601String(),
                ];
            } else {
                $card['calibration'] = null;
            }
            $card['activeMethod'] = $device->active_method;
            $card['selectedSamplePoint'] = $device->selected_sample_point;
            $card['componentSummary'] = $this->componentSummaryFor($device);
        }

        if ($deviceType === AnalysisDeviceType::GAS_WARNING_CONTROLLER) {
            $card['safetyState'] = $device->safety_state;
            $card['channelSummary'] = $this->channelSummaryFor($device);
        }

        if ($deviceType === AnalysisDeviceType::SAMPLE_SWITCHING_MODULE) {
            $card['routingState'] = $device->routing_state;
            $card['streamSummary'] = $this->streamSummaryFor($device);
        }

        return $card;
    }

    /**
     * V1 §17 — flag a device as stale when its heartbeat is older than 5
     * minutes. A null heartbeat also counts as stale (we have no signal
     * either way, treat it as untrustworthy).
     */
    public function getStaleness(AnalysisDevice $device): bool
    {
        if (! $device->last_heartbeat_at) {
            return true;
        }
        return $device->last_heartbeat_at->diffInSeconds(now()) > self::STALE_AFTER_SECONDS;
    }

    /**
     * V1 §8 / §13 — one navigation target per abnormal card. Healthy
     * cards still expose "Open Device Detail" so the FE can always offer
     * a clickthrough.
     */
    public function resolveActionPath(AnalysisDevice $device): ?array
    {
        $deviceType = $device->device_type;
        $health = $device->health_status;

        // Calibration trumps run-state guidance — operator must clear that
        // first per V1 Flow 4.
        if ($deviceType === AnalysisDeviceType::ANALYSER
            && in_array($device->calibration_status, [
                AnalysisDeviceCalibrationStatus::OVERDUE,
                AnalysisDeviceCalibrationStatus::FAILED,
                AnalysisDeviceCalibrationStatus::DUE_SOON,
            ], true)
        ) {
            return [
                'label' => 'Open Calibration',
                'route' => "/analysis/calibration-settings?deviceId={$device->id}",
            ];
        }

        if ($deviceType === AnalysisDeviceType::GAS_WARNING_CONTROLLER
            && in_array($health, [AnalysisDeviceHealthStatus::ALARM, AnalysisDeviceHealthStatus::FAULT], true)
        ) {
            return [
                'label' => 'Open Active Alarm',
                'route' => "/alarms-events/active-alarms?deviceId={$device->id}",
            ];
        }

        if ($deviceType === AnalysisDeviceType::SAMPLE_SWITCHING_MODULE
            && in_array($health, [AnalysisDeviceHealthStatus::WARNING, AnalysisDeviceHealthStatus::ALARM, AnalysisDeviceHealthStatus::FAULT], true)
        ) {
            return [
                'label' => 'Open Device Detail',
                'route' => "/operations/analysis-devices/{$device->id}",
            ];
        }

        // Default for any non-healthy device. Healthy devices return null
        // so the card stays quiet per V1 §17 ("normal stays quiet").
        if ($health !== AnalysisDeviceHealthStatus::HEALTHY) {
            return [
                'label' => 'Open Device Detail',
                'route' => "/operations/analysis-devices/{$device->id}",
            ];
        }

        return null;
    }

    /**
     * V1 §8.2 — collapse all REGARD channels into a card-friendly summary.
     */
    public function channelSummaryFor(AnalysisDevice $device): array
    {
        $channels = $device->channels()->get();

        $highest = 'normal';
        $highestIdx = self::CHANNEL_SEVERITY_ORDER['normal'];
        $alarmCount = 0;
        $faultCount = 0;

        foreach ($channels as $ch) {
            $sev = strtolower($ch->severity ?? 'normal');
            $idx = self::CHANNEL_SEVERITY_ORDER[$sev] ?? 0;
            if ($idx > $highestIdx) {
                $highestIdx = $idx;
                $highest = $sev;
            }
            if (in_array($sev, ['a1', 'a2', 'a3'], true)) {
                $alarmCount++;
            }
            if (in_array($sev, ['f1', 'f2'], true)) {
                $faultCount++;
            }
        }

        return [
            'highestSeverity' => $highest,
            'alarmCount' => $alarmCount,
            'faultCount' => $faultCount,
            'totalChannels' => $channels->count(),
        ];
    }

    /**
     * V1 §8.3 — SAM stream/mode/valve at-a-glance. Valve state isn't
     * stored separately yet (no PLC feed); we expose `last_message` as
     * the closest readable signal until that wire comes in.
     */
    public function streamSummaryFor(AnalysisDevice $device): array
    {
        return [
            'selectedStream' => $device->selected_stream,
            'mode' => $device->mode,
            // Reserved for when valve telemetry lands; today the kiosk
            // demo doesn't track per-valve state.
            'valveState' => null,
        ];
    }

    /**
     * V1 §8.1 — analyser component summary: how many of the 6 MVP
     * components are configured / missing / invalid.
     */
    public function componentSummaryFor(AnalysisDevice $device): array
    {
        $expected = GasComponent::all();
        $readings = $device->latestReadings()->get()->keyBy('component');

        $missing = [];
        $invalid = [];
        foreach ($expected as $component) {
            $row = $readings->get($component);
            if (! $row) {
                $missing[] = $component;
                continue;
            }
            if ($row->validity !== 'valid') {
                $invalid[] = $component;
            }
        }

        return [
            'configured' => $readings->count(),
            'expected' => count($expected),
            'missing' => $missing,
            'invalid' => $invalid,
        ];
    }
}
