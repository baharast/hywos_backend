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
        $pierre = Driver::where('driver_code', 'DRV-1004')->first();
        $klaus  = Driver::where('driver_code', 'DRV-1005')->first();
        $helena = Driver::where('driver_code', 'DRV-1006')->first();

        $rows = [
            // ----- Non-active samples (kept so the state matrix stays covered).
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

            // ----- 10 active TANs spread across the seeded driver pool.
            // expires_at is null on every active row per current ops rule:
            // active TANs are open-ended bridges until consumed or
            // explicitly revoked. The auth_media.expires_at column is
            // nullable, and downstream filters (TansExporter etc.) already
            // treat NULL as "no expiry".
            [
                'tan_reference' => 'TAN-2026-0001',
                'driver' => $max,
                'state' => 'active',
                'expires_at' => null,
                'reason' => 'Driver chip not yet issued',
            ],
            [
                'tan_reference' => 'TAN-2026-0005',
                'driver' => $max,
                'state' => 'active',
                'expires_at' => null,
                'reason' => 'Walk-in driver, chip pending',
            ],
            [
                'tan_reference' => 'TAN-2026-0006',
                'driver' => $anna,
                'state' => 'active',
                'expires_at' => null,
                'reason' => 'Replacement chip ordered, TAN bridge',
            ],
            [
                'tan_reference' => 'TAN-2026-0007',
                'driver' => $tomasz,
                'state' => 'active',
                'expires_at' => null,
                'reason' => 'Cross-border permit override',
            ],
            [
                'tan_reference' => 'TAN-2026-0008',
                'driver' => $pierre,
                'state' => 'active',
                'expires_at' => null,
                'reason' => 'Spot loading approved by dispatcher',
            ],
            [
                'tan_reference' => 'TAN-2026-0009',
                'driver' => $pierre,
                'state' => 'active',
                'expires_at' => null,
                'reason' => 'Second-leg pickup, single use',
            ],
            [
                'tan_reference' => 'TAN-2026-0010',
                'driver' => $klaus,
                'state' => 'active',
                'expires_at' => null,
                'reason' => 'Chip lost; emergency bridge issued',
            ],
            [
                'tan_reference' => 'TAN-2026-0011',
                'driver' => $helena,
                'state' => 'active',
                'expires_at' => null,
                'reason' => 'New driver onboarding, awaiting chip',
            ],
            [
                'tan_reference' => 'TAN-2026-0012',
                'driver' => $helena,
                'state' => 'active',
                'expires_at' => null,
                'reason' => 'Last-minute one-off loading',
            ],
            [
                'tan_reference' => 'TAN-2026-0013',
                'driver' => $anna,
                'state' => 'active',
                'expires_at' => null,
                'reason' => 'Holiday cover for primary driver',
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
