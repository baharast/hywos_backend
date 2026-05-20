<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed order: permissions/roles -> sites/areas -> users -> domain data
        $this->call([
            \Database\Seeders\RolePermissionSeeder::class,
            \Database\Seeders\SiteSeeder::class,
            \Database\Seeders\PlantAreaSeeder::class,
            \Database\Seeders\AdminUserSeeder::class,
            \Database\Seeders\BayLineSeeder::class,
            \Database\Seeders\ParkingSeeder::class,
        ]);
    }
}
