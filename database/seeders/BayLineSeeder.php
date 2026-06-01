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

        // V3.2 station-card extension demo values (2026-06-01):
        //   `pressure_class`     → parsed into `capability_bar` (bar) by
        //                          StationViewItemResource.
        //   `related_device_id`  → exposed as `plc_id`. Stable per-bay UUID
        //                          literal so re-seeds produce identical ids
        //                          and the FE can cache by id.
        //   `allowed_product`    → not yet used by the FE, but helps the bay
        //                          look real in /baylines listings.
        $bayLines = [
            ['code' => 'BAY-1', 'name' => 'Bay Line 1', 'status_code' => 'free',
                'pressure_class' => '150 bar', 'allowed_product' => 'Hydrogen 5.0',
                'related_device_id' => '11111111-1111-4111-8111-d0d0d0d0d001'],
            ['code' => 'BAY-2', 'name' => 'Bay Line 2', 'status_code' => 'free',
                'pressure_class' => '200 bar', 'allowed_product' => 'Hydrogen 5.0',
                'related_device_id' => '22222222-2222-4222-8222-d0d0d0d0d002'],
            ['code' => 'BAY-3', 'name' => 'Bay Line 3', 'status_code' => 'free',
                'pressure_class' => '250 bar', 'allowed_product' => 'Hydrogen 5.0',
                'related_device_id' => '33333333-3333-4333-8333-d0d0d0d0d003'],
            ['code' => 'BAY-4', 'name' => 'Bay Line 4', 'status_code' => 'free',
                'pressure_class' => '350 bar', 'allowed_product' => 'Hydrogen 5.0',
                'related_device_id' => '44444444-4444-4444-8444-d0d0d0d0d004'],
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
                    'pressure_class' => $line['pressure_class'],
                    'allowed_product' => $line['allowed_product'],
                    'related_device_id' => $line['related_device_id'],
                    'is_active' => true,
                ]
            );
        }
    }
}
