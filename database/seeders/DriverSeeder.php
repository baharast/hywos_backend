<?php

namespace Database\Seeders;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Models\AuthMedium;
use App\Models\Company;
use App\Models\Driver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $employer = Company::query()->where('code', 'TYCZKA')->first()
            ?? Company::query()->first();
        $operator = Company::query()->where('code', 'COMP-002')->first()
            ?? Company::query()->first();

        $employerId = $employer?->id;
        $operatorId = $operator?->id;

        $drivers = [
            [
                'driver_code' => 'DRV-1001',
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'national_id_last4' => '1234',
                'license_no' => 'B-123456',
                'license_expiry_date' => now()->addYears(2)->toDateString(),
                'phone' => '+49 170 000 0001',
                'email' => 'max.mustermann@example.local',
                'preferred_culture_code' => 'de',
                'training_status' => 'valid',
                'training_valid_until' => now()->addYear()->toDateString(),
                'block_status' => 'clear',
                'is_active' => true,
                'employer_company_id' => $employerId,
                'operator_company_id' => $operatorId,
                '_chip' => true,
            ],
            [
                'driver_code' => 'DRV-1002',
                'first_name' => 'Anna',
                'last_name' => 'Schneider',
                'national_id_last4' => '5678',
                'license_no' => 'B-654321',
                'license_expiry_date' => now()->addYears(3)->toDateString(),
                'phone' => '+49 170 000 0002',
                'email' => 'anna.schneider@example.local',
                'preferred_culture_code' => 'de',
                'training_status' => 'valid',
                'block_status' => 'clear',
                'is_active' => true,
                'employer_company_id' => $employerId,
                'operator_company_id' => $operatorId,
                '_chip' => true,
            ],
            [
                'driver_code' => 'DRV-1003',
                'first_name' => 'Tomasz',
                'last_name' => 'Nowak',
                'license_no' => 'PL-22-9988',
                'license_expiry_date' => now()->subMonths(2)->toDateString(),
                'phone' => '+48 600 000 003',
                'email' => 'tomasz.nowak@example.local',
                'preferred_culture_code' => 'pl',
                'training_status' => 'expired',
                'training_valid_until' => now()->subMonth()->toDateString(),
                'block_status' => 'clear',
                'is_active' => true,
                'employer_company_id' => $operatorId,
                'operator_company_id' => $operatorId,
                '_chip' => true,
            ],
            [
                'driver_code' => 'DRV-1004',
                'first_name' => 'Pierre',
                'last_name' => 'Dupont',
                'phone' => '+33 6 12 34 56 78',
                'email' => 'pierre.dupont@example.local',
                'preferred_culture_code' => 'fr',
                'training_status' => 'missing',
                'block_status' => 'clear',
                'is_active' => true,
                'employer_company_id' => $operatorId,
                '_tan' => true,
            ],
            [
                'driver_code' => 'DRV-1005',
                'first_name' => 'Klaus',
                'last_name' => 'Becker',
                'phone' => '+49 170 000 0005',
                'preferred_culture_code' => 'de',
                'training_status' => 'valid',
                'block_status' => 'blocked',
                'block_reason' => 'License revoked by carrier — investigation ongoing.',
                'blocked_at' => now()->subDays(3),
                'is_active' => true,
                'employer_company_id' => $employerId,
            ],
            [
                'driver_code' => 'DRV-1006',
                'first_name' => 'Helena',
                'last_name' => 'Vogel',
                'preferred_culture_code' => 'de',
                'training_status' => 'not_required',
                'block_status' => 'clear',
                'is_active' => false, // inactive
                'employer_company_id' => $employerId,
            ],
        ];

        foreach ($drivers as $row) {
            $needsChip = $row['_chip'] ?? false;
            $needsTan = $row['_tan'] ?? false;
            unset($row['_chip'], $row['_tan']);

            $driver = Driver::firstOrCreate(
                ['driver_code' => $row['driver_code']],
                array_merge(['id' => (string) Str::uuid()], $row)
            );

            if ($needsChip) {
                AuthMedium::firstOrCreate(
                    [
                        'driver_id' => $driver->id,
                        'medium_type' => AuthMediumType::CHIP_CARD,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'identifier_hash' => hash('sha256', 'chip-' . $driver->driver_code),
                        'display_identifier' => 'CHIP-' . substr($driver->driver_code, -4),
                        'status' => AuthMediumStatus::ACTIVE,
                        'is_single_use' => false,
                        'issued_at' => now()->subMonths(2),
                    ]
                );
            }

            if ($needsTan) {
                AuthMedium::firstOrCreate(
                    [
                        'driver_id' => $driver->id,
                        'medium_type' => AuthMediumType::TAN,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'identifier_value' => 'TAN' . random_int(100000, 999999),
                        'display_identifier' => 'TAN****',
                        'status' => AuthMediumStatus::ACTIVE,
                        'is_single_use' => true,
                        'issued_at' => now(),
                        'expires_at' => now()->addHours(8),
                    ]
                );
            }
        }
    }
}
