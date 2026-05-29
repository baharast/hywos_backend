<?php

namespace App\Services\AlarmsEvents;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\LogbookArea;
use App\Enums\LogbookCategory;
use App\Enums\LogbookFollowUpStatus;
use App\Enums\LogbookSeverity;
use App\Models\LogbookEntry;
use App\Models\LogbookEntryCorrection;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * V1 §7.5 Safety & Operations Logbook service.
 *
 * Owns the read query, follow-up overdue derivation, summary build,
 * filter pushdown AND the 4 write actions allowed by spec §7.5:
 *   - create()           POST /
 *   - addFollowUp()      POST /{id}/follow-up
 *   - markFollowUpDone() POST /{id}/follow-up/done
 *   - correct()          POST /{id}/correct
 *
 * Per §7.5: no silent overwrite — correct() snapshots old text into
 * a LogbookEntryCorrection row before applying new text and emits a
 * LOGBOOK_ENTRY_CORRECTED audit row with full old/new payload.
 */
class LogbookService
{
    public const ALLOWED_SORT_COLUMNS = [
        'created_at', 'follow_up_due_at', 'severity', 'category',
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
        $query = LogbookEntry::query();
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
     * V1 §7.5 — follow_up_status `overdue` is DERIVED at read time
     * (open / in_progress + due_at in the past).
     */
    public function deriveFollowUpStatus(LogbookEntry $entry): string
    {
        $stored = $entry->follow_up_status;
        if (! $entry->follow_up_required) return $stored;
        if (in_array($stored, [LogbookFollowUpStatus::DONE, LogbookFollowUpStatus::CANCELLED], true)) {
            return $stored;
        }
        if ($entry->follow_up_due_at && $entry->follow_up_due_at->isPast()) {
            return LogbookFollowUpStatus::OVERDUE;
        }
        return $stored;
    }

    public function correctionsFor(LogbookEntry $entry): array
    {
        return LogbookEntryCorrection::query()
            ->where('logbook_entry_id', $entry->id)
            ->orderByDesc('corrected_at')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'correctedAt' => $c->corrected_at?->toIso8601String(),
                'correctedBy' => [
                    'userId' => $c->corrected_by_user_id,
                    'name' => $c->corrected_by_name,
                ],
                'oldTitle' => $c->old_title,
                'oldDescription' => $c->old_description,
                'oldActionTaken' => $c->old_action_taken,
                'newTitle' => $c->new_title,
                'newDescription' => $c->new_description,
                'newActionTaken' => $c->new_action_taken,
                'reason' => $c->reason,
            ])
            ->all();
    }

    /* ============================================================
     * Write: create
     * ============================================================ */

    public function create(array $data): LogbookEntry
    {
        return DB::transaction(function () use ($data) {
            $entry = LogbookEntry::create([
                'shift_label' => $data['shift_label'] ?? null,
                'category' => $data['category'],
                'severity' => $data['severity'],
                'area' => $data['area'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'action_taken' => $data['action_taken'] ?? null,
                'related_entity_type' => $data['related_entity_type'] ?? null,
                'related_entity_id' => $data['related_entity_id'] ?? null,
                'linked_alarm_id' => $data['linked_alarm_id'] ?? null,
                'linked_event_log_id' => $data['linked_event_log_id'] ?? null,
                'linked_clarification_case_id' => $data['linked_clarification_case_id'] ?? null,
                'follow_up_required' => (bool) ($data['follow_up_required'] ?? false),
                'follow_up_owner_user_id' => $data['follow_up_owner_user_id'] ?? null,
                'follow_up_owner_role' => $data['follow_up_owner_role'] ?? null,
                'follow_up_due_at' => $data['follow_up_due_at'] ?? null,
                'handover_flag' => (bool) ($data['handover_flag'] ?? false),
                'created_by_user_id' => Auth::id(),
                'created_by_name' => Auth::user()?->name ?? ($data['created_by_name'] ?? null),
                'correlation_id' => $data['correlation_id'] ?? null,
            ]);

            $this->audit->record(
                entity: $entry,
                action: AuditAction::LOGBOOK_ENTRY_CREATED,
                newValues: $this->snapshotForAudit($entry),
                reason: 'Logbook entry created',
            );

            $this->events->record(
                eventType: 'logbook_entry.created',
                entity: $entry,
                message: "Logbook entry created: {$entry->title}",
                category: EventCategory::OPERATIONS,
                severity: $this->mapToEventSeverity($entry->severity),
            );

            return $entry->fresh();
        });
    }

    /* ============================================================
     * Write: add follow-up to an existing entry
     * ============================================================ */

    public function addFollowUp(LogbookEntry $entry, array $data): LogbookEntry
    {
        if ($entry->follow_up_required && $entry->follow_up_status !== LogbookFollowUpStatus::DONE
            && $entry->follow_up_status !== LogbookFollowUpStatus::CANCELLED
        ) {
            throw new \DomainException('Logbook entry already has an open follow-up.');
        }

        return DB::transaction(function () use ($entry, $data) {
            $entry->update([
                'follow_up_required' => true,
                'follow_up_owner_user_id' => $data['follow_up_owner_user_id'] ?? null,
                'follow_up_owner_role' => $data['follow_up_owner_role'] ?? null,
                'follow_up_due_at' => $data['follow_up_due_at'],
                'follow_up_status' => LogbookFollowUpStatus::OPEN,
                'follow_up_completed_at' => null,
                'follow_up_completed_by_user_id' => null,
                'follow_up_completion_note' => null,
            ]);

            $this->audit->record(
                entity: $entry,
                action: AuditAction::LOGBOOK_FOLLOWUP_ADDED,
                newValues: [
                    'follow_up_owner_user_id' => $entry->follow_up_owner_user_id,
                    'follow_up_owner_role' => $entry->follow_up_owner_role,
                    'follow_up_due_at' => $entry->follow_up_due_at?->toIso8601String(),
                ],
                reason: $data['reason'] ?? 'Follow-up added to logbook entry',
            );

            return $entry->fresh();
        });
    }

    /* ============================================================
     * Write: mark follow-up done
     * ============================================================ */

    public function markFollowUpDone(LogbookEntry $entry, array $data): LogbookEntry
    {
        if (! $entry->follow_up_required) {
            throw new \DomainException('Logbook entry has no follow-up to complete.');
        }
        if ($entry->follow_up_status === LogbookFollowUpStatus::DONE) {
            throw new \DomainException('Follow-up already completed.');
        }
        if ($entry->follow_up_status === LogbookFollowUpStatus::CANCELLED) {
            throw new \DomainException('Follow-up was cancelled; reopen via add-follow-up.');
        }

        return DB::transaction(function () use ($entry, $data) {
            $entry->update([
                'follow_up_status' => LogbookFollowUpStatus::DONE,
                'follow_up_completed_at' => now(),
                'follow_up_completed_by_user_id' => Auth::id(),
                'follow_up_completion_note' => $data['completion_note'] ?? null,
            ]);

            $this->audit->record(
                entity: $entry,
                action: AuditAction::LOGBOOK_FOLLOWUP_COMPLETED,
                newValues: [
                    'follow_up_status' => $entry->follow_up_status,
                    'follow_up_completed_at' => $entry->follow_up_completed_at?->toIso8601String(),
                ],
                reason: $data['completion_note'] ?? 'Follow-up completed',
            );

            return $entry->fresh();
        });
    }

    /* ============================================================
     * Write: correct an entry (snapshot old → store new + audit)
     * ============================================================ */

    public function correct(LogbookEntry $entry, array $data): LogbookEntry
    {
        return DB::transaction(function () use ($entry, $data) {
            $oldTitle = $entry->title;
            $oldDescription = $entry->description;
            $oldActionTaken = $entry->action_taken;

            $newTitle = $data['title'] ?? $oldTitle;
            $newDescription = $data['description'] ?? $oldDescription;
            $newActionTaken = array_key_exists('action_taken', $data)
                ? $data['action_taken']
                : $oldActionTaken;

            // No-op guard: avoid storing a correction row when nothing
            // actually changed.
            if ($newTitle === $oldTitle
                && $newDescription === $oldDescription
                && $newActionTaken === $oldActionTaken
            ) {
                throw new \DomainException('Correction makes no content change.');
            }

            // 1) snapshot old + new on the correction row
            LogbookEntryCorrection::create([
                'logbook_entry_id' => $entry->id,
                'corrected_by_user_id' => Auth::id(),
                'corrected_by_name' => Auth::user()?->name,
                'old_title' => $oldTitle,
                'old_description' => $oldDescription,
                'old_action_taken' => $oldActionTaken,
                'new_title' => $newTitle,
                'new_description' => $newDescription,
                'new_action_taken' => $newActionTaken,
                'reason' => $data['reason'],
                'correlation_id' => $data['correlation_id'] ?? null,
            ]);

            // 2) Apply new values directly to the row. The CONTENT_FIELDS
            //    immutability hook only fires for updates that don't go
            //    through this service path; we bypass it here by writing
            //    via DB::table to avoid model events.
            DB::table('logbook_entries')
                ->where('id', $entry->id)
                ->update([
                    'title' => $newTitle,
                    'description' => $newDescription,
                    'action_taken' => $newActionTaken,
                    'updated_at' => now(),
                ]);

            $fresh = $entry->fresh();

            // 3) Emit the audit row with full before/after payload.
            $this->audit->record(
                entity: $fresh,
                action: AuditAction::LOGBOOK_ENTRY_CORRECTED,
                oldValues: [
                    'title' => $oldTitle,
                    'description' => $oldDescription,
                    'action_taken' => $oldActionTaken,
                ],
                newValues: [
                    'title' => $newTitle,
                    'description' => $newDescription,
                    'action_taken' => $newActionTaken,
                ],
                reason: $data['reason'],
            );

            return $fresh;
        });
    }

    /* ============================================================
     * Summary bar
     * ============================================================ */

    /**
     * @return array{
     *   totalThisShift:int, openFollowUps:int, overdueFollowUps:int,
     *   handoverItems:int, criticalNotesToday:int,
     *   availableFilterValues:array<string,array<int,string>>
     * }
     */
    public function buildSummary(): array
    {
        $todayStart = Carbon::today();

        $totalThisShift = LogbookEntry::query()
            ->where('created_at', '>=', Carbon::now()->subHours(12))
            ->count();

        $openFollowUps = LogbookEntry::query()
            ->where('follow_up_required', true)
            ->whereIn('follow_up_status', [LogbookFollowUpStatus::OPEN, LogbookFollowUpStatus::IN_PROGRESS])
            ->count();

        $overdueFollowUps = LogbookEntry::query()
            ->where('follow_up_required', true)
            ->whereIn('follow_up_status', [LogbookFollowUpStatus::OPEN, LogbookFollowUpStatus::IN_PROGRESS])
            ->whereNotNull('follow_up_due_at')
            ->where('follow_up_due_at', '<', now())
            ->count();

        $handoverItems = LogbookEntry::query()
            ->where('handover_flag', true)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        $criticalNotesToday = LogbookEntry::query()
            ->where('created_at', '>=', $todayStart)
            ->whereIn('severity', [LogbookSeverity::CRITICAL, LogbookSeverity::HIGH])
            ->count();

        return [
            'totalThisShift' => $totalThisShift,
            'openFollowUps' => $openFollowUps,
            'overdueFollowUps' => $overdueFollowUps,
            'handoverItems' => $handoverItems,
            'criticalNotesToday' => $criticalNotesToday,
            'availableFilterValues' => $this->availableFilterValues(),
        ];
    }

    public function availableFilterValues(): array
    {
        $since = Carbon::now()->subDays(30);
        $rows = LogbookEntry::query()
            ->where('created_at', '>=', $since)
            ->get(['category', 'severity', 'area', 'follow_up_status']);

        return [
            'categories' => $rows->pluck('category')->filter()->unique()->values()->all(),
            'severities' => $rows->pluck('severity')->filter()->unique()->values()->all(),
            'areas' => $rows->pluck('area')->filter()->unique()->values()->all(),
            'followUpStatuses' => LogbookFollowUpStatus::all(),
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
                $q->where('title', 'like', $s)
                    ->orWhere('description', 'like', $s)
                    ->orWhere('action_taken', 'like', $s)
                    ->orWhere('related_entity_type', 'like', $s)
                    ->orWhere('related_entity_id', 'like', $s)
                    ->orWhere('created_by_name', 'like', $s);
            });
        }

        if (! empty($filters['shift_label'])) {
            $query->where('shift_label', $filters['shift_label']);
        }

        if (! empty($filters['category']) && in_array($filters['category'], LogbookCategory::all(), true)) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['severity'])) {
            $values = array_filter(array_map('trim', explode(',', (string) $filters['severity'])));
            $values = array_values(array_intersect($values, LogbookSeverity::all()));
            if ($values) $query->whereIn('severity', $values);
        }

        if (! empty($filters['area']) && in_array($filters['area'], LogbookArea::all(), true)) {
            $query->where('area', $filters['area']);
        }

        if (array_key_exists('handover_flag', $filters) && $filters['handover_flag'] !== null && $filters['handover_flag'] !== '') {
            $query->where('handover_flag', filter_var($filters['handover_flag'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['follow_up_status']) && in_array($filters['follow_up_status'], LogbookFollowUpStatus::all(), true)) {
            if ($filters['follow_up_status'] === LogbookFollowUpStatus::OVERDUE) {
                // Overdue is derived: open/in_progress + due_at in the past.
                $query->where('follow_up_required', true)
                    ->whereIn('follow_up_status', [LogbookFollowUpStatus::OPEN, LogbookFollowUpStatus::IN_PROGRESS])
                    ->whereNotNull('follow_up_due_at')
                    ->where('follow_up_due_at', '<', now());
            } else {
                $query->where('follow_up_status', $filters['follow_up_status']);
            }
        }

        if (! empty($filters['created_by_user_id'])) {
            $query->where('created_by_user_id', $filters['created_by_user_id']);
        }

        if (! empty($filters['related_entity_type'])) {
            $needle = $filters['related_entity_type'];
            $query->where(function (Builder $q) use ($needle) {
                $q->where('related_entity_type', $needle)
                    ->orWhere('related_entity_type', 'like', '%\\\\' . $needle);
            });
        }
        if (! empty($filters['related_entity_id'])) {
            $query->where('related_entity_id', $filters['related_entity_id']);
        }

        if (! empty($filters['date_from'])) {
            try { $query->where('created_at', '>=', Carbon::parse($filters['date_from'])); } catch (\Throwable) {}
        }
        if (! empty($filters['date_to'])) {
            try { $query->where('created_at', '<=', Carbon::parse($filters['date_to'])); } catch (\Throwable) {}
        }
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    protected function snapshotForAudit(LogbookEntry $entry): array
    {
        return [
            'category' => $entry->category,
            'severity' => $entry->severity,
            'area' => $entry->area,
            'title' => $entry->title,
            'description' => $entry->description,
            'action_taken' => $entry->action_taken,
            'follow_up_required' => $entry->follow_up_required,
            'handover_flag' => $entry->handover_flag,
        ];
    }

    protected function mapToEventSeverity(?string $logbookSeverity): string
    {
        return match ($logbookSeverity) {
            LogbookSeverity::CRITICAL => EventSeverity::CRITICAL,
            LogbookSeverity::HIGH => EventSeverity::DANGER,
            LogbookSeverity::MEDIUM => EventSeverity::WARNING,
            default => EventSeverity::INFO,
        };
    }
}
