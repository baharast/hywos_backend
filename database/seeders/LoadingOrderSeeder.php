<?php

namespace Database\Seeders;

use App\Enums\DriverTask;
use App\Models\BayLine;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\FreightForwarder;
use App\Models\LoadingOrder;
use App\Models\Trailer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LoadingOrderSeeder extends Seeder
{
    public function run(): void
    {
        $customer1 = Customer::query()->where('code', 'CUST-1')->first()
            ?? Customer::query()->first();
        $customer2 = Customer::query()->where('code', 'CUST-2')->first()
            ?? $customer1;

        $carrier = FreightForwarder::query()->first();

        $driver = Driver::query()->first();
        $trailer = Trailer::query()->first();

        if (! $customer1) {
            return; // can't seed orders without at least one customer
        }

        // Bayline pool, fetched in code order so each demo order lands on
        // a different bay. Missing entries are tolerated (the seeder ran
        // before BayLineSeeder, or the plant has fewer than 4 bays) — the
        // assignment columns then stay null and the FE renders no bayLine.
        $bays = BayLine::query()->orderBy('code')->get()->keyBy('code');
        $bay = fn (string $code): ?BayLine => $bays->get($code);

        $now = now();

        // 1) DRAFT — incomplete data (missing target_quantity)
        LoadingOrder::firstOrCreate(
            ['order_no' => 'LO-2026-0001'],
            [
                'id' => (string) Str::uuid(),
                'source' => 'manual',
                'customer_id' => $customer1->id,
                'customer_name' => $customer1->name,
                'carrier_id' => $carrier?->id,
                'carrier_name' => $carrier?->carrier_name,
                'product_quality' => 'Hydrogen 5.0',
                'target_quantity' => null, // forces DRAFT
                'unit' => 'kg',
                'task_flow' => DriverTask::TRAILER_FILLING,
                'requires_certificate' => true,
                'requires_delivery_note' => true,
                'requires_qm_document' => false,
                'is_sap_owned' => false,
                'notes' => 'Draft order; awaiting target quantity from operator.',
            ]
        );

        // 2) NEEDS_ASSIGNMENT — full data but no driver/trailer
        $b2 = $bay('BAY-1');
        LoadingOrder::firstOrCreate(
            ['order_no' => 'LO-2026-0002'],
            [
                'id' => (string) Str::uuid(),
                'source' => 'sap',
                'sap_reference' => 'SAP-450012982',
                'customer_id' => $customer1->id,
                'customer_name' => $customer1->name,
                'carrier_id' => $carrier?->id,
                'carrier_name' => $carrier?->carrier_name,
                'product_quality' => 'Hydrogen 5.0',
                'target_quantity' => 300.000,
                'unit' => 'kg',
                'planned_window_start' => $now->copy()->addHours(2),
                'planned_window_end' => $now->copy()->addHours(6),
                'task_flow' => DriverTask::TRAILER_FILLING,
                'requires_certificate' => true,
                'requires_delivery_note' => true,
                'requires_qm_document' => true,
                'is_sap_owned' => true,
                'assigned_bay_line_id' => $b2?->id,
                'assigned_bay_line_code' => $b2?->code,
                'assigned_bay_line_name' => $b2?->name,
            ]
        );

        // 3) READY — full data + driver + trailer
        $b3 = $bay('BAY-2');
        LoadingOrder::firstOrCreate(
            ['order_no' => 'LO-2026-0003'],
            [
                'id' => (string) Str::uuid(),
                'source' => 'sap',
                'sap_reference' => 'SAP-450012999',
                'customer_id' => $customer2?->id ?? $customer1->id,
                'customer_name' => $customer2?->name ?? $customer1->name,
                'carrier_id' => $carrier?->id,
                'carrier_name' => $carrier?->carrier_name,
                'product_quality' => 'Hydrogen 5.0',
                'target_quantity' => 500.000,
                'unit' => 'kg',
                'planned_window_start' => $now->copy()->addHours(1),
                'planned_window_end' => $now->copy()->addHours(4),
                'assigned_driver_id' => $driver?->id,
                'assigned_driver_name' => $driver
                    ? trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? ''))
                    : null,
                'assigned_driver_code' => $driver?->driver_code,
                'assigned_trailer_id' => $trailer?->id,
                'assigned_trailer_label' => $trailer?->trailer_label ?? $trailer?->trailer_code,
                'assigned_trailer_plate' => $trailer?->plate,
                'task_flow' => DriverTask::TRAILER_FILLING,
                'requires_certificate' => true,
                'requires_delivery_note' => true,
                'requires_qm_document' => false,
                'is_sap_owned' => true,
                'assigned_bay_line_id' => $b3?->id,
                'assigned_bay_line_code' => $b3?->code,
                'assigned_bay_line_name' => $b3?->name,
            ]
        );

        // 4) IN_PROGRESS — has active plant visit reference (soft FK; plant_visits table arrives in TSK-002)
        $b4 = $bay('BAY-3');
        LoadingOrder::firstOrCreate(
            ['order_no' => 'LO-2026-0004'],
            [
                'id' => (string) Str::uuid(),
                'source' => 'sap',
                'sap_reference' => 'SAP-450013010',
                'customer_id' => $customer1->id,
                'customer_name' => $customer1->name,
                'carrier_id' => $carrier?->id,
                'carrier_name' => $carrier?->carrier_name,
                'product_quality' => 'Hydrogen 5.0',
                'target_quantity' => 240.000,
                'unit' => 'kg',
                'assigned_driver_id' => $driver?->id,
                'assigned_driver_name' => $driver
                    ? trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? ''))
                    : null,
                'assigned_driver_code' => $driver?->driver_code,
                'assigned_trailer_id' => $trailer?->id,
                'assigned_trailer_label' => $trailer?->trailer_label ?? $trailer?->trailer_code,
                'assigned_trailer_plate' => $trailer?->plate,
                'task_flow' => DriverTask::TRAILER_FILLING,
                'requires_certificate' => true,
                'requires_delivery_note' => true,
                'requires_qm_document' => false,
                'is_sap_owned' => true,
                'active_plant_visit_id' => (string) Str::uuid(), // placeholder soft FK
                'active_plant_visit_no' => 'PV-2026-0019',
                'current_step' => 'loading',
                'is_locked_by_execution' => true,
                'assigned_bay_line_id' => $b4?->id,
                'assigned_bay_line_code' => $b4?->code,
                'assigned_bay_line_name' => $b4?->name,
            ]
        );

        // 5) BLOCKED — explicit blocker with reason + timestamp
        $b5 = $bay('BAY-4');
        LoadingOrder::firstOrCreate(
            ['order_no' => 'LO-2026-0005'],
            [
                'id' => (string) Str::uuid(),
                'source' => 'manual',
                'customer_id' => $customer1->id,
                'customer_name' => $customer1->name,
                'carrier_id' => $carrier?->id,
                'carrier_name' => $carrier?->carrier_name,
                'product_quality' => 'Hydrogen 5.0',
                'target_quantity' => 100.000,
                'unit' => 'kg',
                'assigned_driver_id' => $driver?->id,
                'assigned_driver_name' => $driver
                    ? trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? ''))
                    : null,
                'assigned_driver_code' => $driver?->driver_code,
                'task_flow' => DriverTask::TRAILER_FILLING,
                'requires_certificate' => true,
                'requires_delivery_note' => true,
                'requires_qm_document' => false,
                'is_sap_owned' => false,
                'blocked_at' => $now->copy()->subMinutes(45),
                'blocking_reason' => 'Customer credit review pending',
                'blocking_reason_code' => 'CREDIT_HOLD',
                'assigned_bay_line_id' => $b5?->id,
                'assigned_bay_line_code' => $b5?->code,
                'assigned_bay_line_name' => $b5?->name,
            ]
        );
    }
}
