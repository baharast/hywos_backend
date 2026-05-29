<?php

namespace App\Services\AlarmsEvents;

use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\SecurityEventCategory;
use App\Enums\SecurityResult;
use App\Enums\SecurityReviewStatus;
use App\Enums\SecurityRiskLevel;
use App\Models\EventLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Source-facing read for V1 §7.6 Security Events (restricted subview).
 *
 * Reads from the existing `event_logs` table filtered to the SECURITY
 * event_category. The same physical table also backs the default Event
 * Journal, but security rows are excluded there per §7.2 / §7.6
 * permission boundary. This service is the only place security rows
 * are surfaced.
 *
 * Per V1 §7.6 the restriction is enforced at the route layer via the
 * `security_events.view` permission (admin / IT / operations_manager).
 *
 * mark-reviewed write workflow is V1 forward-contract: the column
 * doesn't exist yet, so review_status surfaces as `unreviewed` for
 * every row. When the workflow lands, the only change needed is a new
 * `security_event_reviews` table + a POST endpoint.
 */
class SecurityEventsService
{
    public const ALLOWED_SORT_COLUMNS = [
        'occurred_at', 'created_at', 'event_type', 'severity',
    ];

    public function listForTab(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = EventLog::query()->where('event_category', EventCategory::SECURITY);
        $this->applyFilters($query, $filters);

        $sort = (string) ($filters['sort'] ?? '-occurred_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (! in_array($column, self::ALLOWED_SORT_COLUMNS, true)) {
            $column = 'occurred_at';
            $direction = 'desc';
        }
        $query->orderBy($column, $direction)->orderBy('id', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * @return array<string,mixed>
     */
    public function enrichRow(EventLog $row): array
    {
        $category = SecurityEventCategory::deriveFrom($row->event_type);
        $result = SecurityResult::deriveFrom($row->event_type);
        $risk = $this->deriveRiskLevel($row, $result);
        $reviewStatus = SecurityReviewStatus::UNREVIEWED;

        return [
            'securityCategory' => [
                'value' => $category,
                'label' => SecurityEventCategory::label($category),
            ],
            'result' => [
                'value' => $result,
                'label' => SecurityResult::label($result),
                'tone' => SecurityResult::tone($result),
            ],
            'risk' => [
                'value' => $risk,
                'label' => SecurityRiskLevel::label($risk),
                'tone' => SecurityRiskLevel::tone($risk),
            ],
            'review' => [
                'value' => $reviewStatus,
                'label' => SecurityReviewStatus::label($reviewStatus),
                'tone' => SecurityReviewStatus::tone($reviewStatus),
                'available' => false,
                'note' => 'Review workflow not yet implemented. Persists in a future security_event_reviews table.',
            ],
            'identityMedium' => $this->resolveIdentityMedium($row),
            'sourceLocation' => $this->resolveSourceLocation($row),
            'safeActions' => $this->resolveSafeActions($row),
        ];
    }

    /**
     * V1 §7.6 lists Critical/High/Medium/Low/Info for risk; we derive
     * from severity + result outcome:
     *   - severity=critical OR result=locked              → critical
     *   - severity=danger  OR result IN (failed/blocked)  → high
     *   - severity=warning OR result=denied               → medium
     *   - severity=info    AND result=success             → info
     *   - else                                            → low
     */
    public function deriveRiskLevel(EventLog $row, string $result): string
    {
        $severity = strtolower((string) $row->severity);

        if ($severity === EventSeverity::CRITICAL || $result === SecurityResult::LOCKED) {
            return SecurityRiskLevel::CRITICAL;
        }
        if ($severity === EventSeverity::DANGER
            || in_array($result, [SecurityResult::FAILED, SecurityResult::BLOCKED], true)
        ) {
            return SecurityRiskLevel::HIGH;
        }
        if ($severity === EventSeverity::WARNING || $result === SecurityResult::DENIED) {
            return SecurityRiskLevel::MEDIUM;
        }
        if ($severity === EventSeverity::INFO && $result === SecurityResult::SUCCESS) {
            return SecurityRiskLevel::INFO;
        }
        return SecurityRiskLevel::LOW;
    }

    /**
     * V1 §7.6 "Identity medium" column. Derive from event_type prefix +
     * details payload hints.
     */
    public function resolveIdentityMedium(EventLog $row): array
    {
        $prefix = explode('.', (string) $row->event_type, 2)[0];
        $value = match ($prefix) {
            'auth' => 'username_password',
            'auth_medium', 'chip_card' => 'driver_chip',
            'tan' => 'tan',
            'session' => 'session_token',
            'api', 'service_token' => 'service_token',
            default => 'unknown',
        };

        return [
            'value' => $value,
            'label' => match ($value) {
                'username_password' => 'Username / password',
                'driver_chip' => 'Driver chip',
                'tan' => 'TAN',
                'session_token' => 'Session token',
                'service_token' => 'Service / API token',
                default => 'Unknown',
            },
        ];
    }

    /**
     * V1 §7.6 "Location / device" column. Best-effort from
     * actor / ip_address / entity hints; never reveals raw payloads.
     */
    public function resolveSourceLocation(EventLog $row): array
    {
        $ip = $row->ip_address;
        // Mask the last octet of IPv4 to avoid leaking exact source.
        $maskedIp = null;
        if ($ip !== null) {
            if (str_contains($ip, '.')) {
                $parts = explode('.', $ip);
                if (count($parts) === 4) {
                    $parts[3] = 'x';
                    $maskedIp = implode('.', $parts);
                }
            } elseif (str_contains($ip, ':')) {
                // IPv6 — coarse mask by keeping the prefix only.
                $maskedIp = explode(':', $ip)[0] . ':x:x:x';
            }
        }

        return [
            'maskedIp' => $maskedIp,
            'sourceHint' => $row->actor_name,
        ];
    }

    /**
     * V1 §7.6 allowed actions per row. Returns route hints that the FE
     * uses for the row action menu. Mark-reviewed is forward-contract.
     *
     * @return array<int,array{action:string,label:string,route:string|null,disabled:bool,reason:string|null}>
     */
    public function resolveSafeActions(EventLog $row): array
    {
        $actions = [];
        $actions[] = [
            'action' => 'open_detail',
            'label' => 'Open security event detail',
            'route' => null,
            'disabled' => false,
            'reason' => null,
        ];

        $relatedRoute = $this->resolveRelatedRoute((string) $row->entity_type, (string) $row->entity_id);
        if ($relatedRoute !== null) {
            $actions[] = [
                'action' => 'open_related_entity',
                'label' => 'Open related ' . strtolower(class_basename((string) $row->entity_type)),
                'route' => $relatedRoute,
                'disabled' => false,
                'reason' => null,
            ];
        }

        if ($row->correlation_id !== null) {
            $actions[] = [
                'action' => 'open_related_audit',
                'label' => 'Open related audit record',
                'route' => "/alarms-events/audit-trail?correlation_id={$row->correlation_id}",
                'disabled' => false,
                'reason' => null,
            ];
        }

        $actions[] = [
            'action' => 'mark_reviewed',
            'label' => 'Mark reviewed',
            'route' => null,
            'disabled' => true,
            'reason' => 'Review workflow not yet wired (V1 forward-contract).',
        ];

        return $actions;
    }

    protected function resolveRelatedRoute(string $entityType, string $entityId): ?string
    {
        if ($entityType === '' || $entityId === '') return null;
        $short = class_basename($entityType);
        return match ($short) {
            'User' => "/administration/users/{$entityId}",
            'Driver' => "/master-data/drivers/{$entityId}",
            'ChipCard' => "/master-data/chip-cards/{$entityId}",
            'Tan' => "/master-data/tans/{$entityId}",
            default => null,
        };
    }

    /* ============================================================
     * Summary bar
     * ============================================================ */

    /**
     * @return array{
     *   totalToday:int, criticalToday:int, highToday:int,
     *   failedAuthsToday:int, deniedAccessToday:int,
     *   availableFilterValues:array<string,array<int,string>>
     * }
     */
    public function buildSummary(): array
    {
        $todayStart = Carbon::today();
        $todayQ = fn () => EventLog::query()
            ->where('event_category', EventCategory::SECURITY)
            ->where('occurred_at', '>=', $todayStart);

        $totalToday = $todayQ()->count();

        $rows = $todayQ()->get(['event_type', 'severity']);

        $criticalToday = 0;
        $highToday = 0;
        $failedAuthsToday = 0;
        $deniedAccessToday = 0;

        foreach ($rows as $r) {
            $result = SecurityResult::deriveFrom($r->event_type);
            $risk = $this->deriveRiskLevel($r, $result);
            if ($risk === SecurityRiskLevel::CRITICAL) $criticalToday++;
            if ($risk === SecurityRiskLevel::HIGH) $highToday++;

            $cat = SecurityEventCategory::deriveFrom($r->event_type);
            if ($cat === SecurityEventCategory::AUTHENTICATION
                && in_array($result, [SecurityResult::FAILED, SecurityResult::LOCKED], true)
            ) {
                $failedAuthsToday++;
            }
            if ($cat === SecurityEventCategory::AUTHORIZATION
                && $result === SecurityResult::DENIED
            ) {
                $deniedAccessToday++;
            }
        }

        return [
            'totalToday' => $totalToday,
            'criticalToday' => $criticalToday,
            'highToday' => $highToday,
            'failedAuthsToday' => $failedAuthsToday,
            'deniedAccessToday' => $deniedAccessToday,
            'availableFilterValues' => $this->availableFilterValues(),
        ];
    }

    public function availableFilterValues(): array
    {
        $since = Carbon::now()->subDays(7);
        $rows = EventLog::query()
            ->where('event_category', EventCategory::SECURITY)
            ->where('occurred_at', '>=', $since)
            ->get(['event_type', 'severity']);

        $eventTypes = $rows->pluck('event_type')->filter()->unique()->values()->take(30)->all();
        $categories = $rows
            ->map(fn ($r) => SecurityEventCategory::deriveFrom($r->event_type))
            ->filter()->unique()->values()->all();
        $results = $rows
            ->map(fn ($r) => SecurityResult::deriveFrom($r->event_type))
            ->filter()->unique()->values()->all();
        $risks = $rows
            ->map(fn ($r) => $this->deriveRiskLevel($r, SecurityResult::deriveFrom($r->event_type)))
            ->filter()->unique()->values()->all();

        return [
            'categories' => $categories,
            'results' => $results,
            'risks' => $risks,
            'eventTypes' => $eventTypes,
            'reviewStatuses' => SecurityReviewStatus::all(),
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
                $q->where('event_type', 'like', $search)
                    ->orWhere('entity_type', 'like', $search)
                    ->orWhere('entity_id', 'like', $search)
                    ->orWhere('message', 'like', $search)
                    ->orWhere('actor_name', 'like', $search)
                    ->orWhere('correlation_id', 'like', $search);
            });
        }

        if (! empty($filters['security_category']) && in_array($filters['security_category'], SecurityEventCategory::all(), true)) {
            $prefixes = $this->prefixesForCategory($filters['security_category']);
            if (! empty($prefixes)) {
                $query->where(function (Builder $q) use ($prefixes) {
                    foreach ($prefixes as $p) {
                        $q->orWhere('event_type', 'like', $p . '.%');
                    }
                });
            }
        }

        if (! empty($filters['result']) && in_array($filters['result'], SecurityResult::all(), true)) {
            $patterns = $this->tailPatternsForResult($filters['result']);
            if (! empty($patterns)) {
                $query->where(function (Builder $q) use ($patterns) {
                    foreach ($patterns as $p) {
                        $q->orWhere('event_type', 'like', '%' . $p . '%');
                    }
                });
            }
        }

        if (! empty($filters['risk']) && in_array($filters['risk'], SecurityRiskLevel::all(), true)) {
            // Risk is derived from severity + result. Push down via the
            // matching severity set.
            $severities = match ($filters['risk']) {
                SecurityRiskLevel::CRITICAL => [EventSeverity::CRITICAL],
                SecurityRiskLevel::HIGH => [EventSeverity::DANGER],
                SecurityRiskLevel::MEDIUM => [EventSeverity::WARNING],
                SecurityRiskLevel::LOW => [EventSeverity::INFO, EventSeverity::WARNING],
                SecurityRiskLevel::INFO => [EventSeverity::INFO],
                default => [],
            };
            if (! empty($severities)) {
                $query->whereIn('severity', $severities);
            }
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['correlation_id'])) {
            $query->where('correlation_id', $filters['correlation_id']);
        }

        if (! empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', $filters['actor_user_id']);
        }

        if (! empty($filters['date_from'])) {
            try { $query->where('occurred_at', '>=', Carbon::parse($filters['date_from'])); } catch (\Throwable) {}
        }
        if (! empty($filters['date_to'])) {
            try { $query->where('occurred_at', '<=', Carbon::parse($filters['date_to'])); } catch (\Throwable) {}
        }

        $timeRange = $filters['time_range'] ?? null;
        if ($timeRange === 'today') {
            $query->where('occurred_at', '>=', Carbon::today());
        } elseif ($timeRange === 'last_24h') {
            $query->where('occurred_at', '>=', Carbon::now()->subDay());
        } elseif ($timeRange === 'last_7d') {
            $query->where('occurred_at', '>=', Carbon::now()->subDays(7));
        }
    }

    protected function prefixesForCategory(string $category): array
    {
        return match ($category) {
            SecurityEventCategory::AUTHENTICATION => ['auth'],
            SecurityEventCategory::AUTHORIZATION => ['permission'],
            SecurityEventCategory::ACCOUNT_LIFECYCLE => ['user'],
            SecurityEventCategory::ROLE_PERMISSION => ['role'],
            SecurityEventCategory::CHIP_TAN_ACCESS => ['auth_medium', 'tan', 'chip_card'],
            SecurityEventCategory::SESSION => ['session'],
            SecurityEventCategory::API_INTEGRATION => ['api', 'service_token', 'integration'],
            default => [],
        };
    }

    protected function tailPatternsForResult(string $result): array
    {
        return match ($result) {
            SecurityResult::SUCCESS => ['success', 'granted', 'logged_in'],
            SecurityResult::FAILED => ['failed', 'failure'],
            SecurityResult::DENIED => ['denied'],
            SecurityResult::LOCKED => ['locked'],
            SecurityResult::EXPIRED => ['expired'],
            SecurityResult::BLOCKED => ['blocked'],
            default => [],
        };
    }
}
