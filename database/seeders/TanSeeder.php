<?php

namespace Database\Seeders;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\TanUsageState;
use App\Models\AuthMedium;
use App\Models\Driver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TanSeeder extends Seeder
{
    public function run(): void
    {
        // Driver lookups — the seeded driver codes are DRV-1001..DRV-1006
        // (Max, Anna, Tomasz, Pierre, Klaus, Helena). We pick subjects that
        // map sensibly to each state in the matrix.
        $max    = Driver::where('driver_code', 'DRV-1001')->first();
        $anna   = Driver::where('driver_code', 'DRV-1002')->first();
        $tomasz = Driver::where('driver_code', 'DRV-1003')->first();
        $klaus  = Driver::where('driver_code', 'DRV-1005')->first();

        $rows = [
            [
                'tan_reference' => 'TAN-2026-0001',
                'driver' => $max,
                'state' => 'active',
                'expires_at' => now()->addDays(7),
                'reason' => 'Driver chip not yet issued',
            ],
            [
                'tan_reference' => 'TAN-2026-0002',
                'driver' => $klaus,
                'state' => 'consumed',
                'expires_at' => now()->subDay()->addMinutes(30),
                'used_at' => now()->subDay(),
                'consumed_at' => now()->subDay(),
                'reason' => 'Emergency loading after chip failure',
            ],
            [
                'tan_reference' => 'TAN-2026-0003',
                'driver' => $tomasz,
                'state' => 'revoked',
                'expires_at' => now()->addDays(3),
                'revoked_at' => now()->subHours(6),
                'revocation_reason' => 'Driver lost phone',
                'reason' => 'Replacement after lost device',
            ],
            [
                'tan_reference' => 'TAN-2026-0004',
                'driver' => $anna,
                'state' => 'expired',
                'expires_at' => now()->subDays(2),
                'reason' => 'Single-loading exception',
            ],
            [
                'tan_reference' => 'TAN-2026-0005',
                'driver' => $max,
                'state' => 'expiring_soon',
                'expires_at' => now()->addHours(2),
                'reason' => 'Walk-in driver, chip pending',
            ],
        ];

        foreach ($rows as $row) {
            if (! $row['driver']) {
                $this->command?->warn("Skipping {$row['tan_reference']} — required driver not present.");
                continue;
            }

            $state = $row['state'];
            $status = match ($state) {
                'expired' => AuthMediumStatus::EXPIRED,
                'revoked' => AuthMediumStatus::BLOCKED,
                default   => AuthMediumStatus::ACTIVE,
            };
            $usageState = match ($state) {
                'consumed' => TanUsageState::CONSUMED,
                'revoked'  => TanUsageState::BLOCKED,
                'expired'  => TanUsageState::UNUSED,
                default    => TanUsageState::UNUSED,
            };
            $consumptionCount = $state === 'consumed' ? 1 : 0;

            // Generate an opaque sha256 hash from random bytes — we deliberately
            // do not store any guessable identifier_value for seeded rows.
            $hash = hash('sha256', random_bytes(16));
            $masked = '••' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            AuthMedium::firstOrCreate(
                ['tan_reference' => $row['tan_reference']],
                [
                    'id' => (string) Str::uuid(),
                    'medium_type' => AuthMediumType::TAN,
                    'driver_id' => $row['driver']->id,
                    'identifier_value' => null,
                    'identifier_hash' => $hash,
                    'display_identifier' => $masked,
                    'tan_masked' => $masked,
                    'is_single_use' => true,
                    'status' => $status,
                    'usage_state' => $usageState,
                    'consumption_count' => $consumptionCount,
                    'issued_at' => now()->subDays(1),
                    'valid_from' => now()->subDays(1),
                    'expires_at' => $row['expires_at'],
                    'used_at' => $row['used_at'] ?? null,
                    'consumed_at' => $row['consumed_at'] ?? null,
                    'revoked_at' => $row['revoked_at'] ?? null,
                    'revocation_reason' => $row['revocation_reason'] ?? null,
                    'reason' => $row['reason'] ?? null,
                ]
            );
        }
    }
}
