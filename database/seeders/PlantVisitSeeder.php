<?php

namespace Database\Seeders;

use App\Enums\DriverTask;
use App\Enums\PlantVisitLocation;
use App\Enums\PlantVisitStatus;
use App\Enums\PlantVisitStep;
use App\Models\Driver;
use App\Models\LoadingOrder;
use App\Models\PlantVisit;
use App\Models\Trailer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds 5 mixed-state plant visits per TSK-002 §3.6.
 *
 * After creating PV-2026-0019, this seeder back-fills the placeholder
 * `loading_orders.active_plant_visit_id` on LO-2026-0004 (set during
 * LoadingOrderSeeder) with the real visit UUID. The LoadingOrder model's
 * saving() hook then refreshes the persisted `status` to IN_PROGRESS via
 * LoadingOrderReadinessService.
 */
class PlantVisitSeeder extends Seeder
{
    public function run(): void
    {
        $driver = Driver::query()->first();
        $trailer = Trailer::query()->first();
        $order = LoadingOrder::query()->where('order_no', 'LO-2026-0004')->first();

        $now = now();

        $driverName = $driver
            ? trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? ''))
            : null;
        $driverCode = $driver?->driver_code;
        $trailerLabel = $trailer?->trailer_label ?? $trailer?->trailer_code;
        $trailerPlate = $trailer?->plate;

        // 1) PV-2026-0019 — TRAILER_FILLING / LOADING / NORMAL at BAY_LINE_1
        $visit1 = PlantVisit::firstOrCreate(
            ['visit_no' => 'PV-2026-0019'],
            [
                'id' => (string) Str::uuid(),
                'driver_id' => $driver?->id,
                'driver_name' => $driverName,
                'driver_code' => $driverCode,
                'tractor_plate' => 'SW-TR-200',
                'trailer_id' => $trailer?->id,
                'trailer_label' => $trailerLabel,
                'trailer_plate' => $trailerPlate,
                'order_id' => $order?->id,
                'order_no' => $order?->order_no,
                'sap_reference' => $order?->sap_reference,
                'task_flow' => DriverTask::TRAILER_FILLING,
                'current_step' => PlantVisitStep::LOADING,
                'visit_status' => PlantVisitStatus::NORMAL,
                'current_location' => PlantVisitLocation::BAY_LINE_1,
                'next_action_label' => 'Monitor loading progress',
                'next_action_target' => '/operations/loading-control',
                'owner_role' => 'Operator',
                'entry_time' => $now->copy()->subHours(2),
                'driver_snapshot' => $driver ? [
                    'driver_id' => $driver->id,
                    'driver_code' => $driverCode,
                    'full_name' => $driverName,
                ] : null,
                'trailer_snapshot' => $trailer ? [
                    'trailer_id' => $trailer->id,
                    'trailer_label' => $trailerLabel,
                    'plate' => $trailerPlate,
                ] : null,
                'order_snapshot' => $order ? [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'sap_reference' => $order->sap_reference,
                    'product_quality' => $order->product_quality,
                    'target_quantity' => $order->target_quantity,
                    'unit' => $order->unit,
                ] : null,
            ]
        );

        // Back-fill LO-2026-0004 with the real visit UUID (replaces the
        // placeholder UUID set by LoadingOrderSeeder). The LoadingOrder
        // saving() hook will re-derive status as IN_PROGRESS.
        if ($order) {
            $order->update([
                'active_plant_visit_id' => $visit1->id,
                'active_plant_visit_no' => $visit1->visit_no,
            ]);
        }

        // 2) PV-2026-0020 — PICKUP_LOADED_TRAILER / PICKUP / WAITING at PARKING_1
        PlantVisit::firstOrCreate(
            ['visit_no' => 'PV-2026-0020'],
            [
                'id' => (string) Str::uuid(),
                'driver_id' => $driver?->id,
                'driver_name' => $driverName,
                'driver_code' => $driverCode,
                'tractor_plate' => 'SW-TR-201',
                'trailer_id' => $trailer?->id,
                'trailer_label' => $trailerLabel,
                'trailer_plate' => $trailerPlate,
                'task_flow' => DriverTask::PICKUP_LOADED_TRAILER,
                'current_step' => PlantVisitStep::PICKUP,
                'visit_status' => PlantVisitStatus::WAITING,
                'current_location' => PlantVisitLocation::PARKING_1,
                'waiting_reason' => 'Waiting for documents readiness',
                'next_action_label' => 'Open Documents & Reports',
                'next_action_target' => '/documents-reports/certificates',
                'owner_role' => 'Operator',
                'entry_time' => $now->copy()->subMinutes(40),
            ]
        );

        // 3) PV-2026-0021 — PARK_TRAILER / PARKING / NORMAL at PARKING_2
        PlantVisit::firstOrCreate(
            ['visit_no' => 'PV-2026-0021'],
            [
                'id' => (string) Str::uuid(),
                'driver_id' => $driver?->id,
                'driver_name' => $driverName,
                'driver_code' => $driverCode,
                'tractor_plate' => 'SW-TR-202',
                'trailer_id' => $trailer?->id,
                'trailer_label' => $trailerLabel,
                'trailer_plate' => $trailerPlate,
                'task_flow' => DriverTask::PARK_TRAILER,
                'current_step' => PlantVisitStep::PARKING,
                'visit_status' => PlantVisitStatus::NORMAL,
                'current_location' => PlantVisitLocation::PARKING_2,
                'next_action_label' => 'Confirm parked',
                'next_action_target' => '/operations/trailer-pool-car-park',
                'owner_role' => 'Operator',
                'entry_time' => $now->copy()->subMinutes(15),
            ]
        );

        // 4) PV-2026-0022 — TRAILER_FILLING / TRAILER_IDENTIFICATION / CLARIFICATION
        PlantVisit::firstOrCreate(
            ['visit_no' => 'PV-2026-0022'],
            [
                'id' => (string) Str::uuid(),
                'driver_id' => $driver?->id,
                'driver_name' => $driverName,
                'driver_code' => $driverCode,
                'tractor_plate' => 'SW-TR-203',
                'trailer_chip' => '•••• 4242',
                'task_flow' => DriverTask::TRAILER_FILLING,
                'current_step' => PlantVisitStep::TRAILER_IDENTIFICATION,
                'visit_status' => PlantVisitStatus::CLARIFICATION,
                'current_location' => PlantVisitLocation::CONTROL_ROOM_DRIVER_TERMINAL,
                'blocker_reason' => 'Unknown trailer chip',
                'next_action_label' => 'Open clarification case',
                'next_action_target' => '/operations/clarification-cases',
                'owner_role' => 'Dispatcher',
                'entry_time' => $now->copy()->subMinutes(8),
            ]
        );

        // 5) PV-2026-0023 — DOCUMENTS_EXIT / EXIT_VALIDATION / READY_FOR_EXIT at EXIT_GATE
        PlantVisit::firstOrCreate(
            ['visit_no' => 'PV-2026-0023'],
            [
                'id' => (string) Str::uuid(),
                'driver_id' => $driver?->id,
                'driver_name' => $driverName,
                'driver_code' => $driverCode,
                'tractor_plate' => 'SW-TR-204',
                'task_flow' => DriverTask::DOCUMENTS_EXIT,
                'current_step' => PlantVisitStep::EXIT_VALIDATION,
                'visit_status' => PlantVisitStatus::READY_FOR_EXIT,
                'current_location' => PlantVisitLocation::EXIT_GATE,
                'next_action_label' => 'Proceed to exit',
                'next_action_target' => '/operations/gate-terminal-monitor',
                'owner_role' => 'Operator',
                'entry_time' => $now->copy()->subHours(1),
            ]
        );
    }
}
