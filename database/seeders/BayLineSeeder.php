<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BayLine;
use App\Models\Site;
use App\Models\PlantArea;
use Illuminate\Support\Str;

class BayLineSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::first();
        $area = PlantArea::first();
        if (! $site || ! $area) {
            return;
        }

        $bayLines = [
            ['code' => 'BAY-1', 'name' => 'Bay Line 1', 'status_code' => 'idle'],
            ['code' => 'BAY-2', 'name' => 'Bay Line 2', 'status_code' => 'idle'],
            ['code' => 'BAY-3', 'name' => 'Bay Line 3', 'status_code' => 'idle'],
            ['code' => 'BAY-4', 'name' => 'Bay Line 4', 'status_code' => 'idle'],
        ];

        foreach ($bayLines as $line) {
            BayLine::firstOrCreate([
                'code' => $line['code'],
            ], [
                'id' => (string) Str::uuid(),
                'name' => $line['name'],
                'site_id' => $site->id,
                'plant_area_id' => $area->id,
                'status_code' => $line['status_code'],
                'is_active' => true,
            ]);
        }
    }
}
