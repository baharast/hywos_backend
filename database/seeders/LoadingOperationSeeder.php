<?php

namespace Database\Seeders;

use App\Enums\AnalysisStatus;
use App\Enums\LoadingStatus;
use App\Models\BayLine;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\LoadingOperation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LoadingOperationSeeder extends Seeder
{
    public function run(): void
    {
        $bays = BayLine::query()
            ->whereIn('code', ['BAY-1', 'BAY-2', 'BAY-3', 'BAY-4'])
            ->orderBy('code')
            ->get()
            ->keyBy('code');

        if ($bays->isEmpty()) {
            return;
        }

        $drivers = Driver::query()->orderBy('driver_code')->take(6)->get()->values();
        $customers = Customer::query()->orderBy('code')->take(4)->get()->values();

        $pick = function ($collection, int $i) {
            if ($collection->isEmpty()) {
                return null;
            }
            return $collection->get($i % $collection->count());
        };

        $rows = [
            [
                'display_no' => 'LD-2026-0001',
                'bay_code' => 'BAY-1',
                'loading_status' => LoadingStatus::LOADING,
                'analysis_status' => AnalysisStatus::PRE_ANALYSIS_OK,
                'progress_percent' => 54,
                'target_quantity' => 1000.000,
                'actual_quantity' => 540.000,
                'started_at' => now()->subMinutes(35),
                'plc_status' => 'connected',
            ],
            [
                'display_no' => 'LD-2026-0002',
                'bay_code' => 'BAY-2',
                'loading_status' => LoadingStatus::WAITING_PRE_ANALYSIS,
                'analysis_status' => AnalysisStatus::PRE_ANALYSIS_OPEN,
                'progress_percent' => 0,
                'target_quantity' => 800.000,
                'actual_quantity' => 0.000,
                'plc_status' => 'connected',
            ],
            [
                'display_no' => 'LD-2026-0003',
                'bay_code' => 'BAY-3',
                'loading_status' => LoadingStatus::QUALITY_BLOCKED,
                'analysis_status' => AnalysisStatus::FUNCTIONALLY_NOK,
                'progress_percent' => 100,
                'target_quantity' => 1200.000,
                'actual_quantity' => 1200.000,
                'critical_alarm_count' => 1,
                'alarm_count' => 2,
                'started_at' => now()->subHours(2),
                'completed_at' => now()->subHour(),
                'plc_status' => 'connected',
            ],
            [
                'display_no' => 'LD-2026-0004',
                'bay_code' => 'BAY-1', // historical — will not be active because it's COMPLETED
                'loading_status' => LoadingStatus::COMPLETED,
                'analysis_status' => AnalysisStatus::QUALITY_CHECKED,
                'progress_percent' => 100,
                'target_quantity' => 900.000,
                'actual_quantity' => 902.500,
                'started_at' => now()->subHours(6),
                'completed_at' => now()->subHours(5),
                'plc_status' => 'connected',
            ],
            [
                'display_no' => 'LD-2026-0005',
                'bay_code' => 'BAY-4',
                'loading_status' => LoadingStatus::CLARIFICATION_REQUIRED,
                'analysis_status' => AnalysisStatus::PRE_ANALYSIS_OK,
                'progress_percent' => 12,
                'target_quantity' => 1000.000,
                'actual_quantity' => 120.000,
                'has_clarification' => true,
                'release_source' => 'panel',
                'release_reason_code' => 'TRAILER_CHIP_MISMATCH',
                'release_reason' => 'Trailer chip does not match the assigned order — operator escalation required.',
                'started_at' => now()->subMinutes(10),
                'plc_status' => 'degraded',
            ],
            [
                'display_no' => 'LD-2026-0006',
                'bay_code' => 'BAY-2', // collision with row 2 is intentional? No — BAY-2 row 2 above will conflict.
                // Move this one to BAY-3 (the BAY-3 row is QUALITY_BLOCKED but completed at clamping above)
                'loading_status' => LoadingStatus::RELEASED,
                'analysis_status' => AnalysisStatus::PRE_ANALYSIS_OK,
                'progress_percent' => 0,
                'target_quantity' => 700.000,
                'actual_quantity' => 0.000,
                'plc_status' => 'connected',
            ],
        ];

        // Resolve the BAY-2 collision: row 6 actually belongs to no bay yet (queued/released).
        $rows[5]['bay_code'] = null;

        foreach ($rows as $i => $row) {
            $bay = $row['bay_code'] ? $bays->get($row['bay_code']) : null;
            $driver = $pick($drivers, $i);
            $customer = $pick($customers, $i);

            // bay_code is a lookup helper for this seeder only; the table
            // stores bay_line_id (resolved above). Strip it before insert so
            // array_merge doesn't push it into the SQL column list.
            unset($row['bay_code']);

            LoadingOperation::firstOrCreate(
                ['display_no' => $row['display_no']],
                array_merge(
                    [
                        'id' => (string) Str::uuid(),
                        'bay_line_id' => $bay?->id,
                        'driver_id' => $driver?->id,
                        'trailer_id' => null,
                        'trailer_label' => 'TRL-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                        'tractor_plate' => 'M-AB ' . (1000 + $i),
                        'order_id' => null,
                        'order_no' => 'ORD-' . (2026000 + $i),
                        'sap_order_no' => 'SAP-' . (5000000 + $i),
                        'plant_visit_id' => null,
                        'visit_no' => 'PV-' . (200 + $i),
                        'customer_id' => $customer?->id,
                        'customer_name' => $customer?->name,
                        'product_quality' => 'H2 5.0',
                        'unit' => 'kg',
                        'has_clarification' => false,
                        'alarm_count' => 0,
                        'critical_alarm_count' => 0,
                        'last_event_at' => now(),
                    ],
                    $row
                )
            );
        }
    }
}
