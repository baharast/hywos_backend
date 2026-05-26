<?php

namespace Database\Seeders;

use App\Enums\CalibrationLockoutBehavior;
use App\Enums\CalibrationProfileStatus;
use App\Enums\GasComponent;
use App\Models\CalibrationComponent;
use App\Models\CalibrationProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Demo seed for Calibration Settings (V2.1):
 *   - One active profile for the OrthoSmart analyser (BMK AN-OS-01) with
 *     all 6 components configured + tolerance_percent=2.0.
 *
 * If Track A's `analysis_devices` table is already present, this seeder
 * resolves the soft `device_id` FK by code so the profile links to the
 * actual device row. Otherwise it leaves device_id null and keeps the
 * BMK snapshot in `device_bmk`.
 */
class CalibrationProfileSeeder extends Seeder
{
    public function run(): void
    {
        $deviceId = $this->resolveOrthoSmartDeviceId();

        $profile = CalibrationProfile::firstOrCreate(
            ['device_bmk' => 'AN-OS-01', 'profile_version' => 'v1'],
            [
                'id' => (string) Str::uuid(),
                'device_id' => $deviceId,
                'device_name' => 'OrthoSmart',
                'calibration_medium' => 'Cal Gas Mix 6-component',
                'certificate_ref' => 'CERT-2026-001',
                'status' => CalibrationProfileStatus::ACTIVE,
                'calibration_status' => CalibrationProfileStatus::CALIBRATION_STATUS_VALID,
                'lockout_behavior' => CalibrationLockoutBehavior::BLOCK_RELEASE_CERTIFICATE,
                'next_due_at' => now()->addDays(60),
                'medium_expiry_at' => now()->addDays(180),
                'last_run_at' => now()->subDays(2),
                'activated_at' => now()->subDays(7),
                'notes' => 'Demo profile — primary calibration for OrthoSmart analyser. Tolerance 2.0% across all 6 components.',
            ]
        );

        $rows = [
            // [component, unit, exact_value, tolerance_percent]
            [GasComponent::H2,  '%',   99.9995, 2.0],
            [GasComponent::O2,  'ppm', 0.5,     2.0],
            [GasComponent::N2,  'ppm', 1.8,     2.0],
            [GasComponent::CH4, 'ppm', 0.4,     2.0],
            [GasComponent::CO,  'ppm', 0.05,    2.0],
            [GasComponent::CO2, 'ppm', 0.08,    2.0],
        ];

        foreach ($rows as [$comp, $unit, $exact, $tolPct]) {
            CalibrationComponent::firstOrCreate(
                ['profile_id' => $profile->id, 'component' => $comp],
                [
                    'id' => (string) Str::uuid(),
                    'unit' => $unit,
                    'exact_value' => $exact,
                    'tolerance_percent' => $tolPct,
                    'precision_decimals' => 4,
                    'rounding_rule' => 'round',
                ]
            );
        }
    }

    /**
     * Try to resolve the OrthoSmart analyser's row id from
     * analysis_devices (Track A). If Track A hasn't landed yet, leave the
     * FK null — the snapshot in device_bmk + device_name keeps the
     * profile readable on its own.
     */
    protected function resolveOrthoSmartDeviceId(): ?string
    {
        if (! Schema::hasTable('analysis_devices')) {
            return null;
        }
        $row = DB::table('analysis_devices')->where('code', 'AN-OS-01')->first();
        return $row?->id;
    }
}
