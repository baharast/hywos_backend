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
            ['code' => 'SITE-1', 'name' => 'Default Site'],
            ['code' => 'SITE-2', 'name' => 'Secondary Site'],
            ['code' => 'SITE-3', 'name' => 'East Site'],
            ['code' => 'SITE-4', 'name' => 'West Site'],
        ];

        foreach ($sites as $siteData) {
            Site::firstOrCreate([
                'code' => $siteData['code'],
            ], [
                'id' => (string) Str::uuid(),
                'name' => $siteData['name'],
            ]);
        }
    }
}
