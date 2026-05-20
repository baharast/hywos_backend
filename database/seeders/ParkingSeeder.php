<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Parking;
use App\Models\Site;
use App\Models\PlantArea;
use Illuminate\Support\Str;

class ParkingSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::first();
        $area = PlantArea::first();
        if (! $site || ! $area) {
            return;
        }

        Parking::firstOrCreate([
            'code' => 'PARK-1'
        ], [
            'id' => (string) Str::uuid(),
            'name' => 'Parking 1',
            'site_id' => $site->id,
            'area_id' => $area->id,
            'capacity' => 50,
            'occupied_count' => 5,
            'status_code' => 'open',
            'is_active' => true,
        ]);
    }
}
