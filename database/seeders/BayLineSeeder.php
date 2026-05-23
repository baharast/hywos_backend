<?php

namespace Database\Seeders;

use App\Models\BayLine;
use App\Models\PlantArea;
use App\Models\PlantConfiguration;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BayLineSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('code', 'SITE-1')->first() ?? Site::first();
        if (! $site) {
            return;
        }

        $loadingArea = PlantArea::where('site_id', $site->id)->where('code', 'AREA-LOAD')->first()
            ?? PlantArea::where('site_id', $site->id)->first();
        $config = PlantConfiguration::where('site_id', $site->id)->first();

        $bayLines = [
            ['code' => 'BAY-1', 'name' => 'Bay Line 1', 'status_code' => 'free'],
            ['code' => 'BAY-2', 'name' => 'Bay Line 2', 'status_code' => 'free'],
            ['code' => 'BAY-3', 'name' => 'Bay Line 3', 'status_code' => 'free'],
            ['code' => 'BAY-4', 'name' => 'Bay Line 4', 'status_code' => 'free'],
        ];

        foreach ($bayLines as $line) {
            BayLine::firstOrCreate(
                ['code' => $line['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $line['name'],
                    'site_id' => $site->id,
                    'plant_area_id' => $loadingArea?->id,
                    'plant_configuration_id' => $config?->id,
                    'status_code' => $line['status_code'],
                    'is_active' => true,
                ]
            );
        }
    }
}
