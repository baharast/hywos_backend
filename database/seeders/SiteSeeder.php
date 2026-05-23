<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Site;
use Illuminate\Support\Str;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        $sites = [
            [
                'code' => 'SITE-1',
                'name' => 'Schweinfurt H2 Site',
                'plant_type' => 'h2_plant',
                'default_language' => 'de',
                'time_zone' => 'Europe/Berlin',
                'address_line' => 'Industriestrasse 1, Schweinfurt, Germany',
            ],
            [
                'code' => 'SITE-2',
                'name' => 'Secondary Site',
                'plant_type' => 'h2_plant',
                'default_language' => 'de',
                'time_zone' => 'Europe/Berlin',
                'address_line' => null,
            ],
            [
                'code' => 'SITE-3',
                'name' => 'East Site',
                'plant_type' => 'h2_plant',
                'default_language' => 'de',
                'time_zone' => 'Europe/Berlin',
                'address_line' => null,
            ],
            [
                'code' => 'SITE-4',
                'name' => 'West Site',
                'plant_type' => 'h2_plant',
                'default_language' => 'de',
                'time_zone' => 'Europe/Berlin',
                'address_line' => null,
            ],
        ];

        foreach ($sites as $siteData) {
            Site::firstOrCreate(
                ['code' => $siteData['code']],
                array_merge(
                    ['id' => (string) Str::uuid(), 'is_active' => true],
                    $siteData
                )
            );
        }
    }
}
