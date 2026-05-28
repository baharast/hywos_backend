<?php

namespace App\Services\InterfaceHealth;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\InterfaceBlockingLevel;
use App\Enums\InterfaceDirection;
use App\Enums\InterfaceFamily;
use App\Enums\InterfaceProtocol;
use App\Enums\InterfaceStatus;
use App\Models\InterfaceHealth;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * V1.4 §9 + §10. Two responsibilities:
 *   1. Build the summary block that the list endpoint surfaces beneath
 *      the table (5 counters + per-filter facet values).
 *   2. Stamp + audit a manual retry request. The demo backend doesn't
 *      actually re-trigger the interface — it records the request, so
 *      audit shows "support tried to retry on this date" without
 *      pretending we have a connector orchestrator in scope.
 */
class InterfaceHealthService
{
    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events,
    ) {}

    /**
     * Stamp `last_retry_at` + `last_retry_by_user_id` and write one
     * audit + one event row. The reason is required (FormRequest) and
     * flows into the audit row's `reason` column.
     */
    public function requestRetry(InterfaceHealth $interface, string $reason): InterfaceHealth
    {
        return DB::transaction(function () use ($interface, $reason) {
            $old = $this->audit->snapshotModel($interface);

            $actor = request()?->user();
            $interface->forceFill([
                'last_retry_at' => now(),
                'last_retry_by_user_id' => $actor?->getKey(),
            ])->save();

            $new = $this->audit->snapshotModel($interface->fresh());

            $this->audit->record(
                $interface,
                AuditAction::INTERFACE_RETRY_REQUESTED,
                $old,
                $new,
                $reason,
                null
            );

            $this->events->record(
                'interface.retry_requested',
                $interface,
                "Manual retry requested for {$interface->exact_interface_id}",
                [
                    'exact_interface_id' => $interface->exact_interface_id,
                    'family' => $interface->family,
                    'previous_status' => $interface->status,
                    'reason' => $reason,
                ],
                EventCategory::SYSTEM,
                EventSeverity::INFO
            );

            return $interface->fresh();
        });
    }

    /**
     * Build the 5-counter summary + available filter facets.
     *
     * Counters use the full (unfiltered) table — V1.4 §9 wants the
     * counters to remain stable as the operator filters the list.
     * Facets are computed from the FILTERED base query so the dropdowns
     * only show values that are actually visible in the current view.
     */
    public function buildSummary(Builder $baseQuery): array
    {
        $criticalDown = InterfaceHealth::query()
            ->whereIn('status', [InterfaceStatus::FAULT, InterfaceStatus::OFFLINE])
            ->where('blocking_level', InterfaceBlockingLevel::CRITICAL)
            ->count();

        $operationalDegraded = InterfaceHealth::query()
            ->whereIn('status', [InterfaceStatus::WARNING, InterfaceStatus::FAULT, InterfaceStatus::OFFLINE])
            ->where('blocking_level', InterfaceBlockingLevel::OPERATIONAL)
            ->count();

        $nonBlockingDown = InterfaceHealth::query()
            ->whereIn('status', [InterfaceStatus::FAULT, InterfaceStatus::OFFLINE])
            ->where('blocking_level', InterfaceBlockingLevel::NON_BLOCKING)
            ->count();

        $totalQueueBacklog = (int) InterfaceHealth::query()->sum('queue_count');
        $totalFailedToday = (int) InterfaceHealth::query()->sum('failed_today');

        // Distinct facets from the FILTERED query so the dropdown only
        // surfaces values that the operator could plausibly pick. Clone
        // the builder so we don't disturb the caller's pagination.
        $forFacets = clone $baseQuery;

        return [
            'criticalDown' => $criticalDown,
            'operationalDegraded' => $operationalDegraded,
            'nonBlockingDown' => $nonBlockingDown,
            'totalQueueBacklog' => $totalQueueBacklog,
            'totalFailedToday' => $totalFailedToday,
            'availableFilterValues' => [
                'families' => $this->facetValues((clone $forFacets), 'family', InterfaceFamily::class),
                'protocols' => $this->facetValues((clone $forFacets), 'protocol', InterfaceProtocol::class),
                'directions' => $this->facetValues((clone $forFacets), 'direction', InterfaceDirection::class),
                'statuses' => $this->facetValues((clone $forFacets), 'status', InterfaceStatus::class),
            ],
        ];
    }

    /**
     * Distinct values for one column, projected through the enum's
     * `label()` helper so the FE can render the dropdown without
     * mapping codes.
     *
     * @param  class-string  $enumClass
     */
    protected function facetValues(Builder $q, string $column, string $enumClass): array
    {
        $values = $q
            ->select($column)
            ->distinct()
            ->pluck($column)
            ->filter()
            ->all();

        return array_map(static fn (string $v) => [
            'value' => $v,
            'label' => $enumClass::label($v),
        ], $values);
    }
}
