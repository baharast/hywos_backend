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
        $d1 = Driver::query()->where('driver_code', 'DRV-1001')->first(); // training=valid → success
        $d2 = Driver::query()->where('driver_code', 'DRV-1003')->first(); // training=expired → training_required
        $d3 = Driver::query()->where('driver_code', 'DRV-1005')->first(); // block_status=blocked → driver_blocked
        $d4 = Driver::query()->where('driver_code', 'DRV-1002')->first(); // training=valid (filler for expired/consumed/revoked/pending TANs)

        if (! $d1 || ! $d2 || ! $d3 || ! $d4) {
            return;
        }

        $rows = [
            // 11111 — success path
            [
                'tan' => '11111', 'driver' => $d1,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'valid_from' => now()->subDay(),
                'expires_at' => now()->addDays(30),
            ],
            // 22222 — training_required path
            [
                'tan' => '22222', 'driver' => $d2,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'valid_from' => now()->subDay(),
                'expires_at' => now()->addDays(30),
            ],
            // 33333 — driver_blocked path
            [
                'tan' => '33333', 'driver' => $d3,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'valid_from' => now()->subDay(),
                'expires_at' => now()->addDays(30),
            ],
            // 44444 — expired TAN (driver is fine; TAN itself past expiry)
            [
                'tan' => '44444', 'driver' => $d4,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'valid_from' => now()->subDays(10),
                'expires_at' => now()->subDay(),
            ],
            // 55555 — already used / consumed
            [
                'tan' => '55555', 'driver' => $d1,
                'status' => AuthMediumStatus::USED,
                'usage_state' => TanUsageState::CONSUMED,
                'valid_from' => now()->subDays(2),
                'expires_at' => now()->addDays(28),
                'consumed_at' => now()->subHours(3),
                'consumption_count' => 1,
                'used_at' => now()->subHours(3),
            ],
            // 66666 — revoked
            [
                'tan' => '66666', 'driver' => $d4,
                'status' => AuthMediumStatus::BLOCKED,
                'usage_state' => TanUsageState::BLOCKED,
                'valid_from' => now()->subDay(),
                'expires_at' => now()->addDays(30),
                'revoked_at' => now()->subHours(5),
                'revocation_reason' => 'Demo seed — revoked for REVOKED test path.',
            ],
            // 77777 — pending (valid_from in future)
            [
                'tan' => '77777', 'driver' => $d4,
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'valid_from' => now()->addHours(6),
                'expires_at' => now()->addDays(30),
            ],
            // 88888 — backup success TAN (driver 1)
            [
                'tan' => '88888', 'driver' => $d1,
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
                ->where('tan_reference', $r['tan'])
                ->first();

            $payload = [
                'medium_type' => AuthMediumType::TAN,
                'tan_reference' => $r['tan'],
                'tan_masked' => '***' . substr($r['tan'], -2),
                'identifier_value' => $r['tan'],
                'display_identifier' => "TAN-{$r['tan']}",
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
                'reason' => 'Demo seed for terminal login V3 test matrix.',
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
                'card_type' => 'driver_chip',
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
