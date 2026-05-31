<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seed order matters:
        //   companies/permissions -> sites -> plant_areas -> plant_configurations
        //   -> gates / terminals / baylines (all reference plant_configuration_id)
        //   -> users -> domain master data -> loading_orders
        //   -> ParkingSeeder (2-slot board snapshots reference Trailer + LO-2026-0003)
        //   -> loading_operations
        $this->call([
            CompanySeeder::class,
            RolePermissionSeeder::class,
            SiteSeeder::class,
            PlantAreaSeeder::class,
            PlantConfigurationSeeder::class,
            GateSeeder::class,
            TerminalPanelSeeder::class,
            BayLineSeeder::class,
            AdminUserSeeder::class,
            CustomerSeeder::class,
            FreightForwarderSeeder::class,
            DriverSeeder::class,
            TrailerSeeder::class,
            TractorVehicleSeeder::class,
            TractorTrailerCouplingSeeder::class,
            ChipCardSeeder::class,
            TanSeeder::class,
            LoadingOrderSeeder::class,
            // SapSyncRecordSeeder references loading_orders rows (LO-2026-0002 /
            // -0003 / -0004) for linked-order snapshots, so it must run after
            // LoadingOrderSeeder.
            SapSyncRecordSeeder::class,
            // PlantVisits must run after LoadingOrderSeeder so PV-2026-0019
            // can back-fill loading_orders.active_plant_visit_id on LO-2026-0004.
            PlantVisitSeeder::class,
            // Terminal sessions soft-FK to plant_visits + clarification_cases.
            // Order vs ClarificationCaseSeeder is best-effort: the seeder
            // skips clarification linkage gracefully when CC rows aren't there.
            TerminalSessionSeeder::class,
            ClarificationCaseSeeder::class,
            // Trailer Parking 2-slot board (V2.1) — needs Trailer + LoadingOrder ids
            ParkingSeeder::class,
            LoadingOperationSeeder::class,
            // Operational Documents V1.2 — uses LoadingOrder + PlantVisit ids
            // for soft-FK snapshots; must run after both seeders above.
            OperationalDocumentSeeder::class,
            // Driver Terminal Login (V3) demo TANs + chip cards + terminal
            // activation. Must run after DriverSeeder (binds existing drivers
            // by code) and TerminalPanelSeeder (flips TERM-DRV-1 to active).
            TerminalAuthDemoSeeder::class,
            // Products & Quality Specifications (V2.1) — H2-5.0 active +
            // H2-3.5 draft. No external dependencies.
            ProductSpecificationSeeder::class,
            // Calibration profile for OrthoSmart. Soft-resolves device_id
            // from analysis_devices if Track A has landed; otherwise the
            // snapshot device_bmk='AN-OS-01' carries the link.
            CalibrationProfileSeeder::class,
            // Analysis Devices (V1) — 3 demo devices with mixed states so
            // the readiness dashboard exercises every tone. Must run BEFORE
            // any future seeder that references device ids (e.g. when
            // CalibrationProfileSeeder later resolves device_id from
            // analysis_devices.code).
            AnalysisDeviceSeeder::class,
            // Active Analyses (V1.4) — 7 demo analyses covering the V1.4
            // §6 action-availability matrix (release loading, repeat,
            // reject, repeat measurement, manual approval, running,
            // on-hold). Must run after LoadingOrderSeeder (binds
            // order_no), AnalysisDeviceSeeder (device_id) and
            // ProductSpecificationSeeder (product_spec_id).
            ActiveAnalysisSeeder::class,
            // Interface Health (V1.4 §9) — 11 exact interfaces seeded
            // standalone (no FK dependency on other modules; the
            // affected-process labels are denormalised strings).
            InterfaceHealthSeeder::class,
            // Hardware Devices (V1.4) — 28 demo rows for the master
            // device registry. Standalone: the other hardware tabs
            // (Terminals & Panels, Printers, Card Readers, PLC/OPC UA
            // Health) will soft-FK to hardware_devices.id in later
            // slices. One row (HD-ANALYZER-52) carries the same
            // vendor_tag as AnalysisDeviceSeeder's AN-OS-01 so support
            // can correlate, but the rows are NOT FK-linked.
            HardwareDeviceSeeder::class,
            // Printers tab demo jobs (V1.4 §6) — ~10 document_print_attempts
            // rows across HD-PRINTER-DRV-01 + HD-PRINTER-OPR-01. Must run
            // AFTER HardwareDeviceSeeder (resolves printer ids) and
            // OperationalDocumentSeeder (writes the parent documents).
            PrinterJobDemoSeeder::class,
            // Alarms & Events V1 §6 — 9 demo Active Alarms across the
            // severity × status × category matrix. Soft-FKs to existing
            // loading_orders and hardware_devices ids when present.
            AlarmSeeder::class,
            // Alarms & Events V1 §7.5 — 8 logbook entries covering the
            // category × follow-up matrix, plus 1 correction row for the
            // edit-history surface. Must run after AlarmSeeder so
            // linked_alarm_id resolves on the entries that reference
            // ALM-2026-0001 / ALM-2026-0008.
            LogbookSeeder::class,
            // Alarms & Events V1 §7.6 — 12 security events written into
            // the existing event_logs table with event_category='security'.
            // Soft-FKs to DRV-1001 / DRV-1005 when those drivers exist.
            SecurityEventSeeder::class,
        ]);
    }
}
