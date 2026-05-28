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
 * Detail payload for the V1.4 §4 row-detail panel. Adds service-mode
 * audit metadata + connection-test full record + recent audit_logs
 * slice on top of the list shape.
 *
 * The caller passes recent audit rows via
 * `->additional(['auditRows' => ...])` because the resource isn't
 * allowed to issue extra queries.
 */
class HardwareDeviceDetailResource extends JsonResource
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

            'serviceMode' => [
                'active' => (bool) $this->service_mode,
                'reason' => $this->service_mode_reason,
                'setAt' => $this->service_mode_set_at?->toIso8601String(),
                'setByUserId' => $this->service_mode_set_by_user_id,
                'expectedEndAt' => $this->service_mode_expected_end_at?->toIso8601String(),
            ],

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
            'sourceBasis' => $this->source_basis,
            'correlationId' => $this->correlation_id,

            // §4.3 row actions reference targets — FE links use these.
            'referenceLinks' => [
                'eventJournal' => "/alarms-events/event-journal?entity=hardware_device&entityId={$this->id}",
                'auditTrail' => "/alarms-events/audit-trail?entity=hardware_device&entityId={$this->id}",
            ],

            'auditRows' => $this->additional['auditRows'] ?? [],

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
