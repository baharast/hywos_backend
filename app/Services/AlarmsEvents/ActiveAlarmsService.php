<?php

namespace App\Services\AlarmsEvents;

use App\Enums\AlarmBlockingImpact;
use App\Enums\AlarmCategory;
use App\Enums\AlarmOwnerRole;
use App\Enums\AlarmSeverity;
use App\Enums\AlarmSourceType;
use App\Enums\AlarmStatus;
use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Models\Alarm;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * V1 §6 Active Alarms service.
 *
 * Owns the live operational queue read, the V1 §6.5 alarm impact strip
 * summary, the V1 §6.6 filter pushdown, and the 5 workflow writes:
 *   - acknowledge(): ALARM_ACKNOWLEDGED
 *   - assign(): ALARM_ASSIGNED
 *   - markInProgress(): ALARM_MARKED_IN_PROGRESS
 *   - resolve(): ALARM_RESOLVED        (→ resolved_pending_close)
 *   - close(): ALARM_CLOSED            (→ closed)
 *
 * Per V1 §6.10 forbidden actions: software acknowledgement is a
 * WORKFLOW RECORD only — never a physical reset. The service never
 * sends commands to PLCs, never opens gates, never bypasses safety,
 * never force-releases loading. The same rule applies to assign,
 * mark-in-progress and resolve/close.
 *
 * Per V1 §6.9: High/Critical alarms must be acknowledged before close,
 * and require a resolution reason on close. The service enforces both
 * via DomainException.
 */
class ActiveAlarmsService
{
    public const ALLOWED_SORT_COLUMNS = [
        'first_seen_at', 'last_seen_at', 'severity', 'status',
        'occurrence_count',
    ];

    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events,
    ) {}

    /* ============================================================
     * Read
     * ============================================================ */

    public function listForTab(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Alarm::query();
        $this->applyFilters($query, $filters);

        // Default: exclude closed rows so the queue stays "active".
        // The status filter can re-include them.
        if (empty($filters['status']) && empty($filters['include_closed'])) {
            $query->where('status', '!=', AlarmStatus::CLOSED);
        }

        // Default sort per V1 §6.7: severity (Critical first) then
        // blocking-impact (real blocks before warning-only) then
        // first_seen_at DESC.
        if (empty($filters['sort'])) {
            $query->orderByRaw($this->defaultSortExpression());
        } else {
            $sort = (string) $filters['sort'];
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');
            if (! in_array($column, self::ALLOWED_SORT_COLUMNS, true)) {
                $column = 'first_seen_at';
                $direction = 'desc';
            }
            $query->orderBy($column, $direction);
        }

        return $query->paginate($perPage);
    }

    public function defaultSortExpression(): string
    {
        // CASE severity priority ASC, blocking priority ASC, first_seen DESC.
        $severityCase = "CASE severity
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            WHEN 'info' THEN 5
            ELSE 9 END";

        $blockingCase = "CASE blocking_impact
            WHEN 'blocks_exit' THEN 1
            WHEN 'blocks_loading' THEN 2
            WHEN 'analysis_approval_blocked' THEN 3
            WHEN 'blocks_documents' THEN 4
            WHEN 'blocks_gate_entry' THEN 5
            WHEN 'warning_only' THEN 9
            ELSE 9 END";

        return "{$severityCase} ASC, {$blockingCase} ASC, first_seen_at DESC";
    }

    /* ============================================================
     * Writes
     * ============================================================ */

    public function acknowledge(Alarm $alarm): Alarm
    {
        if ($alarm->status === AlarmStatus::CLOSED) {
            throw new \DomainException('Closed alarms cannot be acknowledged.');
        }
        if ($alarm->acknowledged_at !== null) {
            throw new \DomainException('Alarm already acknowledged.');
        }

        return DB::transaction(function () use ($alarm) {
            $alarm->update([
                'status' => AlarmStatus::ACKNOWLEDGED,
                'acknowledged_at' => now(),
                'acknowledged_by_user_id' => Auth::id(),
                'acknowledged_by_name' => Auth::user()?->name,
            ]);

            $this->audit->record(
                entity: $alarm,
                action: AuditAction::ALARM_ACKNOWLEDGED,
                newValues: [
                    'status' => $alarm->status,
                    'acknowledged_at' => $alarm->acknowledged_at?->toIso8601String(),
                ],
                reason: 'Alarm acknowledged',
            );

            $this->events->record(
                eventType: 'alarm.acknowledged',
                entity: $alarm,
                message: "Alarm acknowledged: {$alarm->title}",
                category: EventCategory::OPERATIONS,
                severity: $this->mapToEventSeverity($alarm->severity),
            );

            return $alarm->fresh();
        });
    }

    public function assign(Alarm $alarm, array $data): Alarm
    {
        if ($alarm->status === AlarmStatus::CLOSED) {
            throw new \DomainException('Closed alarms cannot be reassigned.');
        }

        return DB::transaction(function () use ($alarm, $data) {
            $alarm->update([
                'status' => AlarmStatus::ASSIGNED,
                'owner_role' => $data['owner_role'] ?? $alarm->owner_role,
                'owner_user_id' => $data['owner_user_id'] ?? null,
                'owner_user_name' => $data['owner_user_name'] ?? null,
            ]);

            $this->audit->record(
                entity: $alarm,
                action: AuditAction::ALARM_ASSIGNED,
                newValues: [
                    'owner_role' => $alarm->owner_role,
                    'owner_user_id' => $alarm->owner_user_id,
                    'owner_user_name' => $alarm->owner_user_name,
                ],
                reason: $data['reason'] ?? 'Alarm owner assigned',
            );

            return $alarm->fresh();
        });
    }

    public function markInProgress(Alarm $alarm): Alarm
    {
        if (! in_array($alarm->status, [
            AlarmStatus::ACKNOWLEDGED, AlarmStatus::ASSIGNED,
            AlarmStatus::ACTIVE,
        ], true)) {
            throw new \DomainException(
                "Alarm in status [{$alarm->status}] cannot move to in_progress."
            );
        }

        return DB::transaction(function () use ($alarm) {
            $alarm->update([
                'status' => AlarmStatus::IN_PROGRESS,
                'in_progress_at' => now(),
                'in_progress_by_user_id' => Auth::id(),
            ]);

            $this->audit->record(
                entity: $alarm,
                action: AuditAction::ALARM_MARKED_IN_PROGRESS,
                newValues: [
                    'status' => $alarm->status,
                    'in_progress_at' => $alarm->in_progress_at?->toIso8601String(),
                ],
                reason: 'Alarm marked in progress',
            );

            return $alarm->fresh();
        });
    }

    public function resolve(Alarm $alarm, array $data): Alarm
    {
        if ($alarm->status === AlarmStatus::CLOSED) {
            throw new \DomainException('Closed alarms cannot be resolved again.');
        }

        // V1 §6.9: High/Critical must be acknowledged before close. We
        // route resolve→pending close, so the acknowledgement check
        // moves to close(). Resolve itself does not require ack.
        if (AlarmSeverity::requiresResolutionReason($alarm->severity)
            && empty($data['resolution_reason'])
        ) {
            throw new \DomainException(
                "High/Critical alarms require a resolution reason."
            );
        }

        return DB::transaction(function () use ($alarm, $data) {
            $alarm->update([
                'status' => AlarmStatus::RESOLVED_PENDING_CLOSE,
                'resolved_at' => now(),
                'resolved_by_user_id' => Auth::id(),
                'resolved_by_name' => Auth::user()?->name,
                'resolution_reason' => $data['resolution_reason'] ?? null,
                'corrective_action' => $data['corrective_action'] ?? null,
            ]);

            $this->audit->record(
                entity: $alarm,
                action: AuditAction::ALARM_RESOLVED,
                newValues: [
                    'status' => $alarm->status,
                    'resolution_reason' => $alarm->resolution_reason,
                    'corrective_action' => $alarm->corrective_action,
                ],
                reason: $data['resolution_reason'] ?? 'Alarm resolved (pending close)',
            );

            $this->events->record(
                eventType: 'alarm.resolved',
                entity: $alarm,
                message: "Alarm resolved (pending close): {$alarm->title}",
                category: EventCategory::OPERATIONS,
                severity: $this->mapToEventSeverity($alarm->severity),
            );

            return $alarm->fresh();
        });
    }

    public function close(Alarm $alarm, array $data): Alarm
    {
        if ($alarm->status === AlarmStatus::CLOSED) {
            throw new \DomainException('Alarm already closed.');
        }

        // V1 §6.9: High/Critical must be acknowledged before close.
        if (AlarmSeverity::requiresAcknowledgementBeforeClose($alarm->severity)
            && $alarm->acknowledged_at === null
        ) {
            throw new \DomainException(
                "High/Critical alarms must be acknowledged before close."
            );
        }
        if (AlarmSeverity::requiresResolutionReason($alarm->severity)
            && empty($alarm->resolution_reason)
            && empty($data['resolution_reason'])
        ) {
            throw new \DomainException(
                "High/Critical alarms require a resolution reason on close."
            );
        }

        return DB::transaction(function () use ($alarm, $data) {
            $alarm->update([
                'status' => AlarmStatus::CLOSED,
                'closed_at' => now(),
                'resolved_at' => $alarm->resolved_at ?? now(),
                'resolved_by_user_id' => $alarm->resolved_by_user_id ?? Auth::id(),
                'resolved_by_name' => $alarm->resolved_by_name ?? Auth::user()?->name,
                'resolution_reason' => $alarm->resolution_reason ?? $data['resolution_reason'] ?? null,
                'corrective_action' => $alarm->corrective_action ?? $data['corrective_action'] ?? null,
            ]);

            $this->audit->record(
                entity: $alarm,
                action: AuditAction::ALARM_CLOSED,
                newValues: [
                    'status' => $alarm->status,
                    'closed_at' => $alarm->closed_at?->toIso8601String(),
                    'resolution_reason' => $alarm->resolution_reason,
                ],
                reason: $data['reason'] ?? $alarm->resolution_reason ?? 'Alarm closed',
            );

            $this->events->record(
                eventType: 'alarm.closed',
                entity: $alarm,
                message: "Alarm closed: {$alarm->title}",
                category: EventCategory::OPERATIONS,
                severity: $this->mapToEventSeverity($alarm->severity),
            );

            return $alarm->fresh();
        });
    }

    /* ============================================================
     * Summary bar (V1 §6.5 Alarm Impact Strip)
     * ============================================================ */

    /**
     * @return array{
     *   criticalOpen:int, unacknowledgedHighCritical:int,
     *   loadingBlocked:int, exitOrDocumentsBlocked:int,
     *   deviceOrInterfaceFaults:int,
     *   availableFilterValues:array<string,array<int,string>>
     * }
     */
    public function buildSummary(): array
    {
        $openStatuses = AlarmStatus::openStatuses();

        $criticalOpen = Alarm::query()
            ->where('severity', AlarmSeverity::CRITICAL)
            ->whereIn('status', $openStatuses)
            ->count();

        $unacknowledgedHighCritical = Alarm::query()
            ->whereIn('severity', [AlarmSeverity::CRITICAL, AlarmSeverity::HIGH])
            ->whereNull('acknowledged_at')
            ->whereIn('status', $openStatuses)
            ->count();

        $loadingBlocked = Alarm::query()
            ->where('blocking_impact', AlarmBlockingImpact::BLOCKS_LOADING)
            ->whereIn('status', $openStatuses)
            ->count();

        $exitOrDocumentsBlocked = Alarm::query()
            ->whereIn('blocking_impact', [
                AlarmBlockingImpact::BLOCKS_EXIT,
                AlarmBlockingImpact::BLOCKS_DOCUMENTS,
            ])
            ->whereIn('status', $openStatuses)
            ->count();

        $deviceOrInterfaceFaults = Alarm::query()
            ->whereIn('category', [
                AlarmCategory::DEVICE_COMMUNICATION,
                AlarmCategory::INTERFACE_SAP,
            ])
            ->whereIn('status', $openStatuses)
            ->count();

        return [
            'criticalOpen' => $criticalOpen,
            'unacknowledgedHighCritical' => $unacknowledgedHighCritical,
            'loadingBlocked' => $loadingBlocked,
            'exitOrDocumentsBlocked' => $exitOrDocumentsBlocked,
            'deviceOrInterfaceFaults' => $deviceOrInterfaceFaults,
            'availableFilterValues' => $this->availableFilterValues(),
        ];
    }

    public function availableFilterValues(): array
    {
        $since = Carbon::now()->subDays(30);
        $rows = Alarm::query()
            ->where(function (Builder $q) use ($since) {
                $q->where('first_seen_at', '>=', $since)
                    ->orWhereIn('status', AlarmStatus::openStatuses());
            })
            ->get(['severity', 'status', 'category', 'blocking_impact',
                'source_type', 'owner_role', 'location']);

        return [
            'severities' => $rows->pluck('severity')->filter()->unique()->values()->all(),
            'statuses' => $rows->pluck('status')->filter()->unique()->values()->all(),
            'categories' => $rows->pluck('category')->filter()->unique()->values()->all(),
            'blockingImpacts' => $rows->pluck('blocking_impact')->filter()->unique()->values()->all(),
            'sourceTypes' => $rows->pluck('source_type')->filter()->unique()->values()->all(),
            'owners' => $rows->pluck('owner_role')->filter()->unique()->values()->all(),
            'locations' => $rows->pluck('location')->filter()->unique()->values()->all(),
        ];
    }

    /* ============================================================
     * Filter plumbing
     * ============================================================ */

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($s) {
                $q->where('alarm_no', 'like', $s)
                    ->orWhere('title', 'like', $s)
                    ->orWhere('message', 'like', $s)
                    ->orWhere('source_label', 'like', $s)
                    ->orWhere('linked_entity_label', 'like', $s)
                    ->orWhere('alarm_code', 'like', $s);
            });
        }

        if (! empty($filters['severity'])) {
            $values = array_filter(array_map('trim', explode(',', (string) $filters['severity'])));
            $values = array_values(array_intersect($values, AlarmSeverity::all()));
            if ($values) $query->whereIn('severity', $values);
        }

        if (! empty($filters['status'])) {
            $values = array_filter(array_map('trim', explode(',', (string) $filters['status'])));
            $values = array_values(array_intersect($values, AlarmStatus::all()));
            if ($values) $query->whereIn('status', $values);
        }

        if (! empty($filters['category']) && in_array($filters['category'], AlarmCategory::all(), true)) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['blocking_impact'])) {
            $values = array_filter(array_map('trim', explode(',', (string) $filters['blocking_impact'])));
            $values = array_values(array_intersect($values, AlarmBlockingImpact::all()));
            if ($values) $query->whereIn('blocking_impact', $values);
        }

        if (! empty($filters['owner_role']) && in_array($filters['owner_role'], AlarmOwnerRole::all(), true)) {
            $query->where('owner_role', $filters['owner_role']);
        }

        if (! empty($filters['owner_user_id'])) {
            $query->where('owner_user_id', $filters['owner_user_id']);
        }

        if (! empty($filters['source_type']) && in_array($filters['source_type'], AlarmSourceType::all(), true)) {
            $query->where('source_type', $filters['source_type']);
        }

        if (! empty($filters['location'])) {
            $query->where('location', $filters['location']);
        }

        if (! empty($filters['linked_entity_type'])) {
            $needle = $filters['linked_entity_type'];
            $query->where(function (Builder $q) use ($needle) {
                $q->where('linked_entity_type', $needle)
                    ->orWhere('linked_entity_type', 'like', '%\\\\' . $needle);
            });
        }
        if (! empty($filters['linked_entity_id'])) {
            $query->where('linked_entity_id', $filters['linked_entity_id']);
        }

        if (! empty($filters['correlation_id'])) {
            $query->where('correlation_id', $filters['correlation_id']);
        }

        if (! empty($filters['age_minutes_min']) && is_numeric($filters['age_minutes_min'])) {
            $query->where('first_seen_at', '<=', Carbon::now()->subMinutes((int) $filters['age_minutes_min']));
        }

        if (! empty($filters['date_from'])) {
            try { $query->where('first_seen_at', '>=', Carbon::parse($filters['date_from'])); } catch (\Throwable) {}
        }
        if (! empty($filters['date_to'])) {
            try { $query->where('first_seen_at', '<=', Carbon::parse($filters['date_to'])); } catch (\Throwable) {}
        }
    }

    protected function mapToEventSeverity(?string $alarmSeverity): string
    {
        return match ($alarmSeverity) {
            AlarmSeverity::CRITICAL => EventSeverity::CRITICAL,
            AlarmSeverity::HIGH => EventSeverity::DANGER,
            AlarmSeverity::MEDIUM => EventSeverity::WARNING,
            default => EventSeverity::INFO,
        };
    }
}
