<?php

namespace Database\Seeders;

use App\Enums\AlarmBlockingImpact;
use App\Enums\AlarmCategory;
use App\Enums\AlarmOwnerRole;
use App\Enums\AlarmSeverity;
use App\Enums\AlarmSourceType;
use App\Enums\AlarmStatus;
use App\Enums\HardwarePhysicalLocation;
use App\Models\Alarm;
use App\Models\HardwareDevice;
use App\Models\LoadingOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo seed for V1 §6 Active Alarms.
 *
 *  ALM-2026-0001  CRITICAL safety       E-stop pressed at FS-04   blocks_loading   ACTIVE
 *  ALM-2026-0002  HIGH     loading      Earthing monitor NOK FS-01 blocks_loading  ACKNOWLEDGED
 *  ALM-2026-0003  HIGH     quality      Pre-analysis NOK ×3        blocks_loading  ASSIGNED
 *  ALM-2026-0004  CRITICAL quality      Main analysis NOK (post)   blocks_exit     IN_PROGRESS
 *  ALM-2026-0005  MEDIUM   document     Printer fault HD-PRINTER-DRV-01 blocks_exit ACTIVE
 *  ALM-2026-0006  HIGH     interface    SAP order import delayed   warning_only    ACTIVE
 *  ALM-2026-0007  MEDIUM   gate_access  Driver TAN expired @ entry warning_only    RESOLVED_PENDING_CLOSE
 *  ALM-2026-0008  LOW      device       PLC heartbeat slow         warning_only    CLOSED (historical)
 *  ALM-2026-0009  INFO     loading      Loading paused per dispatcher warning_only SUPPRESSED_MAINTENANCE
 */
class AlarmSeeder extends Seeder
{
    public function run(): void
    {
        // Best-effort soft-FK lookups to existing demo rows so the
        // linked_entity columns deep-link correctly in the FE.
        $orderLO0001 = LoadingOrder::query()->where('order_no', 'LO-2026-0001')->first();
        $orderLO0003 = LoadingOrder::query()->where('order_no', 'LO-2026-0003')->first();
        $orderLO0004 = LoadingOrder::query()->where('order_no', 'LO-2026-0004')->first();
        $printer = HardwareDevice::query()->where('asset_tag', 'HD-PRINTER-DRV-01')->first();
        $plc = HardwareDevice::query()->where('asset_tag', 'HD-PLC-CENTRAL-01')->first();

        $rows = [
            // 1) Critical safety — E-stop FS-04
            [
                'alarm_no' => 'ALM-2026-0001',
                'title' => 'Emergency stop pressed at Filling Station 04',
                'severity' => AlarmSeverity::CRITICAL,
                'status' => AlarmStatus::ACTIVE,
                'category' => AlarmCategory::SAFETY,
                'blocking_impact' => AlarmBlockingImpact::BLOCKS_LOADING,
                'source_type' => AlarmSourceType::STATION_PLC,
                'source_id' => 'FS-04-PLC-S1500',
                'source_label' => 'Filling Station 04 PLC',
                'location' => HardwarePhysicalLocation::FILLING_BAY_04,
                'owner_role' => AlarmOwnerRole::OPERATOR,
                'message' => 'Driver pressed E-stop on the dispenser panel; loading blocked at FS-04. Investigate before reset; no software reset available from this surface.',
                'recommended_action' => 'Open Filling Station 04 detail; coordinate physical reset with the safety officer.',
                'alarm_code' => 'SAF-ESTOP-FS04',
                'first_seen_at' => '-12 minutes',
                'last_seen_at' => '-12 minutes',
                'occurrence_count' => 1,
            ],
            // 2) High loading — Earthing monitor
            [
                'alarm_no' => 'ALM-2026-0002',
                'title' => 'Earthing monitor not OK at Filling Station 01',
                'severity' => AlarmSeverity::HIGH,
                'status' => AlarmStatus::ACKNOWLEDGED,
                'category' => AlarmCategory::LOADING_PROCESS,
                'blocking_impact' => AlarmBlockingImpact::BLOCKS_LOADING,
                'source_type' => AlarmSourceType::STATION_PLC,
                'source_id' => 'FS-01-EARTH-MON',
                'source_label' => 'FS-01 earthing monitor',
                'location' => HardwarePhysicalLocation::FILLING_BAY_01,
                'owner_role' => AlarmOwnerRole::OPERATOR,
                'message' => 'Earthing clamp resistance above threshold (12 Ω, max 5 Ω). Loading release remains blocked.',
                'recommended_action' => 'Re-attach the earthing clamp; recheck the resistance reading.',
                'alarm_code' => 'LOAD-EARTH-NOK',
                'current_value' => '12',
                'threshold_value' => '5',
                'unit' => 'Ω',
                'first_seen_at' => '-45 minutes',
                'last_seen_at' => '-3 minutes',
                'occurrence_count' => 4,
                'acknowledged_at' => '-22 minutes',
                'acknowledged_by_name' => 'Operations Operator',
                'linked_entity_type' => $orderLO0003 ? \App\Models\LoadingOrder::class : null,
                'linked_entity_id' => $orderLO0003?->id,
                'linked_entity_label' => $orderLO0003?->order_no,
            ],
            // 3) High quality — Pre-analysis NOK ×3
            [
                'alarm_no' => 'ALM-2026-0003',
                'title' => 'Pre-analysis NOK on 3rd attempt (O2 high)',
                'severity' => AlarmSeverity::HIGH,
                'status' => AlarmStatus::ASSIGNED,
                'category' => AlarmCategory::QUALITY_ANALYSIS,
                'blocking_impact' => AlarmBlockingImpact::BLOCKS_LOADING,
                'source_type' => AlarmSourceType::ANALYSIS_PLC,
                'source_id' => 'AN-OS-01',
                'source_label' => 'OrthoSmart analyser AN-OS-01',
                'location' => HardwarePhysicalLocation::CONTROL_ROOM,
                'owner_role' => AlarmOwnerRole::ANALYSIS_SPECIALIST,
                'owner_user_name' => 'Analysis Specialist',
                'message' => 'Pre-analysis attempt 3/3 failed: O2 at 4.2 ppm (limit 1.0 ppm).',
                'recommended_action' => 'Open Active Analyses; loading rejection required.',
                'alarm_code' => 'QA-PRE-NOK-3X',
                'current_value' => '4.2',
                'threshold_value' => '1.0',
                'unit' => 'ppm',
                'first_seen_at' => '-30 minutes',
                'last_seen_at' => '-30 minutes',
                'occurrence_count' => 1,
                'acknowledged_at' => '-15 minutes',
                'acknowledged_by_name' => 'Analysis Specialist',
                'linked_entity_type' => 'analyses',
                'linked_entity_id' => null,
                'linked_entity_label' => 'AN-2026-0003',
            ],
            // 4) Critical quality — Main analysis NOK post-loading
            [
                'alarm_no' => 'ALM-2026-0004',
                'title' => 'Main analysis NOK after loading (N2 high)',
                'severity' => AlarmSeverity::CRITICAL,
                'status' => AlarmStatus::IN_PROGRESS,
                'category' => AlarmCategory::QUALITY_ANALYSIS,
                'blocking_impact' => AlarmBlockingImpact::BLOCKS_EXIT,
                'source_type' => AlarmSourceType::ANALYSIS_PLC,
                'source_id' => 'AN-OS-01',
                'source_label' => 'OrthoSmart analyser AN-OS-01',
                'location' => HardwarePhysicalLocation::CONTROL_ROOM,
                'owner_role' => AlarmOwnerRole::ANALYSIS_SPECIALIST,
                'owner_user_name' => 'Analysis Specialist',
                'message' => 'Main analysis NOK: N2 at 8.4 ppm (limit 5.0 ppm). Documents + exit remain blocked until decision.',
                'recommended_action' => 'Open Active Analyses to record manual functional approval or reject loading.',
                'alarm_code' => 'QA-MAIN-NOK-N2',
                'current_value' => '8.4',
                'threshold_value' => '5.0',
                'unit' => 'ppm',
                'first_seen_at' => '-25 minutes',
                'last_seen_at' => '-25 minutes',
                'occurrence_count' => 1,
                'acknowledged_at' => '-20 minutes',
                'acknowledged_by_name' => 'Analysis Specialist',
                'in_progress_at' => '-10 minutes',
                'linked_entity_type' => $orderLO0001 ? \App\Models\LoadingOrder::class : null,
                'linked_entity_id' => $orderLO0001?->id,
                'linked_entity_label' => $orderLO0001?->order_no,
            ],
            // 5) Medium document — Printer fault
            [
                'alarm_no' => 'ALM-2026-0005',
                'title' => 'Driver terminal printer failed mid-print',
                'severity' => AlarmSeverity::MEDIUM,
                'status' => AlarmStatus::ACTIVE,
                'category' => AlarmCategory::DOCUMENT_PRINTER,
                'blocking_impact' => AlarmBlockingImpact::BLOCKS_EXIT,
                'source_type' => AlarmSourceType::PRINTER,
                'source_id' => $printer?->asset_tag ?? 'HD-PRINTER-DRV-01',
                'source_label' => $printer?->name ?? 'Driver terminal printer',
                'location' => HardwarePhysicalLocation::DRIVER_TERMINAL,
                'owner_role' => AlarmOwnerRole::IT_SUPPORT,
                'message' => 'Print job for delivery note DN-2026-0007 failed. Driver cannot exit until certificate is provided.',
                'recommended_action' => 'Retry via Printers tab; or reroute to operator-room printer if configured.',
                'alarm_code' => 'DOC-PRINT-FAIL',
                'first_seen_at' => '-8 minutes',
                'last_seen_at' => '-8 minutes',
                'occurrence_count' => 1,
                'linked_entity_type' => $printer ? HardwareDevice::class : null,
                'linked_entity_id' => $printer?->id,
                'linked_entity_label' => $printer?->name,
            ],
            // 6) High interface — SAP import delay
            [
                'alarm_no' => 'ALM-2026-0006',
                'title' => 'SAP order import delayed (>15 min)',
                'severity' => AlarmSeverity::HIGH,
                'status' => AlarmStatus::ACTIVE,
                'category' => AlarmCategory::INTERFACE_SAP,
                'blocking_impact' => AlarmBlockingImpact::WARNING_ONLY,
                'source_type' => AlarmSourceType::SAP_CONNECTOR,
                'source_id' => 'sap-connector-01',
                'source_label' => 'SAP RFC connector',
                'location' => HardwarePhysicalLocation::ON_SITE_SERVER_ZONE,
                'owner_role' => AlarmOwnerRole::IT_SUPPORT,
                'message' => 'Inbound SAP order queue stalled. Already-synced active orders continue under fallback rules.',
                'recommended_action' => 'Check SAP Sync diagnostics; raise IT/Support ticket if delay persists.',
                'alarm_code' => 'SAP-IMPORT-DELAY',
                'first_seen_at' => '-18 minutes',
                'last_seen_at' => '-2 minutes',
                'occurrence_count' => 5,
            ],
            // 7) Medium gate/access — Driver TAN expired at entry
            [
                'alarm_no' => 'ALM-2026-0007',
                'title' => 'Expired TAN presented at entry gate',
                'severity' => AlarmSeverity::MEDIUM,
                'status' => AlarmStatus::RESOLVED_PENDING_CLOSE,
                'category' => AlarmCategory::GATE_ACCESS,
                'blocking_impact' => AlarmBlockingImpact::WARNING_ONLY,
                'source_type' => AlarmSourceType::GATE_SMART_PANEL,
                'source_id' => 'HD-GATE-ENTRY-01',
                'source_label' => 'Entry gate smart panel',
                'location' => HardwarePhysicalLocation::ENTRY_GATE,
                'owner_role' => AlarmOwnerRole::DISPATCHER,
                'message' => 'Driver presented TAN past expires_at; dispatcher issued fresh TAN.',
                'recommended_action' => 'Close after dispatcher confirms TAN replacement.',
                'alarm_code' => 'GATE-TAN-EXPIRED',
                'first_seen_at' => '-95 minutes',
                'last_seen_at' => '-95 minutes',
                'occurrence_count' => 1,
                'acknowledged_at' => '-80 minutes',
                'acknowledged_by_name' => 'Dispatcher',
                'resolved_at' => '-30 minutes',
                'resolved_by_name' => 'Dispatcher',
                'resolution_reason' => 'Replacement TAN issued and presented successfully.',
                'corrective_action' => 'New TAN issued via TANs management; driver advised to verify expires_at at the desk.',
            ],
            // 8) Low device — PLC heartbeat slow (closed/historical)
            [
                'alarm_no' => 'ALM-2026-0008',
                'title' => 'Central PLC heartbeat above 1s',
                'severity' => AlarmSeverity::LOW,
                'status' => AlarmStatus::CLOSED,
                'category' => AlarmCategory::DEVICE_COMMUNICATION,
                'blocking_impact' => AlarmBlockingImpact::WARNING_ONLY,
                'source_type' => AlarmSourceType::CENTRAL_PLC,
                'source_id' => $plc?->asset_tag ?? 'HD-PLC-CENTRAL-01',
                'source_label' => $plc?->name ?? 'Central PLC',
                'location' => HardwarePhysicalLocation::TECHNICAL_ROOM,
                'owner_role' => AlarmOwnerRole::IT_SUPPORT,
                'message' => 'PLC heartbeat latency exceeded 1.0s for 3 consecutive cycles.',
                'alarm_code' => 'DEV-PLC-HBT-SLOW',
                'first_seen_at' => '-5 hours',
                'last_seen_at' => '-4 hours',
                'occurrence_count' => 6,
                'acknowledged_at' => '-4 hours',
                'acknowledged_by_name' => 'IT Support',
                'resolved_at' => '-3 hours',
                'resolved_by_name' => 'IT Support',
                'resolution_reason' => 'Network switch reset; heartbeat returned to normal.',
                'corrective_action' => 'Replaced switch port; monitor next 24h.',
                'closed_at' => '-3 hours',
                'linked_entity_type' => $plc ? HardwareDevice::class : null,
                'linked_entity_id' => $plc?->id,
                'linked_entity_label' => $plc?->name,
            ],
            // 9) Info loading — paused per dispatcher (suppressed_maintenance)
            [
                'alarm_no' => 'ALM-2026-0009',
                'title' => 'Loading paused per dispatcher',
                'severity' => AlarmSeverity::INFO,
                'status' => AlarmStatus::SUPPRESSED_MAINTENANCE,
                'category' => AlarmCategory::LOADING_PROCESS,
                'blocking_impact' => AlarmBlockingImpact::WARNING_ONLY,
                'source_type' => AlarmSourceType::CENTRAL_PLC,
                'source_id' => 'central-pause',
                'source_label' => 'Central scheduler',
                'location' => HardwarePhysicalLocation::CONTROL_ROOM,
                'owner_role' => AlarmOwnerRole::DISPATCHER,
                'message' => 'Loading queue paused by dispatcher for shift handover.',
                'alarm_code' => 'LOAD-PAUSE-DISPATCH',
                'first_seen_at' => '-50 minutes',
                'last_seen_at' => '-50 minutes',
                'occurrence_count' => 1,
                'linked_entity_type' => $orderLO0004 ? \App\Models\LoadingOrder::class : null,
                'linked_entity_id' => $orderLO0004?->id,
                'linked_entity_label' => $orderLO0004?->order_no,
            ],
        ];

        foreach ($rows as $r) {
            $payload = $this->expandTimestamps($r);
            Alarm::query()->updateOrCreate(
                ['alarm_no' => $r['alarm_no']],
                array_merge(['id' => (string) Str::uuid()], $payload)
            );
        }
    }

    /**
     * Convert relative strings ("-12 minutes") to Carbon-modify offsets
     * so the seeded timestamps stay realistic relative to "now".
     */
    protected function expandTimestamps(array $r): array
    {
        foreach (['first_seen_at', 'last_seen_at', 'acknowledged_at', 'in_progress_at', 'resolved_at', 'closed_at'] as $col) {
            if (isset($r[$col]) && is_string($r[$col]) && str_contains($r[$col], ' ')) {
                $r[$col] = now()->modify($r[$col]);
            }
        }
        return $r;
    }
}
