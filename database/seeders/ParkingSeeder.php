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

        $parkings = [
            ['code' => 'PARK-1', 'name' => 'Parking 1', 'capacity' => 50, 'occupied_count' => 5, 'status_code' => 'open'],
            ['code' => 'PARK-2', 'name' => 'Parking 2', 'capacity' => 40, 'occupied_count' => 12, 'status_code' => 'open'],
            ['code' => 'PARK-3', 'name' => 'Parking 3', 'capacity' => 60, 'occupied_count' => 18, 'status_code' => 'open'],
            ['code' => 'PARK-4', 'name' => 'Parking 4', 'capacity' => 30, 'occupied_count' => 3, 'status_code' => 'open'],
        ];

        foreach ($parkings as $parking) {
            Parking::firstOrCreate([
                'code' => $parking['code'],
            ], [
                'id' => (string) Str::uuid(),
                'name' => $parking['name'],
                'site_id' => $site->id,
                'area_id' => $area->id,
                'capacity' => $parking['capacity'],
                'occupied_count' => $parking['occupied_count'],
                'status_code' => $parking['status_code'],
                'is_active' => true,
            ]);
        }
    }
}
