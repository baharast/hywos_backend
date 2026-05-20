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

        BayLine::firstOrCreate([
            'code' => 'BAY-1'
        ], [
            'id' => (string) Str::uuid(),
            'name' => 'Bay Line 1',
            'site_id' => $site->id,
            'plant_area_id' => $area->id,
            'status_code' => 'idle',
            'is_active' => true,
        ]);
    }
}
