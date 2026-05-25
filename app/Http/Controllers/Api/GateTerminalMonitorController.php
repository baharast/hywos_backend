<?php

namespace App\Http\Controllers\Api;

use App\Enums\GateTerminalSessionState;
use App\Enums\GateTerminalTouchpoint;
use App\Http\Resources\GateTerminalSessionResource;
use App\Http\Resources\GateTerminalTouchpointSummaryResource;
use App\Models\TerminalSession;
use App\Services\ApiResponse;
use Illuminate\Http\Request;

/**
 * Gate & Terminal Monitor — read-only V2.3 controller.
 *
 * Three endpoints:
 *   GET /api/gate-terminal-monitor/touchpoints  — exactly 3 cards
 *   GET /api/gate-terminal-monitor/sessions     — paginated session list
 *   GET /api/gate-terminal-monitor/sessions/{id}
 *
 * Hard rules (V2.3 §§5/6.3/9/11 — do NOT relax):
 *   - touchpoints endpoint always returns 3 cards, one per enum value
 *   - card state is picked per V2.3 §6.3 display priority
 *   - sessions summary collapses zero counts (V2.3 §5)
 *   - needsOperator is backend-set; never inferred
 *   - no POST, no DELETE, no unsafe controls in this slice
 */
class GateTerminalMonitorController extends ApiController
{
    public const SORT_WHITELIST = [
        'last_activity_at',
        'created_at',
        'session_state',
        'touchpoint',
    ];

    /**
     * V2.3 §6 — touchpoint-first board. Always 3 cards.
     */
    public function touchpoints()
    {
        $cards = [];
        foreach (GateTerminalTouchpoint::all() as $touchpoint) {
            $cards[] = $this->buildTouchpointCard($touchpoint);
        }

        $lastUpdated = TerminalSession::query()->max('updated_at');

        return ApiResponse::list(
            GateTerminalTouchpointSummaryResource::collection($cards),
            null,
            null,
            $lastUpdated,
            'Touchpoint board retrieved'
        );
    }

    /**
     * V2.3 §7 + §8 — paginated session list with primary/More filters.
     */
    public function sessions(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = TerminalSession::query();
        $this->applyFilters($query, $request->all());

        $sort = (string) $request->query('sort', '-last_activity_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (! in_array($column, self::SORT_WHITELIST, true)) {
            $column = 'last_activity_at';
            $direction = 'desc';
        }

        $paginator = $query->orderBy($column, $direction)->paginate($perPage);
        $rows = GateTerminalSessionResource::collection($paginator->items());

        $summary = $this->summary();
        $lastUpdated = TerminalSession::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'Terminal sessions retrieved');
    }

    public function show($id)
    {
        $session = TerminalSession::find($id);
        if (! $session) {
            return $this->error('Terminal session not found', 'TERMINAL_SESSION_NOT_FOUND', 404);
        }

        return $this->success(new GateTerminalSessionResource($session), 'Terminal session retrieved');
    }

    // ---------- helpers ----------

    /**
     * Build the touchpoint card payload for one of the 3 fixed touchpoints,
     * applying the V2.3 §6.3 display priority:
     *   1. device_fault                      (open device / reader / gate fault)
     *   2. denied / needs_operator           (driver-side block requiring action)
     *   3. service_mode                      (touchpoint out for service)
     *   4. active                            (current driver session)
     *   5. idle                              (calm state)
     *
     * Within the same priority class we tie-break on `last_activity_at desc`
     * so the freshest item wins.
     *
     * Returns an associative array consumable by GateTerminalTouchpointSummaryResource.
     */
    protected function buildTouchpointCard(string $touchpoint): array
    {
        // Pull every non-idle session at this touchpoint plus the latest idle
        // row (only used when there is no other state to show). Cheap enough
        // because the table is small operationally — at most a handful of
        // open sessions per touchpoint at any moment.
        $sessions = TerminalSession::query()
            ->atTouchpoint($touchpoint)
            ->orderByDesc('last_activity_at')
            ->get();

        $activeCount = $sessions
            ->where('session_state', '!=', GateTerminalSessionState::IDLE)
            ->count();

        // Primary session = highest-priority (lowest displayPriority number),
        // then most-recent within that priority class.
        $primary = $sessions
            ->sortBy(fn (TerminalSession $s) => GateTerminalSessionState::displayPriority($s->session_state))
            ->first();

        $state = $primary
            ? $primary->session_state
            : GateTerminalSessionState::IDLE;

        return [
            'touchpoint' => $touchpoint,
            'state' => $state,
            'activeSessionCount' => $activeCount,
            'primarySession' => $primary,
        ];
    }

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('driver_name', 'like', $search)
                    ->orWhere('driver_code', 'like', $search)
                    ->orWhere('visit_no', 'like', $search)
                    ->orWhere('order_no', 'like', $search)
                    ->orWhere('trailer_label', 'like', $search)
                    ->orWhere('device_label', 'like', $search)
                    ->orWhere('session_no', 'like', $search);
            });
        }

        if (! empty($filters['touchpoint'])) {
            $query->where('touchpoint', $filters['touchpoint']);
        }
        if (! empty($filters['session_state'])) {
            $query->where('session_state', $filters['session_state']);
        }
        if (! empty($filters['current_screen'])) {
            $query->where('current_screen', $filters['current_screen']);
        }
        if (! empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }
        if (! empty($filters['plant_visit_id'])) {
            $query->where('plant_visit_id', $filters['plant_visit_id']);
        }
        if (! empty($filters['device_health'])) {
            $query->where('device_health', $filters['device_health']);
        }
        if (! empty($filters['last_activity_from'])) {
            $query->where('last_activity_at', '>=', $filters['last_activity_from']);
        }
        if (! empty($filters['last_activity_to'])) {
            $query->where('last_activity_at', '<=', $filters['last_activity_to']);
        }
    }

    /**
     * V2.3 §5 — emit only non-zero issue counters. If everything is zero,
     * collapse to the calm `{activeSessions:0, message:...}` shape so the
     * frontend renders "No active gate or terminal issues" instead of a
     * row of zeros.
     */
    protected function summary(): array
    {
        $base = TerminalSession::query();

        $entryDenied = (clone $base)
            ->where('touchpoint', GateTerminalTouchpoint::ENTRY_GATE)
            ->where('session_state', GateTerminalSessionState::DENIED)
            ->count();

        $exitBlocked = (clone $base)
            ->where('touchpoint', GateTerminalTouchpoint::EXIT_GATE)
            ->whereIn('session_state', [
                GateTerminalSessionState::DENIED,
                GateTerminalSessionState::NEEDS_OPERATOR,
            ])
            ->count();

        $needsOperator = (clone $base)
            ->where(function ($q) {
                $q->where('needs_operator', true)
                    ->orWhere('session_state', GateTerminalSessionState::NEEDS_OPERATOR);
            })
            ->count();

        $deviceFaults = (clone $base)
            ->where('session_state', GateTerminalSessionState::DEVICE_FAULT)
            ->count();

        $activeSessions = (clone $base)
            ->where('session_state', '!=', GateTerminalSessionState::IDLE)
            ->count();

        // Zero-summary rule (V2.3 §5): never emit zero counters as separate
        // cards. Collapse the whole block when there is nothing to show.
        if ($entryDenied + $exitBlocked + $needsOperator + $deviceFaults + $activeSessions === 0) {
            return [
                'activeSessions' => 0,
                'message' => 'No active gate or terminal issues',
            ];
        }

        $summary = ['activeSessions' => $activeSessions];
        if ($entryDenied > 0) {
            $summary['entryDenied'] = $entryDenied;
        }
        if ($exitBlocked > 0) {
            $summary['exitBlocked'] = $exitBlocked;
        }
        if ($needsOperator > 0) {
            $summary['needsOperator'] = $needsOperator;
        }
        if ($deviceFaults > 0) {
            $summary['deviceFaults'] = $deviceFaults;
        }
        return $summary;
    }
}
