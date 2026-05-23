<?php

namespace Database\Seeders;

use App\Enums\TerminalType;
use App\Models\PlantArea;
use App\Models\PlantConfiguration;
use App\Models\Site;
use App\Models\TerminalPanel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TerminalPanelSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('code', 'SITE-1')->first() ?? Site::first();
        if (! $site) {
            return;
        }

        $gateArea = PlantArea::where('site_id', $site->id)->where('code', 'AREA-GATE')->first();
        $loadingArea = PlantArea::where('site_id', $site->id)->where('code', 'AREA-LOAD')->first();
        $controlArea = PlantArea::where('site_id', $site->id)->where('code', 'AREA-CTRL')->first();
        $config = PlantConfiguration::where('site_id', $site->id)->first();

        $terminals = [
            [
                'code' => 'TERM-GATE-1',
                'name' => 'Gate Terminal 1',
                'terminal_type' => TerminalType::GATE_TERMINAL,
                'area' => $gateArea,
                'languages' => ['de', 'en'],
            ],
            [
                'code' => 'TERM-DRV-1',
                'name' => 'Driver Terminal 1',
                'terminal_type' => TerminalType::DRIVER_TERMINAL,
                'area' => $loadingArea,
                'languages' => ['de', 'en'],
            ],
            [
                'code' => 'PANEL-FILL-1',
                'name' => 'Filling Station Panel 1',
                'terminal_type' => TerminalType::FILLING_STATION_PANEL,
                'area' => $loadingArea,
                'languages' => ['de', 'en'],
            ],
            [
                'code' => 'PANEL-OP-1',
                'name' => 'Operator Panel',
                'terminal_type' => TerminalType::OPERATOR_PANEL,
                'area' => $controlArea,
                'languages' => ['de', 'en'],
            ],
        ];

        foreach ($terminals as $t) {
            TerminalPanel::firstOrCreate(
                ['site_id' => $site->id, 'code' => $t['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $t['name'],
                    'terminal_type' => $t['terminal_type'],
                    'plant_area_id' => $t['area']?->id,
                    'plant_configuration_id' => $config?->id,
                    'language_support' => $t['languages'],
                    'status' => 'draft',
                    'is_active' => true,
                ]
            );
        }
    }
}
