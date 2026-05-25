<?php

namespace Database\Seeders;

use App\Enums\GateTerminalCurrentScreen;
use App\Enums\GateTerminalSessionState;
use App\Enums\GateTerminalTouchpoint;
use App\Models\ClarificationCase;
use App\Models\Driver;
use App\Models\TerminalSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds 8 demo terminal sessions covering every V2.3 §9 state across all
 * three touchpoints, so the touchpoint board exercise every priority rule.
 *
 *  #  touchpoint        state            notes
 *  ─  ────────────────  ───────────────  ─────────────────────────────────
 *  1  entry_gate        idle             baseline calm row
 *  2  driver_terminal   active           normal trailer_identification flow
 *  3  entry_gate        denied           invalid driver chip/TAN
 *  4  driver_terminal   needs_operator   trailer chip unknown; clarification linked
 *  5  entry_gate        device_fault     reader offline — wins priority on entry_gate
 *  6  exit_gate         service_mode     touchpoint out for maintenance
 *  7  exit_gate         active           ready_for_exit, exit_validation screen
 *  8  driver_terminal   active           task_selection screen, normal flow
 *
 * Driver back-references resolve via DriverSeeder (DRV-1001..DRV-1006).
 * Visit / order back-references are soft FKs; we set the UUIDs only when
 * the seeded parent rows exist.
 */
class TerminalSessionSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve a few drivers for richer demo data; tolerate missing seed
        // (e.g. if DriverSeeder is skipped) by leaving driver_* null.
        $max = Driver::query()->where('driver_code', 'DRV-1001')->first();
        $anna = Driver::query()->where('driver_code', 'DRV-1002')->first();
        $tomasz = Driver::query()->where('driver_code', 'DRV-1003')->first();
        $klaus = Driver::query()->where('driver_code', 'DRV-1005')->first();

        // Plant visits are soft FKs — read the demo IDs if PlantVisitSeeder ran.
        $pv0019 = $this->visitId('PV-2026-0019');   // active TRAILER_FILLING visit
        $pv0022 = $this->visitId('PV-2026-0022');   // CLARIFICATION visit

        // Clarification cases (CC-2026-0001..0003) likewise soft.
        $cc0001 = ClarificationCase::query()->where('case_no', 'CC-2026-0001')->value('id');

        $rows = [
            // 1 — entry_gate / idle (calm baseline)
            [
                'session_no' => 'TS-2026-0001',
                'touchpoint' => GateTerminalTouchpoint::ENTRY_GATE,
                'device_label' => 'EntryReader-1',
                'device_health' => 'online',
                'session_state' => GateTerminalSessionState::IDLE,
                'last_activity_at' => now()->subMinutes(45),
            ],

            // 2 — driver_terminal / active / trailer_identification
            [
                'session_no' => 'TS-2026-0002',
                'touchpoint' => GateTerminalTouchpoint::DRIVER_TERMINAL,
                'device_label' => 'Terminal-CR-A',
                'device_health' => 'online',
                'driver' => $max,
                'plant_visit_id' => $pv0019,
                'visit_no' => $pv0019 ? 'PV-2026-0019' : null,
                'order_no' => 'LO-2026-0003',
                'current_screen' => GateTerminalCurrentScreen::TRAILER_IDENTIFICATION,
                'session_state' => GateTerminalSessionState::ACTIVE,
                'last_activity_at' => now()->subSeconds(40),
            ],

            // 3 — entry_gate / denied (invalid driver chip/TAN)
            [
                'session_no' => 'TS-2026-0003',
                'touchpoint' => GateTerminalTouchpoint::ENTRY_GATE,
                'device_label' => 'EntryReader-1',
                'device_health' => 'online',
                'driver' => $anna,
                'current_screen' => GateTerminalCurrentScreen::DRIVER_LOGIN,
                'session_state' => GateTerminalSessionState::DENIED,
                'issue_reason' => 'Driver chip/TAN denied.',
                'action_needed' => 'Check driver identity or open event.',
                'last_activity_at' => now()->subMinutes(2),
            ],

            // 4 — driver_terminal / needs_operator (trailer chip unknown,
            //     clarification linked, supportRequested true)
            [
                'session_no' => 'TS-2026-0004',
                'touchpoint' => GateTerminalTouchpoint::DRIVER_TERMINAL,
                'device_label' => 'Terminal-CR-B',
                'device_health' => 'online',
                'driver' => $tomasz,
                'plant_visit_id' => $pv0022,
                'visit_no' => $pv0022 ? 'PV-2026-0022' : null,
                'current_screen' => GateTerminalCurrentScreen::TRAILER_IDENTIFICATION,
                'session_state' => GateTerminalSessionState::NEEDS_OPERATOR,
                'needs_operator' => true,
                'support_requested' => true,
                'clarification_case_id' => $cc0001,
                'issue_reason' => 'Trailer chip not recognized.',
                'action_needed' => 'Open Clarification.',
                'last_activity_at' => now()->subMinutes(5),
            ],

            // 5 — entry_gate / device_fault (reader offline — beats denied on
            //     priority for the entry_gate card)
            [
                'session_no' => 'TS-2026-0005',
                'touchpoint' => GateTerminalTouchpoint::ENTRY_GATE,
                'device_label' => 'EntryGate-A',
                'device_health' => 'fault',
                'session_state' => GateTerminalSessionState::DEVICE_FAULT,
                'issue_reason' => 'Reader/gate feedback unavailable.',
                'action_needed' => 'Open Device Detail.',
                'last_activity_at' => now()->subSeconds(90),
            ],

            // 6 — exit_gate / service_mode
            [
                'session_no' => 'TS-2026-0006',
                'touchpoint' => GateTerminalTouchpoint::EXIT_GATE,
                'device_label' => 'ExitGate-B',
                'device_health' => 'service_mode',
                'session_state' => GateTerminalSessionState::SERVICE_MODE,
                'issue_reason' => 'Exit gate B in scheduled maintenance.',
                'last_activity_at' => now()->subMinutes(20),
            ],

            // 7 — exit_gate / active / exit_validation (ready for exit)
            [
                'session_no' => 'TS-2026-0007',
                'touchpoint' => GateTerminalTouchpoint::EXIT_GATE,
                'device_label' => 'ExitGate-A',
                'device_health' => 'online',
                'driver' => $klaus,
                'plant_visit_id' => $pv0019,
                'visit_no' => $pv0019 ? 'PV-2026-0019' : null,
                'current_screen' => GateTerminalCurrentScreen::EXIT_VALIDATION,
                'session_state' => GateTerminalSessionState::ACTIVE,
                'last_activity_at' => now()->subSeconds(15),
            ],

            // 8 — driver_terminal / active / task_selection (normal flow)
            [
                'session_no' => 'TS-2026-0008',
                'touchpoint' => GateTerminalTouchpoint::DRIVER_TERMINAL,
                'device_label' => 'Terminal-CR-A',
                'device_health' => 'online',
                'driver' => $anna,
                'current_screen' => GateTerminalCurrentScreen::TASK_SELECTION,
                'session_state' => GateTerminalSessionState::ACTIVE,
                'last_activity_at' => now()->subSeconds(8),
            ],
        ];

        foreach ($rows as $row) {
            $driver = $row['driver'] ?? null;
            unset($row['driver']);

            $payload = array_merge([
                'id' => (string) Str::uuid(),
                'touchpoint_label' => GateTerminalTouchpoint::label($row['touchpoint']),
                'driver_id' => $driver?->id,
                'driver_name' => $driver ? trim($driver->first_name . ' ' . $driver->last_name) : null,
                'driver_code' => $driver?->driver_code,
            ], $row);

            TerminalSession::firstOrCreate(
                ['session_no' => $payload['session_no']],
                $payload
            );
        }
    }

    /**
     * Look up a plant visit UUID by visit_no. Returns null when the
     * plant_visits table doesn't exist yet or the row isn't seeded.
     */
    protected function visitId(string $visitNo): ?string
    {
        if (! Schema::hasTable('plant_visits')) {
            return null;
        }
        $row = DB::table('plant_visits')->where('visit_no', $visitNo)->first();
        return $row?->id;
    }
}
