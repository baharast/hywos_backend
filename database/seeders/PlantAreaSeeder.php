<?php

namespace Database\Seeders;

use App\Enums\PlantAreaType;
use App\Models\PlantArea;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlantAreaSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('code', 'SITE-1')->first() ?? Site::first();
        if (! $site) {
            return;
        }

        $areas = [
            ['code' => 'AREA-GATE', 'name' => 'Gate Area', 'area_type' => PlantAreaType::GATE_AREA],
            ['code' => 'AREA-LOAD', 'name' => 'Loading Area', 'area_type' => PlantAreaType::LOADING_AREA],
            ['code' => 'AREA-PARK', 'name' => 'Parking Area', 'area_type' => PlantAreaType::PARKING_AREA],
            ['code' => 'AREA-CTRL', 'name' => 'Control Room', 'area_type' => PlantAreaType::CONTROL_ROOM],
        ];

        foreach ($areas as $a) {
            PlantArea::firstOrCreate(
                ['site_id' => $site->id, 'code' => $a['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $a['name'],
                    'area_type' => $a['area_type'],
                    'status' => 'draft',
                    'is_active' => true,
                ]
            );
        }
    }
}
