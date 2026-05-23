<?php

namespace Database\Seeders;

use App\Enums\PlantConfigurationStatus;
use App\Models\Company;
use App\Models\PlantConfiguration;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlantConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('code', 'SITE-1')->first() ?? Site::first();
        if (! $site) {
            return;
        }

        $company = Company::first();

        PlantConfiguration::firstOrCreate(
            ['site_id' => $site->id],
            [
                'id' => (string) Str::uuid(),
                'company_id' => $company?->id,
                'status' => PlantConfigurationStatus::DRAFT,
                'version' => 1,
                'company_name' => $company?->name ?? 'HYWOS GmbH',
                'company_code' => $company?->code ?? 'HYWOS',
                'site_name' => $site->name,
                'site_code' => $site->code,
                'plant_type' => $site->plant_type ?? 'h2_plant',
                'default_language' => $site->default_language ?? 'de',
                'time_zone' => $site->time_zone ?? 'Europe/Berlin',
            ]
        );
    }
}
