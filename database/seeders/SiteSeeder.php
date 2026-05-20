<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Site;
use Illuminate\Support\Str;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        Site::firstOrCreate([
            'code' => 'SITE-1'
        ], [
            'id' => (string) Str::uuid(),
            'name' => 'Default Site'
        ]);
    }
}
