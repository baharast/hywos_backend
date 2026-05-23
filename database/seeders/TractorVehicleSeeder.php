<?php

namespace Database\Seeders;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Company;
use App\Models\Driver;
use App\Models\TractorVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TractorVehicleSeeder extends Seeder
{
    public function run(): void
    {
        $carrier = Company::query()->where('code', 'TYCZKA')->first()
            ?? Company::query()->first();
        $carrierId = $carrier?->id;

        $defaultDriver = Driver::query()->where('driver_code', 'DRV-1001')->first()
            ?? Driver::query()->first();
        $defaultDriverId = $defaultDriver?->id;

        $vehicles = [
            [
                'license_plate' => 'SW-TR-200',
                'vehicle_code' => 'TR-200',
                'plate_country' => 'DE',
                'vehicle_type' => VehicleType::TRACTOR_UNIT,
                'carrier_id' => $carrierId,
                'default_driver_id' => $defaultDriverId,
                'vin' => 'WMA000000HYW00200',
                'registration_expiry' => now()->addYears(2)->toDateString(),
                'insurance_expiry' => now()->addYears(1)->toDateString(),
                'status' => VehicleStatus::ACTIVE,
                'is_active' => true,
            ],
            [
                'license_plate' => 'SW-TR-201',
                'vehicle_code' => 'TR-201',
                'plate_country' => 'DE',
                'vehicle_type' => VehicleType::TRACTOR_UNIT,
                'carrier_id' => $carrierId,
                'default_driver_id' => null,
                'registration_expiry' => now()->addYears(3)->toDateString(),
                'insurance_expiry' => now()->addYears(2)->toDateString(),
                'status' => VehicleStatus::ACTIVE,
                'is_active' => true,
            ],
            [
                'license_plate' => 'SW-TR-202',
                'vehicle_code' => 'TR-202',
                'plate_country' => 'DE',
                'vehicle_type' => VehicleType::TRUCK,
                'carrier_id' => $carrierId,
                'status' => VehicleStatus::BLOCKED,
                'block_reason' => 'Suspended pending compliance review.',
                'blocked_at' => now()->subDays(2),
                'is_active' => true,
            ],
            [
                'license_plate' => 'SW-TR-203',
                'vehicle_code' => 'TR-203',
                'plate_country' => 'DE',
                'vehicle_type' => VehicleType::TRACTOR_UNIT,
                'carrier_id' => $carrierId,
                // compliance attention — registration expired
                'registration_expiry' => now()->subMonths(1)->toDateString(),
                'insurance_expiry' => now()->addYear()->toDateString(),
                'status' => VehicleStatus::ACTIVE,
                'is_active' => true,
            ],
            [
                'license_plate' => 'SW-SVC-001',
                'vehicle_code' => 'SVC-001',
                'plate_country' => 'DE',
                'vehicle_type' => VehicleType::SERVICE_VEHICLE,
                'carrier_id' => null,
                'owner_name' => 'HYWOS Internal Maintenance',
                'status' => VehicleStatus::ACTIVE,
                'is_active' => true,
            ],
        ];

        foreach ($vehicles as $row) {
            TractorVehicle::firstOrCreate(
                ['license_plate' => $row['license_plate']],
                array_merge(['id' => (string) Str::uuid()], $row)
            );
        }
    }
}
