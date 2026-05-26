<?php

namespace Database\Seeders;

use App\Enums\ActiveAnalysisStatus;
use App\Enums\ActiveAnalysisType;
use App\Enums\AnalysisElementStatus;
use App\Enums\AnalysisUserAction;
use App\Enums\GasComponent;
use App\Enums\SamplingTrigger;
use App\Models\ActiveAnalysis;
use App\Models\AnalysisAttempt;
use App\Models\AnalysisElementResult;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Demo seed for Active Analyses (V1.4) covering the state matrix that
 * exercises each canonical action path. Rows are ordered so the FE sees
 * a mix of immediate decisions, in-progress runs, and held / done rows.
 *
 *   AN-2026-0001  pre_analysis  WAITING_DECISION → required: release_loading (VA-2)
 *   AN-2026-0002  pre_analysis  NOK (attempt 2/3)→ required: request_repeat_analysis
 *   AN-2026-0003  pre_analysis  NOK (attempt 3/3)→ required: reject_loading (VA-4)
 *   AN-2026-0004  main_analysis INVALID, no technical repeat used → repeat_measurement (HA-3)
 *   AN-2026-0005  main_analysis NOK            → required: manual_functional_approval (HA-5)
 *   AN-2026-0006  pre_analysis  RUNNING        → no action, monitor only
 *   AN-2026-0007  main_analysis ON_HOLD        → no action, allows cancel
 *
 * Each row's 6 element results are generated with realistic values per
 * the demo H2-5.0 spec (Track B): H2 high-purity, impurities low ppm.
 * NOK / INVALID rows tag one or more elements with the matching status
 * + a `validityReason` so the workbench renders the rule trace.
 */
class ActiveAnalysisSeeder extends Seeder
{
    public function run(): void
    {
        $orders = $this->fetchOrderBindings();
        $deviceId = $this->resolveDeviceId('AN-OS-01');
        $specId = $this->resolveSpecId('H2-5.0', 'v1');

        $rows = [
            [
                'display_no' => 'AN-2026-0001',
                'type' => ActiveAnalysisType::PRE_ANALYSIS,
                'trigger' => SamplingTrigger::BEFORE_LOADING,
                'status' => ActiveAnalysisStatus::WAITING_DECISION,
                'order_key' => 'LO-2026-0001',
                'attempt_count' => 1,
                'elements_mode' => 'all_valid',
                'latest_message' => 'Pre-analysis OK — release required.',
                'element_summary' => '6/6 valid',
            ],
            [
                'display_no' => 'AN-2026-0002',
                'type' => ActiveAnalysisType::PRE_ANALYSIS,
                'trigger' => SamplingTrigger::BEFORE_LOADING,
                'status' => ActiveAnalysisStatus::NOK,
                'order_key' => 'LO-2026-0002',
                'attempt_count' => 2,
                'elements_mode' => 'o2_high',
                'latest_message' => 'O2 above max (attempt 2/3).',
                'element_summary' => 'O2 high, 5/6 valid',
            ],
            [
                'display_no' => 'AN-2026-0003',
                'type' => ActiveAnalysisType::PRE_ANALYSIS,
                'trigger' => SamplingTrigger::BEFORE_LOADING,
                'status' => ActiveAnalysisStatus::NOK,
                'order_key' => 'LO-2026-0003',
                'attempt_count' => 3,
                'elements_mode' => 'o2_high',
                'latest_message' => '3rd pre-analysis functionally NOK; loading must be rejected.',
                'element_summary' => 'O2 high (3rd attempt)',
            ],
            [
                'display_no' => 'AN-2026-0004',
                'type' => ActiveAnalysisType::MAIN_ANALYSIS,
                'trigger' => SamplingTrigger::MAIN_60_PERCENT,
                'status' => ActiveAnalysisStatus::INVALID,
                'order_key' => 'LO-2026-0004',
                'attempt_count' => 1,
                'elements_mode' => 'co2_invalid',
                'latest_message' => 'CO2 channel reported stale value; technical repeat available.',
                'element_summary' => 'CO2 invalid',
            ],
            [
                'display_no' => 'AN-2026-0005',
                'type' => ActiveAnalysisType::MAIN_ANALYSIS,
                'trigger' => SamplingTrigger::AFTER_LOADING,
                'status' => ActiveAnalysisStatus::NOK,
                'order_key' => 'LO-2026-0001',
                'attempt_count' => 1,
                'elements_mode' => 'n2_high',
                'latest_message' => 'Main analysis functionally NOK (N2). Manual approval required to release.',
                'element_summary' => 'N2 high',
            ],
            [
                'display_no' => 'AN-2026-0006',
                'type' => ActiveAnalysisType::PRE_ANALYSIS,
                'trigger' => SamplingTrigger::BEFORE_LOADING,
                'status' => ActiveAnalysisStatus::RUNNING,
                'order_key' => 'LO-2026-0002',
                'attempt_count' => 1,
                'elements_mode' => 'waiting',
                'latest_message' => 'Analyser running — sample point BAY-2 / TRL-001.',
                'element_summary' => 'Waiting result',
            ],
            [
                'display_no' => 'AN-2026-0007',
                'type' => ActiveAnalysisType::MAIN_ANALYSIS,
                'trigger' => SamplingTrigger::MAIN_30_PERCENT,
                'status' => ActiveAnalysisStatus::ON_HOLD,
                'order_key' => 'LO-2026-0003',
                'attempt_count' => 1,
                'elements_mode' => 'waiting',
                'latest_message' => 'On hold per dispatcher request.',
                'element_summary' => 'On hold',
                'hold_reason' => 'Awaiting dispatcher confirmation on trailer chip mismatch.',
            ],
        ];

        foreach ($rows as $r) {
            $order = $orders[$r['order_key']] ?? null;
            $a = $this->upsertAnalysis($r, $order, $deviceId, $specId);
            $this->seedAttemptsAndElements($a, $r);
            // Refresh the cached action snapshot through the service —
            // but we want this seeder to be self-contained, so inline
            // the computeAllowedActions logic in a minimal form:
            $this->stampActionSnapshot($a, $r);
        }
    }

    protected function upsertAnalysis(array $r, ?array $order, ?string $deviceId, ?string $specId): ActiveAnalysis
    {
        return ActiveAnalysis::firstOrCreate(
            ['display_no' => $r['display_no']],
            [
                'id' => (string) Str::uuid(),
                'analysis_type' => $r['type'],
                'sampling_trigger' => $r['trigger'],
                'status' => $r['status'],
                'order_id' => $order['id'] ?? null,
                'order_no' => $order['order_no'] ?? $r['order_key'],
                'sap_order_no' => $order['sap_order_no'] ?? null,
                'driver_id' => $order['driver_id'] ?? null,
                'driver_name' => $order['driver_name'] ?? null,
                'trailer_id' => $order['trailer_id'] ?? null,
                'trailer_label' => $order['trailer_label'] ?? 'TRL-001',
                'bay_line_id' => null,
                'station_name' => 'BAY-1',
                'device_id' => $deviceId,
                'device_bmk' => 'AN-OS-01',
                'device_name' => 'OrthoSmart',
                'product_spec_id' => $specId,
                'product_code' => 'H2-5.0',
                'spec_version' => 'v1',
                'attempt_count' => $r['attempt_count'],
                'max_attempts' => 3,
                'latest_message' => $r['latest_message'],
                'element_summary' => $r['element_summary'],
                'held_at' => isset($r['hold_reason']) ? now()->subHour() : null,
                'hold_reason' => $r['hold_reason'] ?? null,
            ]
        );
    }

    protected function seedAttemptsAndElements(ActiveAnalysis $a, array $r): void
    {
        // One attempt per attempt_count
        for ($i = 1; $i <= $r['attempt_count']; $i++) {
            $isLast = $i === (int) $r['attempt_count'];
            $attempt = AnalysisAttempt::firstOrCreate(
                ['analysis_id' => $a->id, 'attempt_no' => $i],
                [
                    'id' => (string) Str::uuid(),
                    'status' => $isLast ? $r['status'] : ActiveAnalysisStatus::CLOSED,
                    'latest_message' => $isLast ? $r['latest_message'] : "Attempt {$i} closed.",
                    'triggered_by' => $i === 1 ? 'system' : 'user_repeat',
                    'started_at' => now()->subMinutes(30 * (4 - $i)),
                    'finished_at' => $isLast && in_array($r['status'], [
                        ActiveAnalysisStatus::RUNNING,
                        ActiveAnalysisStatus::ON_HOLD,
                        ActiveAnalysisStatus::QUEUED,
                    ], true) ? null : now()->subMinutes(30 * (4 - $i) - 5),
                    'is_repeat' => $i > 1,
                    'request_reason' => $i > 1 ? 'Demo repeat for state matrix.' : null,
                ]
            );

            // Element results only on the LAST attempt (the one whose
            // status drives the workbench). Earlier attempts could carry
            // their own historical rows but for demo we only seed the
            // latest.
            if ($isLast) {
                $this->seedElementRowsForAttempt($a, $attempt, $r['elements_mode']);
            }
        }
    }

    /**
     * Generate 6 element rows. The `mode` argument flips one or more
     * elements to NOK / INVALID / WAITING to make the demo cover the
     * V1.4 §13 distinction between failed-limit (NOK) and untrusted
     * (INVALID).
     */
    protected function seedElementRowsForAttempt(ActiveAnalysis $a, AnalysisAttempt $attempt, string $mode): void
    {
        $base = [
            GasComponent::H2  => ['value' => 99.9995, 'unit' => '%',   'lower' => 99.999,  'upper' => null, 'limit_label' => '>= 99.999%'],
            GasComponent::O2  => ['value' => 0.5,      'unit' => 'ppm', 'lower' => null,    'upper' => 1.0,  'limit_label' => '<= 1.0 ppm'],
            GasComponent::N2  => ['value' => 1.8,      'unit' => 'ppm', 'lower' => null,    'upper' => 5.0,  'limit_label' => '<= 5.0 ppm'],
            GasComponent::CH4 => ['value' => 0.4,      'unit' => 'ppm', 'lower' => null,    'upper' => 1.0,  'limit_label' => '<= 1.0 ppm'],
            GasComponent::CO  => ['value' => 0.05,     'unit' => 'ppm', 'lower' => null,    'upper' => 0.2,  'limit_label' => '<= 0.2 ppm'],
            GasComponent::CO2 => ['value' => 0.08,     'unit' => 'ppm', 'lower' => null,    'upper' => 2.0,  'limit_label' => '<= 2.0 ppm'],
        ];

        foreach ($base as $element => $cfg) {
            $status = AnalysisElementStatus::VALID;
            $validityReason = null;
            $value = $cfg['value'];
            $diff = null;

            if ($mode === 'waiting') {
                $status = AnalysisElementStatus::WAITING;
                $value = null;
            } elseif ($mode === 'o2_high' && $element === GasComponent::O2) {
                $value = 4.2;
                $status = AnalysisElementStatus::NOK;
                $diff = '+3.2 ppm above max';
            } elseif ($mode === 'n2_high' && $element === GasComponent::N2) {
                $value = 8.4;
                $status = AnalysisElementStatus::NOK;
                $diff = '+3.4 ppm above max';
            } elseif ($mode === 'co2_invalid' && $element === GasComponent::CO2) {
                $status = AnalysisElementStatus::INVALID;
                $validityReason = 'Channel stale — last live value > 5 min old.';
            }

            AnalysisElementResult::firstOrCreate(
                ['attempt_id' => $attempt->id, 'element' => $element],
                [
                    'id' => (string) Str::uuid(),
                    'analysis_id' => $a->id,
                    'measured_value' => $value,
                    'unit' => $cfg['unit'],
                    'lower_limit' => $cfg['lower'],
                    'upper_limit' => $cfg['upper'],
                    'limit_label' => $cfg['limit_label'],
                    'difference_label' => $diff,
                    'status' => $status,
                    'validity_reason' => $validityReason,
                    'measured_at' => $value === null ? null : now()->subMinutes(15),
                ]
            );
        }
    }

    /**
     * Stamp the required_action + allowed_actions snapshot directly
     * (without calling the service) so the seeder stays free of DB
     * transactions inside transactions. The values mirror what
     * ActiveAnalysisService::computeAllowedActions() would return for
     * the same row.
     */
    protected function stampActionSnapshot(ActiveAnalysis $a, array $r): void
    {
        [$required, $reason, $allowed] = $this->snapshotFor($r);
        $a->required_action = $required;
        $a->required_action_reason = $reason;
        $a->allowed_actions = $allowed;
        $a->save();
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: array<int,string>}
     */
    protected function snapshotFor(array $r): array
    {
        $always = [AnalysisUserAction::VIEW_DETAILS];

        return match (true) {
            // 1) WAITING_DECISION pre-analysis → release loading
            $r['status'] === ActiveAnalysisStatus::WAITING_DECISION
                && $r['type'] === ActiveAnalysisType::PRE_ANALYSIS => [
                AnalysisUserAction::RELEASE_LOADING,
                'Pre-analysis OK — manual release is required to continue loading.',
                array_merge([AnalysisUserAction::RELEASE_LOADING, AnalysisUserAction::PUT_ON_HOLD, AnalysisUserAction::CANCEL_ANALYSIS], $always),
            ],
            // 2) NOK pre-analysis with attempts remaining → repeat
            $r['status'] === ActiveAnalysisStatus::NOK
                && $r['type'] === ActiveAnalysisType::PRE_ANALYSIS
                && $r['attempt_count'] < 3 => [
                AnalysisUserAction::REQUEST_REPEAT_ANALYSIS,
                sprintf('Pre-analysis failed limit (attempt %d/%d); request a repeat.', $r['attempt_count'], 3),
                array_merge([AnalysisUserAction::REQUEST_REPEAT_ANALYSIS, AnalysisUserAction::PUT_ON_HOLD, AnalysisUserAction::CANCEL_ANALYSIS], $always),
            ],
            // 3) NOK pre-analysis with attempts exhausted → reject (VA-4)
            $r['status'] === ActiveAnalysisStatus::NOK
                && $r['type'] === ActiveAnalysisType::PRE_ANALYSIS
                && $r['attempt_count'] >= 3 => [
                AnalysisUserAction::REJECT_LOADING_BLOCK_TRAILER,
                'Third pre-analysis is functionally NOK; loading cannot be released.',
                array_merge([AnalysisUserAction::REJECT_LOADING_BLOCK_TRAILER, AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK], $always),
            ],
            // 4) INVALID main analysis, no technical repeat → HA-3 repeat measurement
            $r['status'] === ActiveAnalysisStatus::INVALID
                && $r['type'] === ActiveAnalysisType::MAIN_ANALYSIS => [
                AnalysisUserAction::REPEAT_MEASUREMENT,
                'Main analysis is technically invalid; one technical repeat is allowed.',
                array_merge([AnalysisUserAction::REPEAT_MEASUREMENT, AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK, AnalysisUserAction::PUT_ON_HOLD], $always),
            ],
            // 5) NOK main analysis → HA-5 manual functional approval
            $r['status'] === ActiveAnalysisStatus::NOK
                && $r['type'] === ActiveAnalysisType::MAIN_ANALYSIS => [
                AnalysisUserAction::MANUAL_FUNCTIONAL_APPROVAL,
                'Main analysis is functionally NOK. Manual functional approval is exceptional and audited; otherwise quality remains blocked.',
                array_merge([AnalysisUserAction::MANUAL_FUNCTIONAL_APPROVAL, AnalysisUserAction::OPEN_FAULT_CASE_MANUAL_CHECK, AnalysisUserAction::PUT_ON_HOLD], $always),
            ],
            // 6) ON_HOLD — cancel only
            $r['status'] === ActiveAnalysisStatus::ON_HOLD => [
                null,
                'Analysis is on hold; resume by cancelling or following backend flow.',
                array_merge([AnalysisUserAction::CANCEL_ANALYSIS], $always),
            ],
            // Default — running / waiting / queued
            default => [
                null,
                null,
                array_merge([AnalysisUserAction::PUT_ON_HOLD, AnalysisUserAction::CANCEL_ANALYSIS], $always),
            ],
        };
    }

    /**
     * @return array<string,array{id:string,order_no:string,sap_order_no:?string,driver_id:?string,driver_name:?string,trailer_id:?string,trailer_label:?string}>
     */
    protected function fetchOrderBindings(): array
    {
        if (! Schema::hasTable('loading_orders')) {
            return [];
        }
        $rows = DB::table('loading_orders')
            ->whereIn('order_no', ['LO-2026-0001', 'LO-2026-0002', 'LO-2026-0003', 'LO-2026-0004'])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->order_no] = [
                'id' => $r->id,
                'order_no' => $r->order_no,
                'sap_order_no' => $r->sap_reference ?? null,
                'driver_id' => $r->assigned_driver_id ?? null,
                'driver_name' => $r->assigned_driver_name ?? null,
                'trailer_id' => $r->assigned_trailer_id ?? null,
                'trailer_label' => $r->assigned_trailer_label ?? null,
            ];
        }
        return $out;
    }

    protected function resolveDeviceId(string $bmk): ?string
    {
        if (! Schema::hasTable('analysis_devices')) {
            return null;
        }
        return DB::table('analysis_devices')->where('code', $bmk)->value('id');
    }

    protected function resolveSpecId(string $productCode, string $version): ?string
    {
        if (! Schema::hasTable('product_specifications')) {
            return null;
        }
        return DB::table('product_specifications')
            ->where('product_code', $productCode)
            ->where('spec_version', $version)
            ->value('id');
    }
}
