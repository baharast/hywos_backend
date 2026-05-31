<?php

namespace Database\Seeders;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\ChipCardAssignmentState;
use App\Enums\TanUsageState;
use App\Models\AuthMedium;
use App\Models\Driver;
use App\Models\TerminalPanel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data for the Driver Terminal Login page (V3).
 *
 * Layers seeded:
 *   1. Driver terminal (TERM-DRV-1) flipped to status=active + 3-language
 *      support so the FE renders an `online` terminalStatus per V3 §15.1.
 *   2. Eight memorable 5-digit TANs (11111 … 88888) covering every
 *      LoginResultCode branch in the TAN auth path. Driver bindings reuse
 *      existing DRV-1001/1003/1005 to map to specific training/block
 *      states already seeded by DriverSeeder.
 *   3. Five demo chip cards (CC-0001 … CC-0005) covering success,
 *      training_required, driver_blocked, chip_blocked and chip_unassigned.
 *
 * Reseed-friendly: each row uses firstOrCreate + an explicit update pass
 * so re-running the seeder snaps the demo back to spec without piling up
 * duplicates.
 */
class TerminalAuthDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->activateDriverTerminal();
        $this->seedTans();
        $this->seedChipCards();
    }

    /**
     * Flip TERM-DRV-1 to status=active and broaden language support to
     * de/en/pl so the V3 page header reflects "online".
     */
    protected function activateDriverTerminal(): void
    {
        $terminal = TerminalPanel::query()->where('code', 'TERM-DRV-1')->first();
        if (! $terminal) {
            return;
        }
        $terminal->update([
            'status' => 'active',
            'is_active' => true,
            'language_support' => ['de', 'en', 'pl'],
        ]);
    }

    protected function seedTans(): void
    {
        // Driver bindings — read by driver_code so we tolerate seed order.
        // V6 demo IDs (drv-001, drv-101, drv-002) don't exist in our backend;
        // we keep DRV-NNNN and bind each V6 TAN to the driver whose existing
        // training/block STATE matches the V6 expectation (see §16.1).
        $d1 = Driver::query()->where('driver_code', 'DRV-1001')->first(); // training=valid, clear     → success
        $d2 = Driver::query()->where('driver_code', 'DRV-1003')->first(); // training=expired, clear   → training_required
        $d3 = Driver::query()->where('driver_code', 'DRV-1005')->first(); // block_status=blocked      → driver_blocked

        if (! $d1 || ! $d2 || ! $d3) {
            return;
        }

        // V6 §16.1 TAN matrix. tan_reference stores the digits only;
        // display_identifier carries the human-readable XXX-XXXX form.
        // TerminalAuthService::authenticateWithTan() strips non-digits on
        // input so both `482-9173` and `4829173` resolve to the same row.
        $rows = [
            // 482-9173 → success (driver M. Schmidt, trainingValid=true equivalent)
            [
                'digits' => '4829173', 'display' => '482-9173', 'driver' => $d1,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'valid_from' => now()->subDay(),
                'expires_at' => now()->addDays(30),
            ],
            // 100-0000 → training_required (driver T. Müller, trainingValid=false equivalent)
            [
                'digits' => '1000000', 'display' => '100-0000', 'driver' => $d2,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'valid_from' => now()->subDay(),
                'expires_at' => now()->addDays(30),
            ],
            // 000-0000 → expired (driver fine; TAN past expires_at)
            [
                'digits' => '0000000', 'display' => '000-0000', 'driver' => $d1,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'valid_from' => now()->subDays(10),
                'expires_at' => now()->subDay(),
            ],
            // 111-1111 → used / consumed (USED takes priority over EXPIRED in evaluateTanLifecycle)
            [
                'digits' => '1111111', 'display' => '111-1111', 'driver' => $d1,
                'status' => AuthMediumStatus::USED,
                'usage_state' => TanUsageState::CONSUMED,
                'valid_from' => now()->subDays(2),
                'expires_at' => now()->addDays(28),
                'consumed_at' => now()->subHours(3),
                'consumption_count' => 1,
                'used_at' => now()->subHours(3),
            ],
            // 332-2233 → driver_blocked (NOT in V6 §16.1 but kept so the
            // FE smoke-test page can verify the driver-blocked branch lights
            // up in dev mode; V6 spec collapses everything not listed to
            // tan_invalid, which would hide this real failure mode).
            [
                'digits' => '3322233', 'display' => '332-2233', 'driver' => $d3,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'valid_from' => now()->subDay(),
                'expires_at' => now()->addDays(30),
            ],
        ];

        foreach ($rows as $r) {
            /** @var Driver $driver */
            $driver = $r['driver'];

            $tan = AuthMedium::query()
                ->where('medium_type', AuthMediumType::TAN)
                ->where('tan_reference', $r['digits'])
                ->first();

            $payload = [
                'medium_type' => AuthMediumType::TAN,
                'tan_reference' => $r['digits'],          // digits only ("4829173")
                'tan_masked' => '***-' . substr($r['digits'], -4),
                'identifier_value' => $r['digits'],
                'display_identifier' => $r['display'],    // human form ("482-9173")
                'driver_id' => $driver->id,
                'status' => $r['status'],
                'usage_state' => $r['usage_state'],
                'is_single_use' => true,
                'issued_at' => now()->subDay(),
                'valid_from' => $r['valid_from'],
                'expires_at' => $r['expires_at'],
                'consumed_at' => $r['consumed_at'] ?? null,
                'consumption_count' => $r['consumption_count'] ?? 0,
                'used_at' => $r['used_at'] ?? null,
                'revoked_at' => $r['revoked_at'] ?? null,
                'revocation_reason' => $r['revocation_reason'] ?? null,
                'reason' => 'Demo seed for terminal login V6 test matrix.',
            ];

            if ($tan) {
                $tan->update($payload);
            } else {
                AuthMedium::create(array_merge([
                    'id' => (string) Str::uuid(),
                ], $payload));
            }
        }
    }

    protected function seedChipCards(): void
    {
        $d1 = Driver::query()->where('driver_code', 'DRV-1001')->first();
        $d2 = Driver::query()->where('driver_code', 'DRV-1003')->first();
        $d3 = Driver::query()->where('driver_code', 'DRV-1005')->first();

        if (! $d1 || ! $d2 || ! $d3) {
            return;
        }

        $rows = [
            // CC-0001 — success path
            [
                'code' => 'CC-0001', 'driver' => $d1,
                'status' => AuthMediumStatus::ACTIVE,
                'assignment_state' => ChipCardAssignmentState::ASSIGNED,
            ],
            // CC-0002 — training_required (driver has expired training)
            [
                'code' => 'CC-0002', 'driver' => $d2,
                'status' => AuthMediumStatus::ACTIVE,
                'assignment_state' => ChipCardAssignmentState::ASSIGNED,
            ],
            // CC-0003 — driver_blocked (chip ok, driver blocked)
            [
                'code' => 'CC-0003', 'driver' => $d3,
                'status' => AuthMediumStatus::ACTIVE,
                'assignment_state' => ChipCardAssignmentState::ASSIGNED,
            ],
            // CC-0004 — chip_blocked test
            [
                'code' => 'CC-0004', 'driver' => null,
                'status' => AuthMediumStatus::BLOCKED,
                'assignment_state' => ChipCardAssignmentState::UNASSIGNED,
            ],
            // CC-0005 — chip_unassigned test
            [
                'code' => 'CC-0005', 'driver' => null,
                'status' => AuthMediumStatus::ACTIVE,
                'assignment_state' => ChipCardAssignmentState::UNASSIGNED,
            ],
        ];

        foreach ($rows as $r) {
            $chip = AuthMedium::query()
                ->where('medium_type', AuthMediumType::CHIP_CARD)
                ->where('card_code', $r['code'])
                ->first();

            $payload = [
                'medium_type' => AuthMediumType::CHIP_CARD,
                'card_code' => $r['code'],
                'serial_number' => 'SN-' . $r['code'],
                'masked_uid' => hash('sha256', $r['code']),
                'card_type' => 'driver_card',
                'display_identifier' => $r['code'],
                'driver_id' => $r['driver']?->id,
                'assignment_state' => $r['assignment_state'],
                'status' => $r['status'],
                'is_single_use' => false,
                'issued_at' => now()->subMonths(2),
            ];

            if ($chip) {
                $chip->update($payload);
            } else {
                AuthMedium::create(array_merge([
                    'id' => (string) Str::uuid(),
                ], $payload));
            }
        }
    }
}
