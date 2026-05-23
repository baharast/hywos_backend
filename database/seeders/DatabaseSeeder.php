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
        //   -> gates / terminals / baylines / parkings  (all reference plant_configuration_id)
        //   -> users -> domain master data
        $this->call([
            CompanySeeder::class,
            RolePermissionSeeder::class,
            SiteSeeder::class,
            PlantAreaSeeder::class,
            PlantConfigurationSeeder::class,
            GateSeeder::class,
            TerminalPanelSeeder::class,
            BayLineSeeder::class,
            ParkingSeeder::class,
            AdminUserSeeder::class,
            CustomerSeeder::class,
        ]);
    }
}
