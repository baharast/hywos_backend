<?php

namespace Database\Seeders;

use App\Enums\HardwareConnectionTestResult;
use App\Enums\HardwareDeviceCriticality;
use App\Enums\HardwareDeviceHealth;
use App\Enums\HardwareDeviceSubsystem;
use App\Enums\HardwareDeviceType;
use App\Enums\HardwarePhysicalLocation;
use App\Models\HardwareDevice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo hardware registry per FillTrack System & Devices V1.4.
 *
 * 28 rows exercise every device_type group + every health tone so the
 * Operational Summary Bar lights up the right counters and the FE can
 * verify badge rendering / default sort / service-mode tone.
 *
 * CRITICAL — DatabaseSeeder uses `WithoutModelEvents`, so the booted()
 * UUID hook does NOT fire. We pass `id` explicitly (re-using the existing
 * id when the row already exists so re-seeds stay stable). Same pattern
 * as AnalysisDeviceSeeder after commit d4a50ae.
 *
 * Spec §11 TBC rule: rows with data_status='tbc_engineering' MUST stay in
 * the registry but be hidden from operational filter dropdowns. The
 * Trailer-Chip Reader (§7) is the canonical TBC example.
 */
class HardwareDeviceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rows() as $row) {
            $tag = $row['asset_tag'];
            $existingId = HardwareDevice::query()->where('asset_tag', $tag)->value('id');

            HardwareDevice::updateOrCreate(
                ['asset_tag' => $tag],
                array_merge(
                    [
                        'id' => $existingId ?? (string) Str::uuid(),
                        'last_seen_at' => now()->subSeconds(30),
                        'last_event_at' => now()->subMinutes(2),
                        'service_mode' => false,
                        'criticality' => HardwareDeviceCriticality::MEDIUM,
                        'health' => HardwareDeviceHealth::ONLINE,
                        'source_basis' => 'exact_data_v1',
                    ],
                    $row
                )
            );
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function rows(): array
    {
        return [
            // ---- Smart Gate Controllers ----
            [
                'asset_tag' => 'HD-GATE-ENTRY-01',
                'vendor_tag' => 'GW-AXIS-A1001-E',
                'name' => 'Entry Gate Smart Panel',
                'device_type' => HardwareDeviceType::SMART_GATE_CONTROLLER,
                'subsystem' => HardwareDeviceSubsystem::GATE,
                'physical_location' => HardwarePhysicalLocation::ENTRY_GATE,
                'criticality' => HardwareDeviceCriticality::CRITICAL,
                'health' => HardwareDeviceHealth::ONLINE,
                'affected_process' => 'entry_exit',
                'affected_process_label' => 'Entry gate access control',
                'protocol' => 'Ethernet',
                'last_message' => 'Last access decision: accepted (driver chip)',
            ],
            [
                'asset_tag' => 'HD-GATE-EXIT-01',
                'vendor_tag' => 'GW-AXIS-A1001-X',
                'name' => 'Exit Gate Smart Panel',
                'device_type' => HardwareDeviceType::SMART_GATE_CONTROLLER,
                'subsystem' => HardwareDeviceSubsystem::GATE,
                'physical_location' => HardwarePhysicalLocation::EXIT_GATE,
                'criticality' => HardwareDeviceCriticality::CRITICAL,
                'health' => HardwareDeviceHealth::ONLINE,
                'affected_process' => 'entry_exit',
                'affected_process_label' => 'Exit gate access control',
                'protocol' => 'Ethernet',
                'last_message' => 'Last access decision: accepted',
            ],

            // ---- Driver Terminal ----
            [
                'asset_tag' => 'HD-TERM-DRV-01',
                'vendor_tag' => 'KIOSK-FT-DRV-01',
                'name' => 'Driver Terminal (check-in cabinet)',
                'device_type' => HardwareDeviceType::HMI_PANEL,
                'subsystem' => HardwareDeviceSubsystem::DRIVER_TERMINAL,
                'physical_location' => HardwarePhysicalLocation::DRIVER_TERMINAL,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::ONLINE,
                'affected_process' => 'driver_login',
                'affected_process_label' => 'Driver login + workflow kiosk',
                'protocol' => 'Ethernet',
                'last_message' => 'Active driver session: DRV-1001',
            ],

            // ---- Filling HMI Panels 01-06 ----
            [
                'asset_tag' => 'HD-PANEL-FILL-01',
                'vendor_tag' => 'HMI-SIEMENS-TP1500-01',
                'name' => 'Filling Bay 01 HMI Panel',
                'device_type' => HardwareDeviceType::HMI_PANEL,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_01,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::ONLINE,
                'affected_process' => 'loading_release',
                'affected_process_label' => 'Bay 01 loading release (380 bar)',
                'protocol' => 'Profinet',
                'last_message' => 'Idle — ready for next loading',
            ],
            [
                'asset_tag' => 'HD-PANEL-FILL-02',
                'vendor_tag' => 'HMI-SIEMENS-TP1500-02',
                'name' => 'Filling Bay 02 HMI Panel',
                'device_type' => HardwareDeviceType::HMI_PANEL,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_02,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::ONLINE,
                'affected_process' => 'loading_release',
                'affected_process_label' => 'Bay 02 loading release (380 bar)',
                'protocol' => 'Profinet',
                'last_message' => 'Filling in progress',
            ],
            [
                'asset_tag' => 'HD-PANEL-FILL-03',
                'vendor_tag' => 'HMI-SIEMENS-TP1500-03',
                'name' => 'Filling Bay 03 HMI Panel',
                'device_type' => HardwareDeviceType::HMI_PANEL,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_03,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::ONLINE,
                'affected_process' => 'loading_release',
                'affected_process_label' => 'Bay 03 loading release (380 bar)',
                'protocol' => 'Profinet',
            ],
            [
                'asset_tag' => 'HD-PANEL-FILL-04',
                'vendor_tag' => 'HMI-SIEMENS-TP1500-04',
                'name' => 'Filling Bay 04 HMI Panel',
                'device_type' => HardwareDeviceType::HMI_PANEL,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_04,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::ONLINE,
                'affected_process' => 'loading_release',
                'affected_process_label' => 'Bay 04 loading release (380 bar)',
                'protocol' => 'Profinet',
            ],
            [
                'asset_tag' => 'HD-PANEL-FILL-05',
                'vendor_tag' => 'HMI-SIEMENS-TP1500-05',
                'name' => 'Filling Bay 05 HMI Panel',
                'device_type' => HardwareDeviceType::HMI_PANEL,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_05,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::WARNING,
                'affected_process' => 'loading_release',
                'affected_process_label' => 'Bay 05 loading release (380/300/200 bar)',
                'protocol' => 'Profinet',
                'last_message' => 'Sample pressure low — operator notified',
            ],
            [
                'asset_tag' => 'HD-PANEL-FILL-06',
                'vendor_tag' => 'HMI-SIEMENS-TP1500-06',
                'name' => 'Filling Bay 06 HMI Panel',
                'device_type' => HardwareDeviceType::HMI_PANEL,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_06,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::ALARM,
                'affected_process' => 'loading_release',
                'affected_process_label' => 'Bay 06 loading release (380/300/200 bar)',
                'protocol' => 'Profinet',
                'last_message' => 'Interlock triggered — bay 06 stopped, operator must verify',
            ],

            // ---- Operator Clients ----
            [
                'asset_tag' => 'HD-OPCLIENT-01',
                'vendor_tag' => 'WS-DELL-OP-01',
                'name' => 'Operator Client 1',
                'device_type' => HardwareDeviceType::SERVER,
                'subsystem' => HardwareDeviceSubsystem::INTEGRATION,
                'physical_location' => HardwarePhysicalLocation::CONTROL_ROOM,
                'criticality' => HardwareDeviceCriticality::MEDIUM,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Ethernet',
            ],
            [
                'asset_tag' => 'HD-OPCLIENT-02',
                'vendor_tag' => 'WS-DELL-OP-02',
                'name' => 'Operator Client 2',
                'device_type' => HardwareDeviceType::SERVER,
                'subsystem' => HardwareDeviceSubsystem::INTEGRATION,
                'physical_location' => HardwarePhysicalLocation::CONTROL_ROOM,
                'criticality' => HardwareDeviceCriticality::MEDIUM,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Ethernet',
            ],

            // ---- Compressors ----
            [
                'asset_tag' => 'HD-COMP-A',
                'vendor_tag' => 'CMP-HIPERBARIC-A',
                'name' => 'Compressor A',
                'device_type' => HardwareDeviceType::COMPRESSOR_CONTROLLER,
                'subsystem' => HardwareDeviceSubsystem::COMPRESSOR,
                'physical_location' => HardwarePhysicalLocation::COMPRESSOR_CONTAINER,
                'criticality' => HardwareDeviceCriticality::CRITICAL,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Profibus',
            ],
            [
                'asset_tag' => 'HD-COMP-B',
                'vendor_tag' => 'CMP-HIPERBARIC-B',
                'name' => 'Compressor B',
                'device_type' => HardwareDeviceType::COMPRESSOR_CONTROLLER,
                'subsystem' => HardwareDeviceSubsystem::COMPRESSOR,
                'physical_location' => HardwarePhysicalLocation::COMPRESSOR_CONTAINER,
                'criticality' => HardwareDeviceCriticality::CRITICAL,
                'health' => HardwareDeviceHealth::OFFLINE,
                'protocol' => 'Profibus',
                'last_message' => 'Compressor B offline — maintenance crew dispatched',
                'last_seen_at' => now()->subHours(2),
                'last_event_at' => now()->subHours(2),
            ],
            [
                'asset_tag' => 'HD-COMP-D',
                'vendor_tag' => 'CMP-HIPERBARIC-D',
                'name' => 'Compressor D',
                'device_type' => HardwareDeviceType::COMPRESSOR_CONTROLLER,
                'subsystem' => HardwareDeviceSubsystem::COMPRESSOR,
                'physical_location' => HardwarePhysicalLocation::COMPRESSOR_CONTAINER,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Profibus',
            ],
            [
                'asset_tag' => 'HD-COMP-E',
                'vendor_tag' => 'CMP-HIPERBARIC-E',
                'name' => 'Compressor E',
                'device_type' => HardwareDeviceType::COMPRESSOR_CONTROLLER,
                'subsystem' => HardwareDeviceSubsystem::COMPRESSOR,
                'physical_location' => HardwarePhysicalLocation::COMPRESSOR_CONTAINER,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::WARNING,
                'protocol' => 'Profibus',
                'last_message' => 'Discharge temperature trending high',
            ],

            // ---- Electrolyzers ----
            [
                'asset_tag' => 'HD-ELY-10A',
                'vendor_tag' => 'HyLYZER-10A',
                'name' => 'Electrolyzer 10A',
                'device_type' => HardwareDeviceType::ELECTROLYZER_CONTROLLER,
                'subsystem' => HardwareDeviceSubsystem::ELECTROLYZER,
                'physical_location' => HardwarePhysicalLocation::ELECTROLYZER_CONTAINER,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Profinet',
            ],
            [
                'asset_tag' => 'HD-ELY-10B',
                'vendor_tag' => 'HyLYZER-10B',
                'name' => 'Electrolyzer 10B',
                'device_type' => HardwareDeviceType::ELECTROLYZER_CONTROLLER,
                'subsystem' => HardwareDeviceSubsystem::ELECTROLYZER,
                'physical_location' => HardwarePhysicalLocation::ELECTROLYZER_CONTAINER,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::SERVICE_MODE,
                'protocol' => 'Profinet',
                'service_mode' => true,
                'service_mode_reason' => 'Scheduled maintenance window — stack inspection',
                'service_mode_set_at' => now()->subHours(4),
                'service_mode_expected_end_at' => now()->addHours(20),
                'last_message' => 'Service mode set: scheduled stack inspection',
            ],

            // ---- Analyzer (soft-link to AnalysisDevice AN-OS-01 via name) ----
            [
                'asset_tag' => 'HD-ANALYZER-52',
                'vendor_tag' => 'AN-OS-01',
                'name' => 'OrthoSmart Analyser (Analysis Unit 52)',
                'device_type' => HardwareDeviceType::ANALYZER,
                'subsystem' => HardwareDeviceSubsystem::ANALYSIS,
                'physical_location' => HardwarePhysicalLocation::TECHNICAL_ROOM,
                'criticality' => HardwareDeviceCriticality::CRITICAL,
                'health' => HardwareDeviceHealth::ONLINE,
                'affected_process' => 'analysis_quality',
                'affected_process_label' => 'H2 5.0 6-component analysis',
                'protocol' => 'Ethernet',
            ],

            // ---- Printers ----
            [
                'asset_tag' => 'HD-PRINTER-DRV-01',
                'vendor_tag' => 'PRINTER-EPSON-DRV',
                'name' => 'Driver Terminal Printer',
                'device_type' => HardwareDeviceType::PRINTER,
                'subsystem' => HardwareDeviceSubsystem::PRINTING,
                'physical_location' => HardwarePhysicalLocation::DRIVER_TERMINAL,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::ONLINE,
                'affected_process' => 'document_output',
                'affected_process_label' => 'Driver delivery note + certificate printing',
                'protocol' => 'USB/Local Print',
            ],
            [
                'asset_tag' => 'HD-PRINTER-OPR-01',
                'vendor_tag' => 'PRINTER-HP-OPR',
                'name' => 'Operator / Control Room Printer',
                'device_type' => HardwareDeviceType::PRINTER,
                'subsystem' => HardwareDeviceSubsystem::PRINTING,
                'physical_location' => HardwarePhysicalLocation::CONTROL_ROOM,
                'criticality' => HardwareDeviceCriticality::CRITICAL,
                'health' => HardwareDeviceHealth::FAULT,
                'affected_process' => 'document_output',
                'affected_process_label' => 'Mandatory operator document output',
                'protocol' => 'Browser/HTTP',
                'last_message' => 'Printer offline — exit-blocking document output stalled (mirrors IF-PRINT-SERVICE)',
                'last_event_at' => now()->subMinutes(5),
            ],

            // ---- RFID Readers (one per filling bay) ----
            [
                'asset_tag' => 'HD-RFID-BAY-01',
                'vendor_tag' => 'RFID-FELICA-B01',
                'name' => 'RFID Reader Bay 01',
                'device_type' => HardwareDeviceType::RFID_READER,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_01,
                'criticality' => HardwareDeviceCriticality::MEDIUM,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Ethernet',
            ],
            [
                'asset_tag' => 'HD-RFID-BAY-02',
                'vendor_tag' => 'RFID-FELICA-B02',
                'name' => 'RFID Reader Bay 02',
                'device_type' => HardwareDeviceType::RFID_READER,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_02,
                'criticality' => HardwareDeviceCriticality::MEDIUM,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Ethernet',
            ],
            [
                'asset_tag' => 'HD-RFID-BAY-03',
                'vendor_tag' => 'RFID-FELICA-B03',
                'name' => 'RFID Reader Bay 03',
                'device_type' => HardwareDeviceType::RFID_READER,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_03,
                'criticality' => HardwareDeviceCriticality::MEDIUM,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Ethernet',
            ],
            [
                'asset_tag' => 'HD-RFID-BAY-04',
                'vendor_tag' => 'RFID-FELICA-B04',
                'name' => 'RFID Reader Bay 04',
                'device_type' => HardwareDeviceType::RFID_READER,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_04,
                'criticality' => HardwareDeviceCriticality::MEDIUM,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Ethernet',
            ],
            [
                'asset_tag' => 'HD-RFID-BAY-05',
                'vendor_tag' => 'RFID-FELICA-B05',
                'name' => 'RFID Reader Bay 05',
                'device_type' => HardwareDeviceType::RFID_READER,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_05,
                'criticality' => HardwareDeviceCriticality::MEDIUM,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Ethernet',
            ],
            [
                'asset_tag' => 'HD-RFID-BAY-06',
                'vendor_tag' => 'RFID-FELICA-B06',
                'name' => 'RFID Reader Bay 06',
                'device_type' => HardwareDeviceType::RFID_READER,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_06,
                'criticality' => HardwareDeviceCriticality::MEDIUM,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Ethernet',
            ],

            // ---- Trailer-Chip Reader (TBC per spec §7) ----
            [
                'asset_tag' => 'HD-READER-TRAILER-01',
                'vendor_tag' => null,
                'name' => 'Trailer Chip Reader (engineering TBC)',
                'device_type' => HardwareDeviceType::TRAILER_CHIP_READER,
                'subsystem' => HardwareDeviceSubsystem::FILLING,
                'physical_location' => HardwarePhysicalLocation::FILLING_BAY_01,
                'criticality' => HardwareDeviceCriticality::MEDIUM,
                'health' => HardwareDeviceHealth::UNKNOWN,
                'data_status' => 'tbc_engineering',
                'last_message' => 'Quantity/location TBC — exact placement awaiting engineering confirmation',
                'source_basis' => 'view_no_data_v1',
            ],

            // ---- Network device + Server ----
            [
                'asset_tag' => 'HD-NET-SWITCH-01',
                'vendor_tag' => 'CISCO-IE3300-01',
                'name' => 'On-site Industrial Switch',
                'device_type' => HardwareDeviceType::NETWORK_DEVICE,
                'subsystem' => HardwareDeviceSubsystem::NETWORK,
                'physical_location' => HardwarePhysicalLocation::ON_SITE_SERVER_ZONE,
                'criticality' => HardwareDeviceCriticality::HIGH,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Ethernet',
            ],
            [
                'asset_tag' => 'HD-SRV-LOCAL-01',
                'vendor_tag' => 'DELL-R750-FT-01',
                'name' => 'FillTrack Local Server',
                'device_type' => HardwareDeviceType::SERVER,
                'subsystem' => HardwareDeviceSubsystem::INTEGRATION,
                'physical_location' => HardwarePhysicalLocation::ON_SITE_SERVER_ZONE,
                'criticality' => HardwareDeviceCriticality::CRITICAL,
                'health' => HardwareDeviceHealth::ONLINE,
                'protocol' => 'Ethernet',
            ],
        ];
    }
}
