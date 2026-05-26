<?php

namespace Database\Seeders;

use App\Enums\AnalysisDeviceCalibrationStatus;
use App\Enums\AnalysisDeviceHealthStatus;
use App\Enums\AnalysisDeviceRunState;
use App\Enums\AnalysisDeviceType;
use App\Enums\GasComponent;
use App\Models\AnalysisDevice;
use App\Models\AnalysisDeviceChannel;
use App\Models\AnalysisDeviceLatestReading;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * V1 §3 — three MVP analysis devices in a realistic mix of states so the
 * dashboard exercises every tone path (success / warning / danger / info).
 *
 * Idempotent: re-running won't multiply rows because we look up devices
 * by their stable `code` and children by their `(device_id, ...)` unique
 * key.
 */
class AnalysisDeviceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAnalyser();
        $this->seedRegard();
        $this->seedSam();
    }

    protected function seedAnalyser(): void
    {
        $device = AnalysisDevice::updateOrCreate(
            ['code' => 'AN-OS-01'],
            [
                'id' => $this->existingIdFor('AN-OS-01') ?? (string) Str::uuid(),
                'name' => 'OrthoSmart Analyser AN-OS-01',
                'device_type' => AnalysisDeviceType::ANALYSER,
                'health_status' => AnalysisDeviceHealthStatus::HEALTHY,
                'run_state' => AnalysisDeviceRunState::MEASURING,
                'calibration_status' => AnalysisDeviceCalibrationStatus::VALID,
                'active_method' => 'H2 5.0 / 6-component',
                'selected_sample_point' => 'BAY-1 / TRL-001',
                'last_message' => 'Measuring H2 5.0 sample from BAY-1',
                'last_heartbeat_at' => now()->subSeconds(20),
                'last_value_at' => now()->subSeconds(35),
                'next_calibration_due_at' => now()->addDays(45),
                'inhibit_active' => false,
            ]
        );

        $readings = [
            [GasComponent::H2,  99.9995, '%vol'],
            [GasComponent::O2,  0.5,     'ppm'],
            [GasComponent::N2,  1.8,     'ppm'],
            [GasComponent::CH4, 0.4,     'ppm'],
            [GasComponent::CO,  0.05,    'ppm'],
            [GasComponent::CO2, 0.08,    'ppm'],
        ];
        foreach ($readings as [$component, $value, $unit]) {
            AnalysisDeviceLatestReading::updateOrCreate(
                ['device_id' => $device->id, 'component' => $component],
                [
                    'value' => $value,
                    'unit' => $unit,
                    'validity' => 'valid',
                    'measured_at' => now()->subSeconds(35),
                ]
            );
        }
    }

    protected function seedRegard(): void
    {
        $device = AnalysisDevice::updateOrCreate(
            ['code' => 'AN-GW-01'],
            [
                'id' => $this->existingIdFor('AN-GW-01') ?? (string) Str::uuid(),
                'name' => 'GWA-REGARD3900 Gas Warning AN-GW-01',
                'device_type' => AnalysisDeviceType::GAS_WARNING_CONTROLLER,
                'health_status' => AnalysisDeviceHealthStatus::ALARM,
                'safety_state' => 'alarm',
                'last_message' => 'H2 channel CH-1 in A2 alarm (12 ppm)',
                'last_heartbeat_at' => now()->subSeconds(15),
                'last_value_at' => now()->subSeconds(20),
                'inhibit_active' => false,
            ]
        );

        $channels = [
            ['CH-1', 'H2 channel',  'H2',  'a2',     12.0,  'ppm', false, false, 'Threshold A2 exceeded'],
            ['CH-2', 'O2 channel',  'O2',  'normal', 20.9,  '%',   false, false, null],
            ['CH-3', 'Ambient CO',  'CO',  'normal', 2.0,   'ppm', false, false, null],
            ['CH-4', 'Ambient H2S', 'H2S', 'f1',     null,  null,  false, false, 'Channel fault — sensor not responding'],
        ];
        foreach ($channels as [$code, $label, $gas, $severity, $value, $unit, $ack, $inh, $msg]) {
            AnalysisDeviceChannel::updateOrCreate(
                ['device_id' => $device->id, 'channel_code' => $code],
                [
                    'label' => $label,
                    'gas' => $gas,
                    'severity' => $severity,
                    'measured_value' => $value,
                    'unit' => $unit,
                    'acknowledged' => $ack,
                    'inhibited' => $inh,
                    'last_message' => $msg,
                    'last_value_at' => now()->subSeconds(20),
                ]
            );
        }
    }

    protected function seedSam(): void
    {
        AnalysisDevice::updateOrCreate(
            ['code' => 'AN-SS-01'],
            [
                'id' => $this->existingIdFor('AN-SS-01') ?? (string) Str::uuid(),
                'name' => 'CGS-SAM1000DP2 Sample Switching AN-SS-01',
                'device_type' => AnalysisDeviceType::SAMPLE_SWITCHING_MODULE,
                // Warning, not alarm — the operator left it in LOCAL but
                // nothing is actually broken. V1 distinguishes "paused on
                // purpose" from "in trouble".
                'health_status' => AnalysisDeviceHealthStatus::WARNING,
                'routing_state' => 'local_mode',
                'selected_stream' => 'S1',
                'mode' => 'local',
                'last_message' => 'Module in LOCAL mode — DCS commands ignored',
                'last_heartbeat_at' => now()->subSeconds(30),
                'last_value_at' => now()->subSeconds(60),
                'inhibit_active' => false,
            ]
        );
    }

    /**
     * Keep the UUID stable across re-seeds when a device row already exists
     * (so soft FKs from event_logs / future modules don't dangle).
     */
    protected function existingIdFor(string $code): ?string
    {
        return AnalysisDevice::where('code', $code)->value('id');
    }
}
