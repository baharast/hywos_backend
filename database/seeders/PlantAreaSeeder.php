<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlantArea;
use App\Models\Site;
use Illuminate\Support\Str;

class PlantAreaSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::first();
        if (! $site) {
            return;
        }

        PlantArea::firstOrCreate([
            'site_id' => $site->id,
            'code' => 'AREA-1'
        ], [
            'id' => (string) Str::uuid(),
            'name' => 'Default Plant Area 1'
        ]);

        PlantArea::firstOrCreate([
            'site_id' => $site->id,
            'code' => 'AREA-2'
        ], [
            'id' => (string) Str::uuid(),
            'name' => 'Default Plant Area 2'
        ]);
    }
}
