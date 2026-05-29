<?php

namespace App\Services\AlarmsEvents;

use App\Enums\AuditApprovalStatus;
use App\Enums\AuditChangeCategory;
use App\Enums\AuditActionType;
use App\Enums\AuditRetentionClass;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Source-facing read for V1 §8 Change Log / Audit Trail.
 *
 * Pure read surface over the existing `audit_logs` table. No new tables,
 * no new audit constants. The list and detail endpoints derive
 * change-category, action-type, approval-status and retention-class at
 * read time from `audit_logs.action` (entity.action format).
 *
 * Per §8.7 the audit trail is immutable from this surface — write
 * happens only as a side-effect of other modules' actions via
 * App\Services\Audit\AuditLogger. The optional four-eyes approval
 * workflow (§8.6) is forward-contract; all V1 rows surface as
 * approval_status='not_required'.
 */
class AuditTrailService
{
    public const ALLOWED_SORT_COLUMNS = [
        'created_at', 'action', 'entity_type',
    ];

    public function listForTab(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = AuditLog::query();
        $this->applyFilters($query, $filters);

        $sort = (string) ($filters['sort'] ?? '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (! in_array($column, self::ALLOWED_SORT_COLUMNS, true)) {
            $column = 'created_at';
            $direction = 'desc';
        }
        $query->orderBy($column, $direction)->orderBy('id', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Compose the FE-facing row from one audit_logs record.
     *
     * @return array<string,mixed>
     */
    public function enrichRow(AuditLog $row): array
    {
        $action = (string) $row->action;
        $changeCategory = AuditChangeCategory::deriveFrom($action);
        $actionType = AuditActionType::deriveFrom($action);
        $retentionClass = AuditRetentionClass::fromChangeCategory($changeCategory);
        $approval = AuditApprovalStatus::NOT_REQUIRED;

        return [
            'changeCategory' => [
                'value' => $changeCategory,
                'label' => AuditChangeCategory::label($changeCategory),
                'tone' => AuditChangeCategory::tone($changeCategory),
            ],
            'actionType' => [
                'value' => $actionType,
                'label' => AuditActionType::label($actionType),
                'tone' => AuditActionType::tone($actionType),
            ],
            'retentionClass' => [
                'value' => $retentionClass,
                'label' => AuditRetentionClass::label($retentionClass),
            ],
            'approval' => [
                'value' => $approval,
                'label' => AuditApprovalStatus::label($approval),
                'tone' => AuditApprovalStatus::tone($approval),
            ],
            'beforeAfterSummary' => $this->summarizeChange($row),
            'relatedRecords' => $this->resolveRelatedRecords($row),
        ];
    }

    /**
     * Compact "before → after" summary for the table column. Detail
     * panel still gets the full old_values / new_values payload — this
     * is the at-a-glance hint only.
     */
    public function summarizeChange(AuditLog $row): ?string
    {
        $old = $row->old_values ?? [];
        $new = $row->new_values ?? [];
        if (! is_array($old) || ! is_array($new) || (empty($old) && empty($new))) {
            return null;
        }

        // Pick the first field that actually changed.
        foreach ($new as $field => $newVal) {
            $oldVal = $old[$field] ?? null;
            if ($oldVal === $newVal) continue;
            $oldStr = $this->scalarPreview($oldVal);
            $newStr = $this->scalarPreview($newVal);
            return "{$field}: {$oldStr} → {$newStr}";
        }

        // Pure create — show field count.
        $count = count($new);
        return "Created with {$count} field(s)";
    }

    protected function scalarPreview($v): string
    {
        if ($v === null) return '∅';
        if (is_bool($v)) return $v ? 'true' : 'false';
        if (is_scalar($v)) {
            $s = (string) $v;
            return mb_strlen($s) > 40 ? mb_substr($s, 0, 37) . '…' : $s;
        }
        return '[object]';
    }

    /**
     * V1 §8.5 "Related records" detail section. We surface the entity
     * deep-link plus a best-effort correlation-id link to event journal.
     *
     * @return array{
     *   entityRoute:string|null,
     *   eventJournalByCorrelation:string|null
     * }
     */
    public function resolveRelatedRecords(AuditLog $row): array
    {
        $entityRoute = $this->resolveEntityRoute((string) $row->entity_type, (string) $row->entity_id);
        $eventJournalByCorrelation = $row->correlation_id
            ? "/alarms-events/event-journal?correlation_id={$row->correlation_id}"
            : null;

        return [
            'entityRoute' => $entityRoute,
            'eventJournalByCorrelation' => $eventJournalByCorrelation,
        ];
    }

    /**
     * Map an entity_type (Eloquent morph class) to a FE deep-link.
     * Unknown types return null — the FE then hides the link.
     */
    protected function resolveEntityRoute(string $entityType, string $entityId): ?string
    {
        if ($entityId === '' || $entityType === '') return null;

        $short = class_basename($entityType);
        return match ($short) {
            'User' => "/administration/users/{$entityId}",
            'Driver' => "/master-data/drivers/{$entityId}",
            'Trailer' => "/master-data/trailers/{$entityId}",
            'TractorVehicle' => "/master-data/tractors-vehicles/{$entityId}",
            'Customer' => "/master-data/customers/{$entityId}",
            'Carrier' => "/master-data/freight-forwarders-carriers/{$entityId}",
            'Company' => "/master-data/companies/{$entityId}",
            'ChipCard' => "/master-data/chip-cards/{$entityId}",
            'Tan' => "/master-data/tans/{$entityId}",
            'LoadingOrder' => "/operations/loading-orders/{$entityId}",
            'PlantVisit' => "/operations/plant-visits/{$entityId}",
            'ClarificationCase' => "/operations/clarification-cases/{$entityId}",
            'OperationalDocument' => "/documents-reports/operational-documents/{$entityId}",
            'HardwareDevice' => "/system-devices/hardware-devices/{$entityId}",
            'InterfaceHealth', 'Interface' => "/system-devices/interface-health/{$entityId}",
            'Analysis' => "/analysis-quality/active-analyses/{$entityId}",
            'AnalysisDevice' => "/analysis-quality/analysis-devices/{$entityId}",
            'ProductSpecification' => "/analysis-quality/products-quality-specs/{$entityId}",
            'CalibrationProfile' => "/analysis-quality/calibration-profiles/{$entityId}",
            'PlantConfiguration' => "/administration/plant-configuration",
            default => null,
        };
    }

    /* ============================================================
     * Summary bar
     * ============================================================ */

    /**
     * V1 §8.2 "First 5 seconds" — compact counts the FE summary strip
     * needs.
     *
     * @return array{
     *   totalToday:int, pendingApprovals:int, criticalToday:int,
     *   manualOverridesToday:int, securityToday:int,
     *   availableFilterValues:array<string,array<int,string>>
     * }
     */
    public function buildSummary(): array
    {
        $todayStart = Carbon::today();

        $todayQ = fn () => AuditLog::query()->where('created_at', '>=', $todayStart);

        $totalToday = $todayQ()->count();
        // Pending approvals — V1 has none; reserved for future workflow.
        $pendingApprovals = 0;

        // We can't filter by derived change category at SQL level, so
        // pull all today rows and bucket in PHP. This is bounded by
        // today's volume — fine for a summary card. If volume becomes
        // an issue, push category derivation into a stored column.
        $todayRows = $todayQ()->get(['action', 'reason']);

        $manualOverridesToday = 0;
        $securityToday = 0;
        $criticalToday = 0;

        foreach ($todayRows as $r) {
            $cat = AuditChangeCategory::deriveFrom((string) $r->action);
            if ($cat === AuditChangeCategory::MANUAL_OVERRIDE) $manualOverridesToday++;
            if ($cat === AuditChangeCategory::SECURITY_ROLE) $securityToday++;
            if (in_array($cat, [
                AuditChangeCategory::MANUAL_OVERRIDE,
                AuditChangeCategory::QUALITY_DECISION,
                AuditChangeCategory::SECURITY_ROLE,
            ], true)) {
                $criticalToday++;
            }
        }

        return [
            'totalToday' => $totalToday,
            'pendingApprovals' => $pendingApprovals,
            'criticalToday' => $criticalToday,
            'manualOverridesToday' => $manualOverridesToday,
            'securityToday' => $securityToday,
            'availableFilterValues' => $this->availableFilterValues(),
        ];
    }

    /**
     * Per spec §3 + module convention, dropdown options come from the
     * actual current dataset (last 30 days for memoryability).
     */
    public function availableFilterValues(): array
    {
        $since = Carbon::now()->subDays(30);
        $rows = AuditLog::query()
            ->where('created_at', '>=', $since)
            ->get(['action', 'entity_type', 'actor_name']);

        $actions = $rows->pluck('action')->filter()->unique()->values()->all();
        $entityTypes = $rows->pluck('entity_type')->filter()->map(fn ($e) => class_basename($e))->unique()->values()->all();
        $actorNames = $rows->pluck('actor_name')->filter()->unique()->values()->take(20)->all();

        $changeCategories = $rows
            ->map(fn ($r) => AuditChangeCategory::deriveFrom((string) $r->action))
            ->filter()->unique()->values()->all();

        $actionTypes = $rows
            ->map(fn ($r) => AuditActionType::deriveFrom((string) $r->action))
            ->filter()->unique()->values()->all();

        return [
            'changeCategories' => $changeCategories,
            'actionTypes' => $actionTypes,
            'entityTypes' => $entityTypes,
            'actions' => $actions,
            'actorNames' => $actorNames,
            'retentionClasses' => AuditRetentionClass::all(),
        ];
    }

    /* ============================================================
     * Filter plumbing
     * ============================================================ */

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('action', 'like', $search)
                    ->orWhere('entity_type', 'like', $search)
                    ->orWhere('entity_id', 'like', $search)
                    ->orWhere('reason', 'like', $search)
                    ->orWhere('actor_name', 'like', $search)
                    ->orWhere('correlation_id', 'like', $search);
            });
        }

        if (! empty($filters['change_category']) && in_array($filters['change_category'], AuditChangeCategory::all(), true)) {
            // Push down to SQL via prefix groups — for each category,
            // collect the entity-prefix set it covers.
            $prefixes = $this->prefixesForCategory($filters['change_category']);
            if (! empty($prefixes)) {
                $query->where(function (Builder $q) use ($prefixes) {
                    foreach ($prefixes as $p) {
                        $q->orWhere('action', 'like', $p . '.%');
                    }
                });
            }
        }

        if (! empty($filters['action_type']) && in_array($filters['action_type'], AuditActionType::all(), true)) {
            // Suffix match — derive a LIKE pattern from the action_type
            // canonical name. Multiple tail substrings map to one type.
            $tailPatterns = $this->tailPatternsForActionType($filters['action_type']);
            if (! empty($tailPatterns)) {
                $query->where(function (Builder $q) use ($tailPatterns) {
                    foreach ($tailPatterns as $p) {
                        $q->orWhere('action', 'like', '%.' . $p);
                        $q->orWhere('action', 'like', '%' . $p . '%');
                    }
                });
            }
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['entity_type'])) {
            // Accept either a short class name or a fully qualified one.
            $needle = $filters['entity_type'];
            $query->where(function (Builder $q) use ($needle) {
                $q->where('entity_type', $needle)
                    ->orWhere('entity_type', 'like', '%\\\\' . $needle);
            });
        }

        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (! empty($filters['correlation_id'])) {
            $query->where('correlation_id', $filters['correlation_id']);
        }

        if (! empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', $filters['actor_user_id']);
        }

        if (! empty($filters['actor_name'])) {
            $query->where('actor_name', 'like', '%' . $filters['actor_name'] . '%');
        }

        if (! empty($filters['date_from'])) {
            try {
                $query->where('created_at', '>=', Carbon::parse($filters['date_from']));
            } catch (\Throwable) {}
        }

        if (! empty($filters['date_to'])) {
            try {
                $query->where('created_at', '<=', Carbon::parse($filters['date_to']));
            } catch (\Throwable) {}
        }

        // Time-range shortcut filters per spec §8.3.
        $timeRange = $filters['time_range'] ?? null;
        if ($timeRange === 'today') {
            $query->where('created_at', '>=', Carbon::today());
        } elseif ($timeRange === 'last_24h') {
            $query->where('created_at', '>=', Carbon::now()->subDay());
        } elseif ($timeRange === 'last_7d') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        }
    }

    protected function prefixesForCategory(string $category): array
    {
        return match ($category) {
            AuditChangeCategory::MASTER_DATA => [
                'driver', 'trailer', 'tractor', 'tractor_vehicle',
                'customer', 'carrier', 'company', 'chip_card',
            ],
            AuditChangeCategory::PROCESS_STATUS => [
                'loading_order', 'plant_visit', 'loading',
                'clarification_case', 'bay_line', 'parking_slot',
                'gate_terminal', 'terminal_workflow', 'terminal_training',
                'sap_sync',
            ],
            AuditChangeCategory::QUALITY_DECISION => [
                'active_analysis', 'analysis', 'analysis_attempt',
                'analysis_device', 'product_spec', 'calibration_profile',
            ],
            AuditChangeCategory::DOCUMENT => [
                'document', 'operational_document', 'report',
                'document_print_attempt', 'printer_job',
            ],
            AuditChangeCategory::DEVICE_SERVICE_MODE => [
                'hardware_device', 'interface', 'plc', 'opc_ua',
            ],
            AuditChangeCategory::SECURITY_ROLE => [
                'user', 'role', 'auth_medium', 'tan', 'session', 'permission',
            ],
            AuditChangeCategory::CONFIGURATION => [
                'plant_config', 'plant_configuration',
            ],
            AuditChangeCategory::MANUAL_OVERRIDE => [
                // Manual override is identified by tail content, not
                // prefix; covered by tailPatternsForActionType().
            ],
            default => [],
        };
    }

    protected function tailPatternsForActionType(string $type): array
    {
        return match ($type) {
            AuditActionType::CREATE => ['created'],
            AuditActionType::UPDATE => ['updated'],
            AuditActionType::BLOCK => ['blocked'],
            AuditActionType::UNBLOCK => ['unblocked'],
            AuditActionType::ASSIGN => ['assigned'],
            AuditActionType::UNASSIGN => ['unassigned'],
            AuditActionType::APPROVE => ['approved', 'released', 'activated', 'restored'],
            AuditActionType::REJECT => ['rejected', 'denied', 'cancelled', 'invalidated', 'deactivated', 'disabled'],
            AuditActionType::OVERRIDE => ['override', 'force', 'manual_approval'],
            AuditActionType::PRINT => ['printed', 'queued_for_print'],
            AuditActionType::REPRINT => ['reprinted', 'rerouted'],
            AuditActionType::CONFIGURATION_CHANGE => ['config', 'change_request', 'service_mode'],
            default => [],
        };
    }
}
