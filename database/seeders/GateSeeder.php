<?php

namespace Database\Seeders;

use App\Enums\GateType;
use App\Models\Gate;
use App\Models\PlantArea;
use App\Models\PlantConfiguration;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GateSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('code', 'SITE-1')->first() ?? Site::first();
        if (! $site) {
            return;
        }

        $gateArea = PlantArea::where('site_id', $site->id)->where('code', 'AREA-GATE')->first()
            ?? PlantArea::where('site_id', $site->id)->first();
        $config = PlantConfiguration::where('site_id', $site->id)->first();

        $gates = [
            ['code' => 'GATE-IN-1', 'name' => 'Entry Gate 1', 'gate_type' => GateType::ENTRY],
            ['code' => 'GATE-OUT-1', 'name' => 'Exit Gate 1', 'gate_type' => GateType::EXIT],
            ['code' => 'GATE-SVC-1', 'name' => 'Service Gate', 'gate_type' => GateType::SERVICE],
        ];

        foreach ($gates as $g) {
            Gate::firstOrCreate(
                ['site_id' => $site->id, 'code' => $g['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $g['name'],
                    'gate_type' => $g['gate_type'],
                    'plant_area_id' => $gateArea?->id,
                    'plant_configuration_id' => $config?->id,
                    'status' => 'draft',
                    'is_active' => true,
                ]
            );
        }
    }
}
