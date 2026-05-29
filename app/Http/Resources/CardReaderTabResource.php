<?php

namespace App\Http\Resources;

use App\Enums\HardwareConnectionTestResult;
use App\Enums\HardwareDeviceCriticality;
use App\Enums\HardwareDeviceHealth;
use App\Enums\HardwareDeviceSubsystem;
use App\Enums\HardwareDeviceType;
use App\Enums\HardwarePhysicalLocation;
use App\Services\HardwareDevice\CardReaderTabService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Composite row for V1.4 §7 Card Readers / Trailer-Chip Readers tab.
 *
 * Same resource serves list + detail endpoints. Detail mode adds
 * `sessionHistory` and `auditRows` via
 * `->additional(['sessionHistory' => [...], 'auditRows' => [...]])`
 * on the controller side. List mode leaves both null.
 */
class CardReaderTabResource extends JsonResource
{
    public function toArray($request): array
    {
        $svc = app(CardReaderTabService::class);
        $composite = $svc->enrichRow($this->resource);

        $health = $this->health ?? HardwareDeviceHealth::UNKNOWN;
        $crit = $this->criticality ?? HardwareDeviceCriticality::LOW;
        $type = $this->device_type;
        $sub = $this->subsystem;
        $loc = $this->physical_location;

        return [
            'id' => $this->id,
            'assetTag' => $this->asset_tag,
            'vendorTag' => $this->vendor_tag,
            'name' => $this->name,

            'deviceType' => $type === null ? null : [
                'value' => $type,
                'label' => HardwareDeviceType::label($type),
            ],
            'subsystem' => $sub === null ? null : [
                'value' => $sub,
                'label' => HardwareDeviceSubsystem::label($sub),
            ],
            'physicalLocation' => $loc === null ? null : [
                'value' => $loc,
                'label' => HardwarePhysicalLocation::label($loc),
            ],
            'health' => [
                'value' => $health,
                'label' => HardwareDeviceHealth::label($health),
                'tone' => HardwareDeviceHealth::tone($health),
            ],
            'criticality' => [
                'value' => $crit,
                'label' => HardwareDeviceCriticality::label($crit),
                'tone' => HardwareDeviceCriticality::tone($crit),
            ],

            // V1.4 §7 columns — reader kind, supported media, linked
            // device, linked process, last identification event,
            // safe route.
            'readerKind' => $composite['kind'],
            'supportedMedia' => $composite['supportedMedia'],
            'linkedDevice' => $composite['linkedDevice'],
            'linkedProcess' => $composite['linkedProcess'],
            'lastIdentificationEvent' => $composite['lastIdentificationEvent'],
            'safeRoute' => $composite['safeRoute'],

            // Service-mode + connection-test live with the registry; we
            // surface them here so the FE can show the badge without a
            // second round-trip.
            'serviceMode' => (bool) $this->service_mode,
            'serviceModeReason' => $this->when((bool) $this->service_mode, $this->service_mode_reason),
            'connectionTest' => $this->connection_test_last_run_at === null ? null : [
                'lastRunAt' => $this->connection_test_last_run_at?->toIso8601String(),
                'lastResult' => [
                    'value' => $this->connection_test_last_result,
                    'label' => HardwareConnectionTestResult::label($this->connection_test_last_result ?? ''),
                    'tone' => HardwareConnectionTestResult::tone($this->connection_test_last_result ?? ''),
                ],
            ],

            'isBlockingCriticalProcess' => (bool) $this->is_blocking_critical_process,
            'lastSeenAt' => $this->last_seen_at?->toIso8601String(),
            'lastEventAt' => $this->last_event_at?->toIso8601String(),
            'lastMessage' => $this->last_message,

            'dataStatus' => $this->data_status,
            'needsEngineeringConfirmation' => $this->data_status === 'tbc_engineering',

            'referenceLinks' => [
                'hardwareDevice' => "/system-devices/hardware-devices/{$this->id}",
                'eventJournal' => "/alarms-events/event-journal?entity=hardware_device&entityId={$this->id}",
                'auditTrail' => "/alarms-events/audit-trail?entity=hardware_device&entityId={$this->id}",
            ],

            // V1.4 §7 boundary note: rendered as a neutral read-only chip
            // in detail mode so operators know forceful actions aren't
            // available from this surface.
            'diagnosticBoundaryNote' => 'This module is diagnostic only; unsafe controls are not available.',

            // Detail-mode extras — null when used in list mode.
            'sessionHistory' => $this->additional['sessionHistory'] ?? null,
            'auditRows' => $this->additional['auditRows'] ?? null,

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
