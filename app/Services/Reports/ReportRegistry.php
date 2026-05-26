<?php

namespace App\Services\Reports;

use App\Enums\ReportId;

/**
 * Per-report metadata. Drives the Reports Hub list and §6 controls.
 *
 * The `dataSourceAvailable` flag is true only when every primary source
 * table the report needs is implemented in this backend. Reports whose
 * primary domain (analysis_quality, device fault history) hasn't shipped
 * yet still appear in the hub but with `availability="not_ready"` so the
 * FE can render an empty-state instead of crashing.
 */
class ReportRegistry
{
    /**
     * @return array<int, array<string,mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'id' => ReportId::DAILY_OPERATIONS,
                'title' => 'Daily Operations Report',
                'category' => 'Operations overview',
                'purpose' => 'Shows if the day is normal or blocked.',
                'primaryUsers' => ['Operations Manager', 'Dispatcher', 'Operator'],
                'defaultOutput' => ['pdf', 'xlsx'],
                'availability' => 'available',
                'dataSourceAvailable' => true,
                'placeholderReason' => null,
            ],
            [
                'id' => ReportId::LOADING_HISTORY,
                'title' => 'Loading History / Order Execution Report',
                'category' => 'Traceability',
                'purpose' => 'Full traceability for one order/loading/plant visit from SAP order to exit.',
                'primaryUsers' => ['Dispatcher', 'Operations Manager', 'Auditor', 'Customer Service'],
                'defaultOutput' => ['pdf', 'xlsx'],
                'availability' => 'available',
                'dataSourceAvailable' => true,
                'placeholderReason' => null,
            ],
            [
                'id' => ReportId::QUANTITY_THROUGHPUT,
                'title' => 'Quantity & Throughput Report',
                'category' => 'Production and efficiency',
                'purpose' => 'Shows loaded volume, throughput and segment durations.',
                'primaryUsers' => ['Operations Manager', 'Dispatcher'],
                'defaultOutput' => ['xlsx', 'pdf'],
                'availability' => 'available',
                'dataSourceAvailable' => true,
                'placeholderReason' => null,
            ],
            [
                'id' => ReportId::STATION_UTILIZATION,
                'title' => 'Filling Station Utilization Report',
                'category' => 'Station utilization',
                'purpose' => 'Compares station load, idle time and fault time.',
                'primaryUsers' => ['Operations Manager', 'Operator', 'IT/Support'],
                'defaultOutput' => ['xlsx'],
                'availability' => 'available',
                'dataSourceAvailable' => true,
                'placeholderReason' => null,
            ],
            [
                'id' => ReportId::ANALYSIS_QUALITY,
                'title' => 'Analysis & Quality Report',
                'category' => 'Quality evidence',
                'purpose' => 'Controls quality decisions and blocked analysis cases.',
                'primaryUsers' => ['Analysis Specialist', 'Operations Manager', 'Auditor'],
                'defaultOutput' => ['xlsx', 'pdf'],
                'availability' => 'not_ready',
                'dataSourceAvailable' => false,
                'placeholderReason' => 'Analysis & Quality module has not landed yet; no analysis_results / quality_decisions tables exist.',
            ],
            [
                'id' => ReportId::DOCUMENTS_PRINT,
                'title' => 'Documents & Print Report',
                'category' => 'Document readiness',
                'purpose' => 'Shows generated, printed, reprinted, handed-over or failed mandatory documents.',
                'primaryUsers' => ['Operator', 'Auditor', 'Operations Manager'],
                'defaultOutput' => ['pdf', 'xlsx'],
                'availability' => 'available',
                'dataSourceAvailable' => true,
                'placeholderReason' => null,
            ],
            [
                'id' => ReportId::CLARIFICATIONS_ALARMS_AUDIT,
                'title' => 'Clarification, Alarm & Audit Report',
                'category' => 'Exceptions and audit',
                'purpose' => 'Controls exceptions, alarms, manual corrections and audited decisions.',
                'primaryUsers' => ['Dispatcher', 'Operator', 'Operations Manager', 'Auditor'],
                'defaultOutput' => ['xlsx'],
                'availability' => 'available',
                'dataSourceAvailable' => true,
                'placeholderReason' => 'Alarms tab returns an empty list — no alarms table exists yet. Clarifications + Audit tabs are live.',
            ],
            [
                'id' => ReportId::GATE_ACCESS_EXIT,
                'title' => 'Gate Access & Exit Report',
                'category' => 'Gate and exit control',
                'purpose' => 'Shows entry/exit activity, denials and exit release decisions.',
                'primaryUsers' => ['Operator', 'Dispatcher', 'Auditor', 'IT/Support'],
                'defaultOutput' => ['xlsx', 'pdf'],
                'availability' => 'available',
                'dataSourceAvailable' => true,
                'placeholderReason' => null,
            ],
            [
                'id' => ReportId::DEVICE_HEALTH,
                'title' => 'Interface & Device Health Report',
                'category' => 'Technical health',
                'purpose' => 'Monitors technical components that can stop the process.',
                'primaryUsers' => ['IT/Support', 'Operator', 'Operations Manager'],
                'defaultOutput' => ['xlsx'],
                'availability' => 'available',
                'dataSourceAvailable' => true,
                'placeholderReason' => 'Inventory of gates / terminals / bay lines is live; per-device fault history (active faults / fault duration) returns an empty list — no device_faults table exists yet.',
            ],
        ];
    }

    public static function find(string $reportId): ?array
    {
        foreach (self::all() as $row) {
            if ($row['id'] === $reportId) {
                return $row;
            }
        }
        return null;
    }
}
