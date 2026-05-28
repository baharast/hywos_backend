<?php

namespace App\Http\Resources;

use App\Enums\HardwareConnectionTestResult;
use App\Enums\HardwareDeviceCriticality;
use App\Enums\HardwareDeviceHealth;
use App\Enums\HardwareDeviceSubsystem;
use App\Enums\HardwareDeviceType;
use App\Enums\HardwarePhysicalLocation;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row shape for the V1.4 §4.3 Device Registry / Overview table.
 *
 * All categorical fields are returned as `{value, label, tone}` so the
 * FE can render badges without re-mapping. `connectionTest` is included
 * here too (not only in detail) so the FE can show a small recency
 * indicator next to the row action menu.
 */
class HardwareDeviceListResource extends JsonResource
{
    public function toArray($request): array
    {
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
                'groupingHint' => HardwareDeviceType::groupingHint($type),
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

            'affectedProcess' => $this->affected_process,
            'affectedProcessLabel' => $this->affected_process_label,
            'protocol' => $this->protocol,

            'isBlockingCriticalProcess' => (bool) $this->is_blocking_critical_process,
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

            'lastSeenAt' => $this->last_seen_at?->toIso8601String(),
            'lastEventAt' => $this->last_event_at?->toIso8601String(),
            'lastMessage' => $this->last_message,

            'dataStatus' => $this->data_status,
            'needsEngineeringConfirmation' => $this->data_status === 'tbc_engineering',

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
