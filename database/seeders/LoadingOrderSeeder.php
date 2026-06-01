<?php

namespace Database\Seeders;

use App\Enums\DriverTask;
use App\Models\BayLine;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\FreightForwarder;
use App\Models\LoadingOrder;
use App\Models\Trailer;
use App\Services\LoadingOrders\LoadingOrderReadinessService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        if (! $customer1) {
            return; // can't seed orders without at least one customer
        }

        // Drivers resolved BY CODE (deterministic). The previous
        // Driver::query()->first() returned a NON-deterministic row — the PK
        // is a random UUID, so the demo orders could land on any driver and
        // Anna / Tomasz would often end up with nothing.
        //
        // The three active drivers that carry an active chip AND an active
        // TAN (Max, Anna, Tomasz) must each own at least one active
        // (READY / IN_PROGRESS) order so the driver-terminal queue is never
        // empty for them after a fresh seed:
        //   - Max (DRV-1001)    : active, chip + TAN, training VALID
        //   - Anna (DRV-1002)   : active, chip + TAN, training VALID
        //   - Tomasz (DRV-1003) : active, chip + TAN, training EXPIRED
        //                         (order is queued, but he is forced into
        //                          Safety Training at login before acting).
        $max    = Driver::where('driver_code', 'DRV-1001')->first();
        $anna   = Driver::where('driver_code', 'DRV-1002')->first();
        $tomasz = Driver::where('driver_code', 'DRV-1003')->first();

        // Active trailers only (TR-1003 is BLOCKED, TR-1005 ARCHIVED).
        // trailer_filling orders need a trailer assigned to reach READY.
        $tr1 = Trailer::where('trailer_code', 'TR-1001')->first();
        $tr2 = Trailer::where('trailer_code', 'TR-1002')->first();
        $tr4 = Trailer::where('trailer_code', 'TR-1004')->first();

        // Bayline pool, fetched in code order so each demo order lands on
        // a different bay. Missing entries are tolerated (the seeder ran
        // before BayLineSeeder, or the plant has fewer than 4 bays) — the
        // assignment columns then stay null and the FE renders no bayLine.
        $bays = BayLine::query()->orderBy('code')->get()->keyBy('code');
        $bay = fn (string $code): ?BayLine => $bays->get($code);

        // Build the denormalised driver + trailer assignment columns from a
        // driver / trailer pair so every order block stays consistent.
        $assign = function (?Driver $d, ?Trailer $t): array {
            return [
                'assigned_driver_id' => $d?->id,
                'assigned_driver_name' => $d
                    ? trim(($d->first_name ?? '') . ' ' . ($d->last_name ?? ''))
                    : null,
                'assigned_driver_code' => $d?->driver_code,
                'assigned_trailer_id' => $t?->id,
                'assigned_trailer_label' => $t?->trailer_label ?? $t?->trailer_code,
                'assigned_trailer_plate' => $t?->plate,
            ];
        };

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

        // 3) READY — Max (DRV-1001): active, chip + TAN, training valid.
        $b3 = $bay('BAY-2');
        LoadingOrder::firstOrCreate(
            ['order_no' => 'LO-2026-0003'],
            array_merge([
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
                'task_flow' => DriverTask::TRAILER_FILLING,
                'requires_certificate' => true,
                'requires_delivery_note' => true,
                'requires_qm_document' => false,
                'is_sap_owned' => true,
                'assigned_bay_line_id' => $b3?->id,
                'assigned_bay_line_code' => $b3?->code,
                'assigned_bay_line_name' => $b3?->name,
            ], $assign($max, $tr1))
        );

        // 4) IN_PROGRESS — Max (DRV-1001): bound to an active plant visit (soft FK).
        $b4 = $bay('BAY-3');
        LoadingOrder::firstOrCreate(
            ['order_no' => 'LO-2026-0004'],
            array_merge([
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
            ], $assign($max, $tr1))
        );

        // 5) BLOCKED — Max (DRV-1001): explicit blocker with reason + timestamp.
        //    Not an "active" order — excluded from the driver-terminal queue.
        $b5 = $bay('BAY-4');
        LoadingOrder::firstOrCreate(
            ['order_no' => 'LO-2026-0005'],
            array_merge([
                'id' => (string) Str::uuid(),
                'source' => 'manual',
                'customer_id' => $customer1->id,
                'customer_name' => $customer1->name,
                'carrier_id' => $carrier?->id,
                'carrier_name' => $carrier?->carrier_name,
                'product_quality' => 'Hydrogen 5.0',
                'target_quantity' => 100.000,
                'unit' => 'kg',
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
            ], $assign($max, null))
        );

        // 6) READY — Anna (DRV-1002): active, chip + TAN, training valid.
        //    Guarantees the second chip+TAN driver has an active order.
        $b6 = $bay('BAY-1');
        LoadingOrder::firstOrCreate(
            ['order_no' => 'LO-2026-0006'],
            array_merge([
                'id' => (string) Str::uuid(),
                'source' => 'sap',
                'sap_reference' => 'SAP-450013055',
                'customer_id' => $customer1->id,
                'customer_name' => $customer1->name,
                'carrier_id' => $carrier?->id,
                'carrier_name' => $carrier?->carrier_name,
                'product_quality' => 'Hydrogen 5.0',
                'target_quantity' => 420.000,
                'unit' => 'kg',
                'planned_window_start' => $now->copy()->addHours(3),
                'planned_window_end' => $now->copy()->addHours(7),
                'task_flow' => DriverTask::TRAILER_FILLING,
                'requires_certificate' => true,
                'requires_delivery_note' => true,
                'requires_qm_document' => false,
                'is_sap_owned' => true,
                'assigned_bay_line_id' => $b6?->id,
                'assigned_bay_line_code' => $b6?->code,
                'assigned_bay_line_name' => $b6?->name,
            ], $assign($anna, $tr2))
        );

        // 7) READY — Tomasz (DRV-1003): active, chip + TAN, training EXPIRED.
        //    The order is queued for him, but at login he is forced into
        //    Safety Training before he can act on it (TRAINING_REQUIRED).
        $b7 = $bay('BAY-2');
        LoadingOrder::firstOrCreate(
            ['order_no' => 'LO-2026-0007'],
            array_merge([
                'id' => (string) Str::uuid(),
                'source' => 'manual',
                'customer_id' => $customer2?->id ?? $customer1->id,
                'customer_name' => $customer2?->name ?? $customer1->name,
                'carrier_id' => $carrier?->id,
                'carrier_name' => $carrier?->carrier_name,
                'product_quality' => 'Hydrogen 5.0',
                'target_quantity' => 360.000,
                'unit' => 'kg',
                'planned_window_start' => $now->copy()->addHours(2),
                'planned_window_end' => $now->copy()->addHours(5),
                'task_flow' => DriverTask::TRAILER_FILLING,
                'requires_certificate' => true,
                'requires_delivery_note' => true,
                'requires_qm_document' => false,
                'is_sap_owned' => false,
                'assigned_bay_line_id' => $b7?->id,
                'assigned_bay_line_code' => $b7?->code,
                'assigned_bay_line_name' => $b7?->name,
            ], $assign($tomasz, $tr4))
        );

        $this->refreshStatuses();
    }

    /**
     * Persist the derived `status` for every order this seeder owns.
     *
     * DatabaseSeeder uses the `WithoutModelEvents` trait, which suppresses
     * the LoadingOrder `saving()` hook that normally refreshes `status` via
     * LoadingOrderReadinessService. Without this, every row keeps the
     * migration default `draft` regardless of its data/assignments — so the
     * READY / IN_PROGRESS / etc. demo states (and the driver-terminal queue
     * that filters on them) would be wrong after a full seed. We re-derive
     * the canonical status here and write it with a raw query builder update
     * (which also bypasses the dead hook). Mirrors the same
     * WithoutModelEvents workaround used by HardwareDeviceSeeder /
     * AnalysisDeviceSeeder.
     */
    protected function refreshStatuses(): void
    {
        $readiness = app(LoadingOrderReadinessService::class);

        LoadingOrder::query()
            ->whereIn('order_no', [
                'LO-2026-0001', 'LO-2026-0002', 'LO-2026-0003', 'LO-2026-0004',
                'LO-2026-0005', 'LO-2026-0006', 'LO-2026-0007',
            ])
            ->get()
            ->each(function (LoadingOrder $order) use ($readiness) {
                $status = $readiness->deriveStatus($order);
                DB::table('loading_orders')
                    ->where('id', $order->id)
                    ->update(['status' => $status]);
            });
    }
}
