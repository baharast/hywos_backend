<?php

namespace Database\Seeders;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\TanUsageState;
use App\Models\AuthMedium;
use App\Models\Driver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the TAN management list (`/api/tans`) with predictable test
 * values per V6 §16.1.
 *
 * Following the same convention as TerminalAuthDemoSeeder, each row
 * carries a fixed 7-digit `code` (instead of a random one) so devs can
 * actually log in at the kiosk using these TANs. The code is stored
 * in `identifier_value` (hidden from API), hashed into
 * `identifier_hash` (for authenticateWithTan() lookup), and exposed
 * via:
 *   - `display_identifier` = full human "XXX-XXXX" form (seed convention)
 *   - `tan_masked`         = safe "***-XXXX" mask (the only form
 *                            production-issued TANs would expose)
 *
 * Code mapping: `TAN-2026-NNNN` → `100NNNN` (e.g. TAN-2026-0001 →
 * `1000001` = `100-0001`). All values are 7 digits starting with 1,
 * so they fall inside the V6 generator range
 * [TanPolicy::VALUE_DIGITS = 7].
 *
 * The non-active rows (consumed / revoked / expired) keep their codes
 * too so devs can verify each lifecycle branch.
 */
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
                'code'   => '1000002',
                'driver' => $klaus,
                'state'  => 'consumed',
                'expires_at' => now()->subDay()->addMinutes(30),
                'used_at' => now()->subDay(),
                'consumed_at' => now()->subDay(),
                'reason' => 'Emergency loading after chip failure',
            ],
            [
                'tan_reference' => 'TAN-2026-0003',
                'code'   => '1000003',
                'driver' => $tomasz,
                'state'  => 'revoked',
                'expires_at' => now()->addDays(3),
                'revoked_at' => now()->subHours(6),
                'revocation_reason' => 'Driver lost phone',
                'reason' => 'Replacement after lost device',
            ],
            [
                'tan_reference' => 'TAN-2026-0004',
                'code'   => '1000004',
                'driver' => $anna,
                'state'  => 'expired',
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
                'code'   => '1000001',
                'driver' => $max,
                'state'  => 'active',
                'expires_at' => null,
                'reason' => 'Driver chip not yet issued',
            ],
            [
                'tan_reference' => 'TAN-2026-0005',
                'code'   => '1000005',
                'driver' => $max,
                'state'  => 'active',
                'expires_at' => null,
                'reason' => 'Walk-in driver, chip pending',
            ],
            [
                'tan_reference' => 'TAN-2026-0006',
                'code'   => '1000006',
                'driver' => $anna,
                'state'  => 'active',
                'expires_at' => null,
                'reason' => 'Replacement chip ordered, TAN bridge',
            ],
            [
                'tan_reference' => 'TAN-2026-0007',
                'code'   => '1000007',
                'driver' => $tomasz,
                'state'  => 'active',
                'expires_at' => null,
                'reason' => 'Cross-border permit override',
            ],
            [
                'tan_reference' => 'TAN-2026-0008',
                'code'   => '1000008',
                'driver' => $pierre,
                'state'  => 'active',
                'expires_at' => null,
                'reason' => 'Spot loading approved by dispatcher',
            ],
            [
                'tan_reference' => 'TAN-2026-0009',
                'code'   => '1000009',
                'driver' => $pierre,
                'state'  => 'active',
                'expires_at' => null,
                'reason' => 'Second-leg pickup, single use',
            ],
            [
                'tan_reference' => 'TAN-2026-0010',
                'code'   => '1000010',
                'driver' => $klaus,
                'state'  => 'active',
                'expires_at' => null,
                'reason' => 'Chip lost; emergency bridge issued',
            ],
            [
                'tan_reference' => 'TAN-2026-0011',
                'code'   => '1000011',
                'driver' => $helena,
                'state'  => 'active',
                'expires_at' => null,
                'reason' => 'New driver onboarding, awaiting chip',
            ],
            [
                'tan_reference' => 'TAN-2026-0012',
                'code'   => '1000012',
                'driver' => $helena,
                'state'  => 'active',
                'expires_at' => null,
                'reason' => 'Last-minute one-off loading',
            ],
            [
                'tan_reference' => 'TAN-2026-0013',
                'code'   => '1000013',
                'driver' => $anna,
                'state'  => 'active',
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

            // Per-row fixed code (V6 §16.1 7-digit). Hash + masked + human
            // forms are derived from it so the lookup, list view and kiosk
            // all agree on the same value. Mirrors TerminalAuthDemoSeeder's
            // known-value test pattern.
            $rawValue = $row['code'];
            $displayHuman = substr($rawValue, 0, 3) . '-' . substr($rawValue, 3);
            $masked = '***-' . substr($rawValue, -4);
            $hash = hash('sha256', $rawValue);

            // firstOrCreate UPDATES the existing row on re-seed so the hash
            // matches the documented `code` even if a previous random-value
            // run already inserted the reference.
            $payload = [
                'medium_type' => AuthMediumType::TAN,
                'driver_id' => $row['driver']->id,
                'identifier_value' => $rawValue,         // hidden from API per AuthMedium::$hidden
                'identifier_hash' => $hash,
                'display_identifier' => $displayHuman,   // "100-0001" — seed convention
                'tan_masked' => $masked,                 // "***-0001" — safe mask
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
            ];

            $tan = AuthMedium::query()
                ->where('tan_reference', $row['tan_reference'])
                ->first();

            if ($tan) {
                $tan->update($payload);
            } else {
                AuthMedium::create(array_merge([
                    'id' => (string) Str::uuid(),
                    'tan_reference' => $row['tan_reference'],
                ], $payload));
            }

            if ($state === 'active') {
                $this->command?->info("Seeded {$row['tan_reference']} → {$displayHuman} (driver {$row['driver']->driver_code})");
            }
        }
    }
}
