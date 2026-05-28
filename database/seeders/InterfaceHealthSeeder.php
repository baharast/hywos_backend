<?php

namespace Database\Seeders;

use App\Enums\InterfaceBlockingLevel;
use App\Enums\InterfaceDirection;
use App\Enums\InterfaceFamily;
use App\Enums\InterfaceProtocol;
use App\Enums\InterfaceStatus;
use App\Models\InterfaceHealth;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * V1.4 §9 — exactly 11 exact interfaces. Status mix exercises every
 * tone path (online / warning / fault) so the FE can verify the badge
 * palette without a separate fixture.
 *
 * IMPORTANT — DatabaseSeeder uses `WithoutModelEvents`, which suppresses
 * the model's booted() UUID hook. We MUST pass `id` explicitly, looking
 * up the existing row first so re-seeds keep stable ids (soft FKs from
 * audit_logs and future modules don't dangle). Same pattern as
 * AnalysisDeviceSeeder (commit d4a50ae).
 */
class InterfaceHealthSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rows() as $row) {
            $code = $row['exact_interface_id'];
            $existingId = InterfaceHealth::query()
                ->where('exact_interface_id', $code)
                ->value('id');

            InterfaceHealth::updateOrCreate(
                ['exact_interface_id' => $code],
                array_merge(
                    $row,
                    ['id' => $existingId ?? (string) Str::uuid()]
                )
            );
        }
    }

    /**
     * Each row matches one line of the V1.4 §9 exact-interface table.
     * `data_status='tbc_engineering'` on the two SAP rows surfaces the
     * spec's "SAP NCo / IDoc / OData to be clarified" caveat.
     */
    protected function rows(): array
    {
        return [
            [
                'exact_interface_id' => 'IF-SAP-ORDER-IN',
                'name' => 'SAP inbound order import',
                'family' => InterfaceFamily::SAP,
                'protocol' => InterfaceProtocol::SAP_RFC_IDOC,
                'direction' => InterfaceDirection::INBOUND,
                'source_label' => 'SAP Production System',
                'target_label' => 'FillTrack Local Server',
                'status' => InterfaceStatus::ONLINE,
                'blocking_level' => InterfaceBlockingLevel::CRITICAL,
                'last_success_at' => now()->subMinutes(3),
                'last_failure_at' => null,
                'queue_count' => 0,
                'failed_today' => 0,
                'fallback_behavior' => 'No new orders can be imported. Operators may run only already-imported orders.',
                'local_operation_allowed' => true,
                'affected_process_label' => 'Order import / scheduling',
                'data_status' => 'tbc_engineering',
                'source_basis' => 'V1.4 §9',
            ],
            [
                'exact_interface_id' => 'IF-SAP-STATUS-OUT',
                'name' => 'SAP outbound status / feedback',
                'family' => InterfaceFamily::SAP,
                'protocol' => InterfaceProtocol::SAP_RFC_IDOC,
                'direction' => InterfaceDirection::OUTBOUND,
                'source_label' => 'FillTrack Local Server',
                'target_label' => 'SAP Production System',
                'status' => InterfaceStatus::WARNING,
                'blocking_level' => InterfaceBlockingLevel::OPERATIONAL,
                'last_success_at' => now()->subHour(),
                'last_failure_at' => now()->subMinutes(40),
                'queue_count' => 12,
                'failed_today' => 2,
                'last_error_text' => 'Customer system slow ack',
                'fallback_behavior' => 'Outbound status / quantity / quality / document feedback queues locally until SAP recovers.',
                'local_operation_allowed' => true,
                'affected_process_label' => 'Order status / document feedback to SAP',
                'data_status' => 'tbc_engineering',
                'source_basis' => 'V1.4 §9',
            ],
            [
                'exact_interface_id' => 'IF-OPCUA-PLANT',
                'name' => 'OPC UA plant link',
                'family' => InterfaceFamily::PLC_FIELDBUS,
                'protocol' => InterfaceProtocol::OPCUA,
                'direction' => InterfaceDirection::BIDIRECTIONAL,
                'source_label' => 'FillTrack',
                'target_label' => 'Plant PLC (OPC UA Sign & Encrypt)',
                'status' => InterfaceStatus::ONLINE,
                'blocking_level' => InterfaceBlockingLevel::CRITICAL,
                'last_success_at' => now()->subSeconds(20),
                'fallback_behavior' => 'Automatic loading release and gate diagnostics depend on this link. No safe local fallback for automatic loading.',
                'local_operation_allowed' => false,
                'affected_process_label' => 'Automatic loading release / gate diagnostics',
                'source_basis' => 'V1.4 §9',
            ],
            [
                'exact_interface_id' => 'IF-MODBUS-FILL',
                'name' => 'Modbus filling-panel link',
                'family' => InterfaceFamily::PLC_FIELDBUS,
                'protocol' => InterfaceProtocol::MODBUS_TCP,
                'direction' => InterfaceDirection::BIDIRECTIONAL,
                'source_label' => 'FillTrack',
                'target_label' => 'Filling Panel (Modbus TCP over fibre)',
                'status' => InterfaceStatus::ONLINE,
                'blocking_level' => InterfaceBlockingLevel::CRITICAL,
                'last_success_at' => now()->subSeconds(30),
                'fallback_behavior' => 'Filling panel status / coordination unavailable. Manual coordination required at the bay.',
                'local_operation_allowed' => true,
                'affected_process_label' => 'Filling panel coordination',
                'source_basis' => 'V1.4 §9',
            ],
            [
                'exact_interface_id' => 'IF-PROFINET-ELY',
                'name' => 'Profinet electrolyzer link',
                'family' => InterfaceFamily::PLC_FIELDBUS,
                'protocol' => InterfaceProtocol::PROFINET,
                'direction' => InterfaceDirection::BIDIRECTIONAL,
                'source_label' => 'FillTrack',
                'target_label' => 'Electrolyzer PLC (Profinet + hardwired safety)',
                'status' => InterfaceStatus::WARNING,
                'blocking_level' => InterfaceBlockingLevel::OPERATIONAL,
                'last_success_at' => now()->subMinutes(2),
                'last_failure_at' => now()->subMinutes(10),
                'queue_count' => 0,
                'failed_today' => 1,
                'last_error_text' => 'Profinet device 10A intermittent ack',
                'fallback_behavior' => 'Hardwired safety remains active. Electrolyzer production diagnostics may show stale values until link stabilises.',
                'local_operation_allowed' => true,
                'affected_process_label' => 'Electrolyzer production availability / safety diagnostics',
                'source_basis' => 'V1.4 §9',
            ],
            [
                'exact_interface_id' => 'IF-PROFIBUS-COMP',
                'name' => 'Profibus compression link',
                'family' => InterfaceFamily::PLC_FIELDBUS,
                'protocol' => InterfaceProtocol::PROFIBUS,
                'direction' => InterfaceDirection::BIDIRECTIONAL,
                'source_label' => 'FillTrack',
                'target_label' => 'Compression PLC (Profibus + hardwired)',
                'status' => InterfaceStatus::ONLINE,
                'blocking_level' => InterfaceBlockingLevel::OPERATIONAL,
                'last_success_at' => now()->subSeconds(45),
                'fallback_behavior' => 'Hardwired signals remain. Compression availability dashboard depends on this link.',
                'local_operation_allowed' => true,
                'affected_process_label' => 'Compression availability',
                'source_basis' => 'V1.4 §9',
            ],
            [
                'exact_interface_id' => 'IF-ANALYSIS-52',
                'name' => 'Analysis bus / server integration (52-series)',
                'family' => InterfaceFamily::ANALYSIS_INTERFACE,
                'protocol' => InterfaceProtocol::ETHERNET,
                'direction' => InterfaceDirection::INBOUND,
                'source_label' => 'OrthoSmart Analyser',
                'target_label' => 'FillTrack Analysis Service',
                'status' => InterfaceStatus::ONLINE,
                'blocking_level' => InterfaceBlockingLevel::CRITICAL,
                'last_success_at' => now()->subMinutes(2),
                'fallback_behavior' => 'No analyser values reach the dashboard. Quality decisions, documents and exit are blocked.',
                'local_operation_allowed' => false,
                'affected_process_label' => 'Quality decisions, documents, exit',
                'source_basis' => 'V1.4 §9',
            ],
            [
                'exact_interface_id' => 'IF-GATE-ETHERNET',
                'name' => 'Gate Ethernet link',
                'family' => InterfaceFamily::GATE_ETHERNET,
                'protocol' => InterfaceProtocol::ETHERNET,
                'direction' => InterfaceDirection::BIDIRECTIONAL,
                'source_label' => 'FillTrack',
                'target_label' => 'Gate Controllers',
                'status' => InterfaceStatus::ONLINE,
                'blocking_level' => InterfaceBlockingLevel::CRITICAL,
                'last_success_at' => now()->subSeconds(15),
                'fallback_behavior' => 'Automated entry/exit release affected. Manual gate operation by operator with audit trail.',
                'local_operation_allowed' => true,
                'affected_process_label' => 'Automated entry / exit release',
                'source_basis' => 'V1.4 §9',
            ],
            [
                // The printer fault here is intentionally aligned with the
                // Hardware Devices Task A printer narrative so the FE can
                // cross-link from the printer card to this interface row.
                'exact_interface_id' => 'IF-PRINT-SERVICE',
                'name' => 'Print service',
                'family' => InterfaceFamily::PRINTER_SERVICE,
                'protocol' => InterfaceProtocol::PRINTER_LOCAL,
                'direction' => InterfaceDirection::OUTBOUND,
                'source_label' => 'FillTrack',
                'target_label' => 'Operator printer (Brother HL-L8260CDW @ Control Room)',
                'status' => InterfaceStatus::FAULT,
                'blocking_level' => InterfaceBlockingLevel::OPERATIONAL,
                'last_success_at' => now()->subHours(2),
                'last_failure_at' => now()->subMinutes(5),
                'queue_count' => 4,
                'failed_today' => 7,
                'last_error_text' => 'Operator printer offline',
                'fallback_behavior' => 'Operator printer fault — mandatory document output may be blocked. Configured replacement printer may apply.',
                'local_operation_allowed' => true,
                'affected_process_label' => 'Mandatory document output and exit',
                'source_basis' => 'V1.4 §9',
            ],
            [
                'exact_interface_id' => 'IF-CLOUD-SYNC',
                'name' => 'Cloud sync',
                'family' => InterfaceFamily::CLOUD_SYNC,
                'protocol' => InterfaceProtocol::HTTPS,
                'direction' => InterfaceDirection::OUTBOUND,
                'source_label' => 'FillTrack',
                'target_label' => 'Tyczka Cloud Sync (HTTPS)',
                'status' => InterfaceStatus::ONLINE,
                'blocking_level' => InterfaceBlockingLevel::NON_BLOCKING,
                'last_success_at' => now()->subMinutes(8),
                'fallback_behavior' => 'Local plant operation continues. Cloud-backed analytics and offsite backup are paused.',
                'local_operation_allowed' => true,
                'affected_process_label' => 'Offsite analytics / backup',
                'source_basis' => 'V1.4 §9',
            ],
            [
                'exact_interface_id' => 'IF-VPN-REMOTE',
                'name' => 'VPN remote support',
                'family' => InterfaceFamily::VPN_REMOTE_SUPPORT,
                'protocol' => InterfaceProtocol::VPN,
                'direction' => InterfaceDirection::BIDIRECTIONAL,
                'source_label' => 'Vendor support',
                'target_label' => 'FillTrack VPN endpoint',
                'status' => InterfaceStatus::ONLINE,
                'blocking_level' => InterfaceBlockingLevel::NON_BLOCKING,
                'last_success_at' => now()->subMinutes(15),
                'fallback_behavior' => 'Remote support / security tunnel unavailable. No direct loading impact.',
                'local_operation_allowed' => true,
                'affected_process_label' => 'Vendor remote support',
                'source_basis' => 'V1.4 §9',
            ],
        ];
    }
}
