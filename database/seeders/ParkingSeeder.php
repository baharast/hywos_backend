<?php

namespace Database\Seeders;

use App\Enums\ParkingLoadState;
use App\Enums\ParkingNextAction;
use App\Enums\ParkingSlotStatus;
use App\Models\LoadingOrder;
use App\Models\Parking;
use App\Models\PlantArea;
use App\Models\PlantConfiguration;
use App\Models\Site;
use App\Models\Trailer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Trailer Parking — MVP two-slot board (V2.1 §0.2).
 *
 * Seeds EXACTLY two slots:
 *   - PARKING-1: OCCUPIED with the first seeded trailer, LOADED, linked to
 *                LO-2026-0003 (READY demo), next action = OPEN_VISIT.
 *   - PARKING-2: FREE, no trailer / order / visit.
 *
 * Must run AFTER TrailerSeeder and LoadingOrderSeeder so the snapshot
 * references resolve.
 */
class ParkingSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('code', 'SITE-1')->first() ?? Site::first();
        $area = $site
            ? (PlantArea::where('site_id', $site->id)->where('code', 'AREA-PARK')->first()
                ?? PlantArea::where('site_id', $site->id)->first())
            : PlantArea::first();
        $config = $site
            ? PlantConfiguration::where('site_id', $site->id)->first()
            : PlantConfiguration::first();

        $trailer = Trailer::orderBy('trailer_code')->first();
        $readyOrder = LoadingOrder::where('order_no', 'LO-2026-0003')->first();

        // Slot 1 — OCCUPIED, LOADED, linked to the READY demo order
        Parking::firstOrCreate(
            ['code' => 'PARKING-1'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Parking 1',
                'plant_configuration_id' => $config?->id,
                'site_id' => $site?->id,
                'plant_area_id' => $area?->id,

                'slot_status' => ParkingSlotStatus::OCCUPIED,

                'current_trailer_id' => $trailer?->id,
                'current_trailer_label' => $trailer?->trailer_label ?? $trailer?->trailer_code,
                'current_trailer_plate' => $trailer?->plate,
                'current_trailer_chip' => null,
                'current_load_state' => ParkingLoadState::LOADED,

                'linked_order_id' => $readyOrder?->id,
                'linked_order_no' => $readyOrder?->order_no,
                'driver_id' => $readyOrder?->assigned_driver_id,
                'driver_name' => $readyOrder?->assigned_driver_name,
                'tractor_plate' => null,

                'parked_since' => now()->subHours(3),

                'next_action' => ParkingNextAction::OPEN_VISIT,
                // document_summary only set for LOADED trailers (V2.1 §6.4)
                'document_summary' => [
                    'certificateStatus' => 'available',
                    'deliveryNoteStatus' => 'pending',
                    'qmDocumentStatus' => 'not_required',
                ],

                'is_active' => true,
            ]
        );

        // Slot 2 — FREE
        Parking::firstOrCreate(
            ['code' => 'PARKING-2'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Parking 2',
                'plant_configuration_id' => $config?->id,
                'site_id' => $site?->id,
                'plant_area_id' => $area?->id,

                'slot_status' => ParkingSlotStatus::FREE,

                'current_trailer_id' => null,
                'current_trailer_label' => null,
                'current_trailer_plate' => null,
                'current_trailer_chip' => null,
                'current_load_state' => null,

                'linked_order_id' => null,
                'linked_order_no' => null,
                'driver_id' => null,
                'driver_name' => null,
                'tractor_plate' => null,

                'parked_since' => null,

                'next_action' => ParkingNextAction::NONE,
                'document_summary' => null,

                'is_active' => true,
            ]
        );
    }
}
