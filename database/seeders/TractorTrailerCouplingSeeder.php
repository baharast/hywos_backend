<?php

namespace Database\Seeders;

use App\Enums\CouplingSource;
use App\Models\Driver;
use App\Models\TractorTrailerCoupling;
use App\Models\TractorVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TractorTrailerCouplingSeeder extends Seeder
{
    public function run(): void
    {
        $tr200 = TractorVehicle::query()->where('license_plate', 'SW-TR-200')->first();
        $tr201 = TractorVehicle::query()->where('license_plate', 'SW-TR-201')->first();
        if (! $tr200 || ! $tr201) {
            return;
        }

        $driverMax = Driver::query()->where('driver_code', 'DRV-1001')->first();
        $driverAnna = Driver::query()->where('driver_code', 'DRV-1002')->first();

        // Resolve a trailer label/id without depending on the Trailers module
        // existing yet. If a trailer with code TR-100 / TR-101 exists, link to it;
        // otherwise leave trailer_id null and use a denormalized label.
        $trailer100Id = $this->trailerIdByCode('TR-100');
        $trailer101Id = $this->trailerIdByCode('TR-101');

        // 1) Active coupling — SW-TR-200 ↔ TR-100, driver Max, source=terminal
        $activeCoupling = TractorTrailerCoupling::firstOrCreate(
            [
                'tractor_vehicle_id' => $tr200->id,
                'trailer_label' => 'TR-100',
                'uncoupled_at' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'trailer_id' => $trailer100Id,
                'driver_id' => $driverMax?->id,
                'driver_name' => $driverMax ? trim(($driverMax->first_name ?? '') . ' ' . ($driverMax->last_name ?? '')) : null,
                'plant_visit_id' => null,
                'visit_no' => 'V-2026-0001',
                'order_id' => null,
                'order_no' => 'ORD-1001',
                'source' => CouplingSource::TERMINAL,
                'coupled_at' => now()->subHours(2),
                'is_active' => true,
                'correlation_id' => (string) Str::uuid(),
            ]
        );

        // Reflect active coupling on the parent tractor's denormalized fields
        $tr200->update([
            'current_trailer_id' => $activeCoupling->trailer_id,
            'current_trailer_label' => $activeCoupling->trailer_label,
            'last_visit_at' => $activeCoupling->coupled_at,
            'last_driver_name' => $activeCoupling->driver_name,
        ]);

        // 2) Historical coupling — SW-TR-200 with TR-101 (uncoupled yesterday)
        TractorTrailerCoupling::firstOrCreate(
            [
                'tractor_vehicle_id' => $tr200->id,
                'trailer_label' => 'TR-101',
                'coupled_at' => now()->subDays(2),
            ],
            [
                'id' => (string) Str::uuid(),
                'trailer_id' => $trailer101Id,
                'driver_id' => $driverMax?->id,
                'driver_name' => $driverMax ? trim(($driverMax->first_name ?? '') . ' ' . ($driverMax->last_name ?? '')) : null,
                'plant_visit_id' => null,
                'visit_no' => 'V-2026-0000',
                'source' => CouplingSource::TERMINAL,
                'coupled_at' => now()->subDays(2),
                'uncoupled_at' => now()->subDay(),
                'is_active' => false,
                'correlation_id' => (string) Str::uuid(),
            ]
        );

        // 3) Historical coupling — SW-TR-201 with TR-100, by Anna
        TractorTrailerCoupling::firstOrCreate(
            [
                'tractor_vehicle_id' => $tr201->id,
                'trailer_label' => 'TR-100',
                'coupled_at' => now()->subDays(5),
            ],
            [
                'id' => (string) Str::uuid(),
                'trailer_id' => $trailer100Id,
                'driver_id' => $driverAnna?->id,
                'driver_name' => $driverAnna ? trim(($driverAnna->first_name ?? '') . ' ' . ($driverAnna->last_name ?? '')) : null,
                'source' => CouplingSource::GATE,
                'coupled_at' => now()->subDays(5),
                'uncoupled_at' => now()->subDays(4),
                'is_active' => false,
                'correlation_id' => (string) Str::uuid(),
            ]
        );
    }

    /**
     * Resolve a trailer id by code without requiring the Trailer model to exist.
     */
    protected function trailerIdByCode(string $code): ?string
    {
        if (! class_exists(\App\Models\Trailer::class)) {
            return null;
        }

        try {
            $row = \App\Models\Trailer::query()->where('trailer_code', $code)->first();
            return $row?->id;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
