<?php

namespace Database\Seeders;

use App\Models\Parking;
use App\Models\PlantArea;
use App\Models\PlantConfiguration;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ParkingSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('code', 'SITE-1')->first() ?? Site::first();
        if (! $site) {
            return;
        }

        $parkingArea = PlantArea::where('site_id', $site->id)->where('code', 'AREA-PARK')->first()
            ?? PlantArea::where('site_id', $site->id)->first();
        $config = PlantConfiguration::where('site_id', $site->id)->first();

        $parkings = [
            ['code' => 'PARK-1', 'name' => 'Trailer Pool A', 'space_type' => 'empty_trailer', 'capacity' => 50, 'occupied_count' => 5, 'status_code' => 'free'],
            ['code' => 'PARK-2', 'name' => 'Trailer Pool B (Loaded)', 'space_type' => 'loaded_trailer', 'capacity' => 40, 'occupied_count' => 12, 'status_code' => 'free'],
            ['code' => 'PARK-3', 'name' => 'General Parking', 'space_type' => 'general', 'capacity' => 60, 'occupied_count' => 18, 'status_code' => 'free'],
            ['code' => 'PARK-4', 'name' => 'Service Parking', 'space_type' => 'service', 'capacity' => 30, 'occupied_count' => 3, 'status_code' => 'free'],
        ];

        foreach ($parkings as $parking) {
            Parking::firstOrCreate(
                ['code' => $parking['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $parking['name'],
                    'site_id' => $site->id,
                    'area_id' => $parkingArea?->id,
                    'plant_configuration_id' => $config?->id,
                    'space_type' => $parking['space_type'],
                    'capacity' => $parking['capacity'],
                    'occupied_count' => $parking['occupied_count'],
                    'status_code' => $parking['status_code'],
                    'is_active' => true,
                ]
            );
        }
    }
}
