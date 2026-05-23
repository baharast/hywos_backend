<?php

namespace Database\Seeders;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\TrailerStatus;
use App\Models\AuthMedium;
use App\Models\Company;
use App\Models\Trailer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TrailerSeeder extends Seeder
{
    public function run(): void
    {
        $carrier = Company::query()->where('code', 'TYCZKA')->first() ?? Company::query()->first();
        $customer = Company::query()->where('code', 'COMP-002')->first() ?? Company::query()->skip(1)->first();

        $carrierId = $carrier?->id;
        $customerId = $customer?->id;

        $rows = [
            [
                'trailer_code' => 'TR-1001',
                'trailer_label' => 'TR-1001 / SW-FT-100',
                'plate' => 'SW-FT-100',
                'trailer_type' => 'type_iii',
                'pressure_class' => '350-bar',
                'volume' => 500.000,
                'volume_unit' => 'kg',
                'approved_product_quality' => ['H2-5.0', 'H2-6.0'],
                'inspection_expiry_date' => now()->addYears(2)->toDateString(),
                'inspection_reference' => 'TUV-2024-1001',
                'technical_suitability' => 'approved',
                'status' => TrailerStatus::ACTIVE,
                'is_active' => true,
                'carrier_id' => $carrierId,
                '_chip' => true,
            ],
            [
                'trailer_code' => 'TR-1002',
                'trailer_label' => 'TR-1002 / SW-FT-101',
                'plate' => 'SW-FT-101',
                'trailer_type' => 'type_iii',
                'pressure_class' => '350-bar',
                'volume' => 480.000,
                'volume_unit' => 'kg',
                'approved_product_quality' => ['H2-5.0'],
                'inspection_expiry_date' => now()->addYear()->toDateString(),
                'inspection_reference' => 'TUV-2024-1002',
                'technical_suitability' => 'incomplete',
                'status' => TrailerStatus::ACTIVE,
                'is_active' => true,
                'carrier_id' => $carrierId,
                // no chip — should surface as MISSING in attention filter
            ],
            [
                'trailer_code' => 'TR-1003',
                'trailer_label' => 'TR-1003 / SW-FT-102',
                'plate' => 'SW-FT-102',
                'trailer_type' => 'iso_container',
                'pressure_class' => '200-bar',
                'volume' => 1200.000,
                'volume_unit' => 'kg',
                'approved_product_quality' => ['H2-5.0', 'H2-6.0'],
                'inspection_expiry_date' => now()->addYears(3)->toDateString(),
                'inspection_reference' => 'TUV-2024-1003',
                'technical_suitability' => 'approved',
                'status' => TrailerStatus::BLOCKED,
                'block_reason' => 'Pressure vessel re-test pending after over-pressure event.',
                'blocked_at' => now()->subDays(2),
                'is_active' => true,
                'carrier_id' => $carrierId,
                'customer_id' => $customerId,
                '_chip' => true,
            ],
            [
                'trailer_code' => 'TR-1004',
                'trailer_label' => 'TR-1004 / SW-FT-103',
                'plate' => 'SW-FT-103',
                'trailer_type' => 'bundle',
                'pressure_class' => '300-bar',
                'volume' => 350.000,
                'volume_unit' => 'kg',
                'approved_product_quality' => ['H2-5.0'],
                'inspection_expiry_date' => now()->addDays(15)->toDateString(), // expiring soon
                'inspection_reference' => 'TUV-2024-1004',
                'technical_suitability' => 'approved',
                'status' => TrailerStatus::ACTIVE,
                'is_active' => true,
                'carrier_id' => $carrierId,
                '_chip' => true,
            ],
            [
                'trailer_code' => 'TR-1005',
                'trailer_label' => 'TR-1005 / SW-FT-104',
                'plate' => 'SW-FT-104',
                'trailer_type' => 'type_iii',
                'pressure_class' => '350-bar',
                'volume' => 500.000,
                'volume_unit' => 'kg',
                'approved_product_quality' => [],
                'inspection_expiry_date' => now()->subDays(60)->toDateString(),
                'inspection_reference' => 'TUV-2020-1005',
                'technical_suitability' => 'not_suitable',
                'status' => TrailerStatus::ARCHIVED,
                'is_active' => false,
                'carrier_id' => $carrierId,
            ],
        ];

        foreach ($rows as $row) {
            $needsChip = $row['_chip'] ?? false;
            unset($row['_chip']);

            $trailer = Trailer::firstOrCreate(
                ['trailer_code' => $row['trailer_code']],
                array_merge(['id' => (string) Str::uuid()], $row)
            );

            if ($needsChip) {
                AuthMedium::firstOrCreate(
                    [
                        'trailer_id' => $trailer->id,
                        'medium_type' => AuthMediumType::CHIP_CARD,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'identifier_hash' => hash('sha256', 'trailer-chip-' . $trailer->trailer_code),
                        'display_identifier' => '•••• ' . substr($trailer->trailer_code, -4),
                        'status' => AuthMediumStatus::ACTIVE,
                        'is_single_use' => false,
                        'issued_at' => now()->subMonths(3),
                    ]
                );
            }
        }
    }
}
