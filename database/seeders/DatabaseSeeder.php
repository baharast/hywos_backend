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
        ]);
    }
}
