<?php

namespace Database\Seeders;

use App\Enums\LogbookArea;
use App\Enums\LogbookCategory;
use App\Enums\LogbookFollowUpStatus;
use App\Enums\LogbookSeverity;
use App\Models\Alarm;
use App\Models\LogbookEntry;
use App\Models\LogbookEntryCorrection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo seed for V1 §7.5 Safety & Operations Logbook.
 *
 *  LBK-2026-0001  safety_note      CRITICAL  FS-04            E-stop incident
 *  LBK-2026-0002  handover_note    HIGH      control_room     shift handover
 *  LBK-2026-0003  manual_action    MEDIUM    driver_terminal  manual TAN issued
 *  LBK-2026-0004  device_follow_up MEDIUM    technical_room   network switch swap (follow-up OPEN)
 *  LBK-2026-0005  near_miss        HIGH      entry_gate       trailer rolled, driver caught it
 *  LBK-2026-0006  operations_note  LOW       compressor       routine pressure trim (follow-up DONE)
 *  LBK-2026-0007  safety_note      MEDIUM    analysis         PPE reminder (follow-up OVERDUE)
 *  LBK-2026-0008  information      INFO      server_network   network maintenance scheduled
 *
 * Entry 0001 carries one logbook_entry_corrections row so the FE has a
 * row to exercise V1 §7.5 correction history (no silent overwrite).
 */
class LogbookSeeder extends Seeder
{
    public function run(): void
    {
        $linkedAlarmEstop = Alarm::query()->where('alarm_no', 'ALM-2026-0001')->first();
        $linkedAlarmPlc = Alarm::query()->where('alarm_no', 'ALM-2026-0008')->first();

        $rows = [
            // 1) Safety note — E-stop incident (linked to ALM-2026-0001)
            [
                'short_no' => 'LBK-2026-0001',
                'shift_label' => 'Morning shift',
                'category' => LogbookCategory::SAFETY_NOTE,
                'severity' => LogbookSeverity::CRITICAL,
                'area' => LogbookArea::FILLING_STATION_04,
                'title' => 'E-stop pressed at FS-04 — driver action verified',
                'description' => 'Driver pressed dispenser E-stop after noticing a hose kink. Verified no injury, no spill. Safety officer notified. Loading hold remains until manual reset.',
                'action_taken' => 'Filled-out E-stop card, notified safety officer, parked trailer in pickup loop.',
                'follow_up_required' => false,
                'handover_flag' => true,
                'created_by_name' => 'Operations Operator',
                'linked_alarm_id' => $linkedAlarmEstop?->id,
                'created_offset' => '-10 minutes',
                'correction' => [
                    'old_title' => 'E-stop pressed at FS-4 — driver action verified',
                    'new_title' => 'E-stop pressed at FS-04 — driver action verified',
                    'old_description' => 'Driver pressed dispenser E-stop after noticing a hose kink. Verified no injury. Safety officer notified.',
                    'new_description' => 'Driver pressed dispenser E-stop after noticing a hose kink. Verified no injury, no spill. Safety officer notified. Loading hold remains until manual reset.',
                    'reason' => 'Corrected FS label to two-digit form per logbook style guide; added spill-check confirmation.',
                    'corrected_offset' => '-5 minutes',
                ],
            ],
            // 2) Handover note — shift end
            [
                'short_no' => 'LBK-2026-0002',
                'shift_label' => 'Morning shift',
                'category' => LogbookCategory::HANDOVER_NOTE,
                'severity' => LogbookSeverity::HIGH,
                'area' => LogbookArea::CONTROL_ROOM,
                'title' => 'Handover — open alarms + earthing FS-01',
                'description' => 'Three open critical/high alarms (ALM-0001, 0002, 0004). FS-01 earthing clamp resistance still high; spare clamp tested but not installed.',
                'action_taken' => 'Briefed afternoon operator; spare clamp in tool cabinet.',
                'follow_up_required' => true,
                'follow_up_owner_role' => 'operator',
                'follow_up_due_at' => '+2 hours',
                'follow_up_status' => LogbookFollowUpStatus::IN_PROGRESS,
                'handover_flag' => true,
                'created_by_name' => 'Morning Operator',
                'created_offset' => '-2 hours',
            ],
            // 3) Manual action — replacement TAN issued
            [
                'short_no' => 'LBK-2026-0003',
                'shift_label' => 'Morning shift',
                'category' => LogbookCategory::MANUAL_ACTION,
                'severity' => LogbookSeverity::MEDIUM,
                'area' => LogbookArea::ENTRY_GATE,
                'title' => 'Manual TAN issued — DRV-1001 lost original',
                'description' => 'Driver DRV-1001 presented an expired TAN; verified ID and issued a fresh single-use TAN at the dispatcher desk.',
                'action_taken' => 'Generated TAN via /tans; original revoked via /tans/{id}/revoke.',
                'follow_up_required' => false,
                'handover_flag' => false,
                'created_by_name' => 'Dispatcher',
                'created_offset' => '-90 minutes',
            ],
            // 4) Device follow-up — network switch, follow-up OPEN
            [
                'short_no' => 'LBK-2026-0004',
                'shift_label' => 'Morning shift',
                'category' => LogbookCategory::DEVICE_FOLLOW_UP,
                'severity' => LogbookSeverity::MEDIUM,
                'area' => LogbookArea::SERVER_NETWORK,
                'title' => 'Network switch port replaced — monitor 24h',
                'description' => 'After ALM-2026-0008 (PLC heartbeat slow), replaced the offending switch port. Heartbeat normal at last check.',
                'action_taken' => 'Patched device to spare port; documented serial #5841.',
                'follow_up_required' => true,
                'follow_up_owner_role' => 'it_support',
                'follow_up_due_at' => '+22 hours',
                'follow_up_status' => LogbookFollowUpStatus::OPEN,
                'handover_flag' => true,
                'created_by_name' => 'IT Support',
                'linked_alarm_id' => $linkedAlarmPlc?->id,
                'created_offset' => '-3 hours',
            ],
            // 5) Near miss — trailer rolled
            [
                'short_no' => 'LBK-2026-0005',
                'shift_label' => 'Morning shift',
                'category' => LogbookCategory::NEAR_MISS,
                'severity' => LogbookSeverity::HIGH,
                'area' => LogbookArea::ENTRY_GATE,
                'title' => 'Near miss — trailer rolled 1m at entry gate',
                'description' => 'Driver forgot to set handbrake; trailer rolled ~1m before the chock engaged. No collision, no injury.',
                'action_taken' => 'Briefed driver on parking SOP; raised RR-2026-014 in safety system.',
                'follow_up_required' => true,
                'follow_up_owner_role' => 'operations_manager',
                'follow_up_due_at' => '+3 days',
                'follow_up_status' => LogbookFollowUpStatus::OPEN,
                'handover_flag' => false,
                'created_by_name' => 'Entry Gate Operator',
                'created_offset' => '-150 minutes',
            ],
            // 6) Operations note — pressure trim done
            [
                'short_no' => 'LBK-2026-0006',
                'shift_label' => 'Morning shift',
                'category' => LogbookCategory::OPERATIONS_NOTE,
                'severity' => LogbookSeverity::LOW,
                'area' => LogbookArea::COMPRESSOR,
                'title' => 'Routine compressor pressure trim',
                'description' => 'Adjusted outlet pressure setpoint by -0.1 bar after morning calibration sweep.',
                'action_taken' => 'Logged new setpoint, restarted compressor in normal mode.',
                'follow_up_required' => true,
                'follow_up_owner_role' => 'operator',
                'follow_up_due_at' => '-30 minutes',
                'follow_up_status' => LogbookFollowUpStatus::DONE,
                'follow_up_completed_at' => '-25 minutes',
                'follow_up_completion_note' => 'Verified setpoint stable for 30 min; closed.',
                'handover_flag' => false,
                'created_by_name' => 'Operations Operator',
                'created_offset' => '-3 hours',
            ],
            // 7) Safety note — PPE reminder, follow-up OVERDUE
            [
                'short_no' => 'LBK-2026-0007',
                'shift_label' => 'Morning shift',
                'category' => LogbookCategory::SAFETY_NOTE,
                'severity' => LogbookSeverity::MEDIUM,
                'area' => LogbookArea::ANALYSIS,
                'title' => 'PPE briefing reminder — analysis lab',
                'description' => 'Two technicians spotted without safety glasses inside the analysis room. Reminded individually.',
                'action_taken' => 'Updated posting at lab door; sent reminder via dispatch channel.',
                'follow_up_required' => true,
                'follow_up_owner_role' => 'operations_manager',
                'follow_up_due_at' => '-6 hours',                            // past → overdue derivation
                'follow_up_status' => LogbookFollowUpStatus::OPEN,           // still open + past due → FE renders overdue
                'handover_flag' => true,
                'created_by_name' => 'Analysis Specialist',
                'created_offset' => '-26 hours',
            ],
            // 8) Information — scheduled maintenance
            [
                'short_no' => 'LBK-2026-0008',
                'shift_label' => 'Morning shift',
                'category' => LogbookCategory::INFORMATION,
                'severity' => LogbookSeverity::INFO,
                'area' => LogbookArea::SERVER_NETWORK,
                'title' => 'Scheduled network maintenance Saturday 02:00 UTC',
                'description' => 'Core switch firmware update. Expect 5-10 min loss of remote access; plant runs in island mode.',
                'follow_up_required' => false,
                'handover_flag' => false,
                'created_by_name' => 'IT Support',
                'created_offset' => '-8 hours',
            ],
        ];

        foreach ($rows as $r) {
            // Resolve relative offsets to concrete datetimes.
            $createdAt = isset($r['created_offset']) ? now()->modify($r['created_offset']) : now();
            $dueAt = isset($r['follow_up_due_at']) && is_string($r['follow_up_due_at'])
                ? now()->modify($r['follow_up_due_at'])
                : null;
            $completedAt = isset($r['follow_up_completed_at']) && is_string($r['follow_up_completed_at'])
                ? now()->modify($r['follow_up_completed_at'])
                : null;

            $entry = LogbookEntry::query()->updateOrCreate(
                ['title' => $r['title']],
                [
                    'id' => (string) Str::uuid(),
                    'shift_label' => $r['shift_label'] ?? null,
                    'category' => $r['category'],
                    'severity' => $r['severity'],
                    'area' => $r['area'] ?? null,
                    'description' => $r['description'],
                    'action_taken' => $r['action_taken'] ?? null,
                    'follow_up_required' => $r['follow_up_required'] ?? false,
                    'follow_up_owner_role' => $r['follow_up_owner_role'] ?? null,
                    'follow_up_due_at' => $dueAt,
                    'follow_up_status' => $r['follow_up_status'] ?? LogbookFollowUpStatus::OPEN,
                    'follow_up_completed_at' => $completedAt,
                    'follow_up_completion_note' => $r['follow_up_completion_note'] ?? null,
                    'handover_flag' => $r['handover_flag'] ?? false,
                    'linked_alarm_id' => $r['linked_alarm_id'] ?? null,
                    'created_by_name' => $r['created_by_name'] ?? null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );

            // Append the correction row if the demo asked for one.
            if (! empty($r['correction'])) {
                $c = $r['correction'];
                $correctedAt = isset($c['corrected_offset'])
                    ? now()->modify($c['corrected_offset'])
                    : now();

                LogbookEntryCorrection::query()->updateOrCreate(
                    ['logbook_entry_id' => $entry->id, 'reason' => $c['reason']],
                    [
                        'id' => (string) Str::uuid(),
                        'corrected_at' => $correctedAt,
                        'corrected_by_name' => 'Operations Operator',
                        'old_title' => $c['old_title'] ?? null,
                        'new_title' => $c['new_title'] ?? null,
                        'old_description' => $c['old_description'] ?? null,
                        'new_description' => $c['new_description'] ?? null,
                        'old_action_taken' => $c['old_action_taken'] ?? null,
                        'new_action_taken' => $c['new_action_taken'] ?? null,
                    ]
                );
            }
        }
    }
}
