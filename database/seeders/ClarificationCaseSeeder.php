<?php

namespace Database\Seeders;

use App\Enums\BlockingImpact;
use App\Enums\ClarificationEntityType;
use App\Enums\ClarificationPrimaryActionType;
use App\Enums\ClarificationSeverity;
use App\Enums\ClarificationSource;
use App\Enums\ClarificationStatus;
use App\Models\ClarificationCase;
use App\Models\LoadingOrder;
use App\Models\Trailer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ClarificationCaseSeeder extends Seeder
{
    public function run(): void
    {
        // Case 1 — try to bind to plant_visit PV-2026-0022 if TSK-002 has landed;
        // otherwise fall back to the first trailer.
        [$case1Type, $case1Id, $case1Label] = $this->resolveCase1Binding();

        $order1 = LoadingOrder::query()->where('order_no', 'LO-2026-0001')->first();

        $rows = [
            [
                'case_no' => 'CC-2026-0001',
                'status' => ClarificationStatus::OPEN,
                'severity' => ClarificationSeverity::NORMAL,
                'source' => ClarificationSource::GATE_TERMINAL,
                'blocking_impact' => BlockingImpact::REGISTRATION_BLOCKED,
                'primary_action' => ClarificationPrimaryActionType::RESOLVE_TRAILER_IDENTIFICATION,
                'action_needed' => 'Verify physical trailer before allowing registration',
                'category' => 'trailer_chip_mismatch',
                'title' => 'Trailer chip does not match assigned trailer',
                'description' => 'Chip scan at gate returned a UID that does not match the trailer assigned to the visit. Dispatcher needs to verify physical trailer before allowing loading to proceed.',
                'entity_type' => $case1Type,
                'entity_id' => $case1Id,
                'entity_label' => $case1Label,
                'owner_role' => 'dispatcher_manager',
                'is_blocking' => true,
            ],
            [
                'case_no' => 'CC-2026-0002',
                'status' => ClarificationStatus::IN_PROGRESS,
                'severity' => ClarificationSeverity::HIGH,
                'source' => ClarificationSource::ORDER_MATCHING,
                'blocking_impact' => BlockingImpact::LOADING_BLOCKED,
                'primary_action' => ClarificationPrimaryActionType::FIX_ORDER_ASSIGNMENT,
                'action_needed' => 'Split or reassign overlapping orders',
                'category' => 'order_assignment_conflict',
                'title' => 'Driver assigned to two overlapping orders',
                'description' => 'Driver DRV-1001 is currently assigned to both LO-2026-0001 (draft) and another order with overlapping planned windows. Dispatcher must split or reassign.',
                'entity_type' => ClarificationEntityType::LOADING_ORDER,
                'entity_id' => $order1?->id ?? (string) Str::uuid(),
                'entity_label' => $order1?->order_no ?? 'LO-2026-0001',
                'related_order_id' => $order1?->id,
                'owner_role' => 'dispatcher_manager',
                'is_blocking' => true,
            ],
            [
                'case_no' => 'CC-2026-0003',
                'status' => ClarificationStatus::RESOLVED,
                'severity' => ClarificationSeverity::LOW,
                'source' => ClarificationSource::DEVICE_INTERFACE,
                'blocking_impact' => BlockingImpact::NONE,
                'primary_action' => ClarificationPrimaryActionType::NONE,
                'action_needed' => null,
                'category' => 'sap_import_failed',
                'title' => 'SAP order import failed — customer mapping missing',
                'description' => 'Inbound order from SAP could not be created because the customer code on the SAP payload did not match any local customer.',
                'entity_type' => ClarificationEntityType::SAP_SYNC_RECORD,
                'entity_id' => (string) Str::uuid(),
                'entity_label' => 'sap-sync-0042',
                'owner_role' => 'it_support',
                'is_blocking' => false,
                'resolved_at' => now()->subHours(2),
                'resolution_note' => 'Mapping rule updated; record retried successfully.',
            ],
        ];

        foreach ($rows as $row) {
            ClarificationCase::firstOrCreate(
                ['case_no' => $row['case_no']],
                array_merge([
                    'id' => (string) Str::uuid(),
                    'opened_at' => now()->subHours(3),
                ], $row)
            );
        }
    }

    /**
     * Try to bind Case 1 to a plant_visit row (PV-2026-0022) if TSK-002 has
     * landed the plant_visits table + that demo row. Falls back to the first
     * trailer otherwise so the seeder remains robust to merge order.
     *
     * @return array{0:string,1:string,2:?string}
     */
    protected function resolveCase1Binding(): array
    {
        if (Schema::hasTable('plant_visits')) {
            $row = DB::table('plant_visits')->where('visit_no', 'PV-2026-0022')->first();
            if ($row) {
                return [
                    ClarificationEntityType::PLANT_VISIT,
                    (string) $row->id,
                    'PV-2026-0022',
                ];
            }
        }

        $trailer = Trailer::query()->orderBy('trailer_code')->first();
        if ($trailer) {
            return [
                ClarificationEntityType::TRAILER,
                $trailer->id,
                $trailer->trailer_code,
            ];
        }

        // Final fallback: placeholder UUID so the seeder never crashes.
        return [
            ClarificationEntityType::TRAILER,
            (string) Str::uuid(),
            'TR-PLACEHOLDER',
        ];
    }
}
