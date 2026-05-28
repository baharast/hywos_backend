<?php

namespace App\Services\HardwareDevice;

use App\Enums\GateTerminalCurrentScreen;
use App\Enums\GateTerminalSessionState;
use App\Enums\GateTerminalTouchpoint;
use App\Enums\HardwareDeviceHealth;
use App\Enums\HardwareDeviceSubsystem;
use App\Enums\HardwareDeviceType;
use App\Enums\HardwarePhysicalLocation;
use App\Enums\TerminalPanelKind;
use App\Models\HardwareDevice;
use App\Models\TerminalSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Source-facing read for V1.4 §5 Terminals & Panels internal tab.
 *
 * Composite read over:
 *   - `hardware_devices` (Track A registry — device health + service mode)
 *   - `terminal_sessions` (Gate & Terminal Monitor V2.3 — live session)
 *
 * No tables added. The link is a SOFT match on asset_tag → touchpoint
 * (see `matchTerminalRecord`), which means filling-HMI panels and
 * operator clients land here with `currentSession=null` — they don't
 * sit on a V2.3 touchpoint by design.
 *
 * Spec §5 specifically says this tab is the device-readiness view, NOT
 * the Gate & Terminal Monitor's driver-touchpoint view. The two endpoints
 * surface overlapping data through different ergonomics.
 */
class TerminalPanelTabService
{
    public function __construct(
        protected HardwareDeviceService $hardwareDevices,
    ) {}

    /* ============================================================
     * List query
     * ============================================================ */

    /**
     * Returns hardware_devices rows whose derived TerminalPanelKind is
     * one of the 5 in-scope kinds (entry/exit gate, driver terminal,
     * filling HMI, operator client).
     *
     * The "derived kind" filter is applied in two layers:
     *   1. SQL-level prefilter narrows to the 5 kinds' (subsystem,
     *      device_type, physical_location) tuples — keeps the result
     *      set tight at the database.
     *   2. PHP-level final filter (in `applyKindFilter`) handles the
     *      single combinations the SQL prefilter can't express cleanly.
     */
    public function listForTab(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = HardwareDevice::query();
        $this->applyKindPrefilter($query);
        $this->applyFilters($query, $filters);

        // Default sort: hardware abnormality first, then by last activity.
        // Reuses the V1.4 §9 expression from HardwareDeviceService so we
        // don't have two sort ladders for the same data.
        if (empty($filters['sort'])) {
            $query->orderByRaw($this->hardwareDevices->defaultSortExpression());
        } else {
            $sort = (string) $filters['sort'];
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');
            $allowed = ['last_event_at', 'last_seen_at', 'asset_tag', 'health'];
            if (! in_array($column, $allowed, true)) {
                $column = 'last_event_at';
                $direction = 'desc';
            }
            $query->orderBy($column, $direction);
        }

        return $query->paginate($perPage);
    }

    /* ============================================================
     * Row enrichment
     * ============================================================ */

    /**
     * Compose the response shape for one hardware_devices row + its
     * latest live terminal_sessions row (when one exists).
     *
     * @return array<string,mixed>
     */
    public function enrichRow(HardwareDevice $device): array
    {
        $kind = TerminalPanelKind::deriveFrom(
            $device->subsystem ?? '',
            $device->device_type ?? '',
            $device->physical_location ?? '',
        );

        $session = $this->matchTerminalRecord($device);

        $lastActivityAt = $session?->last_activity_at ?? $device->last_event_at;
        $lastActivityIso = $lastActivityAt?->toIso8601String();

        return [
            'kind' => [
                'value' => $kind,
                'label' => TerminalPanelKind::label($kind),
                'tone' => TerminalPanelKind::tone($kind),
            ],
            'currentSession' => $session === null ? null : [
                'id' => $session->id,
                'sessionNo' => $session->session_no,
                'state' => [
                    'value' => $session->session_state,
                    'label' => GateTerminalSessionState::label($session->session_state ?? ''),
                    'tone' => GateTerminalSessionState::tone($session->session_state ?? ''),
                ],
                'driverName' => $session->driver_name,
                'driverCode' => $session->driver_code,
                'needsOperator' => (bool) $session->needs_operator,
            ],
            'currentScreen' => $session?->current_screen === null ? null : [
                'value' => $session->current_screen,
                'label' => GateTerminalCurrentScreen::label($session->current_screen),
            ],
            'linkedVisit' => $session?->plant_visit_id === null ? null : [
                'id' => $session->plant_visit_id,
                'no' => $session->visit_no,
            ],
            'linkedOrder' => $session?->order_id === null ? null : [
                'id' => $session->order_id,
                'no' => $session->order_no,
            ],
            'lastActivityAt' => $lastActivityIso,
            'deviceImpact' => $this->resolveDeviceImpact($device, $session),
        ];
    }

    /**
     * Look up the latest live `terminal_sessions` row that matches this
     * hardware_devices row.
     *
     * Strategy: map asset_tag → V2.3 touchpoint. The 3 V2.3 touchpoints
     * (entry_gate / driver_terminal / exit_gate) cover entry gate, exit
     * gate and driver terminal hardware. Filling HMI panels and operator
     * clients DON'T have a V2.3 touchpoint by design — they return null
     * here and the FE renders an "idle / no live session" state.
     *
     * When multiple sessions match (e.g. several historical idle rows on
     * the entry gate touchpoint), pick the most recent by
     * `last_activity_at DESC`.
     */
    public function matchTerminalRecord(HardwareDevice $device): ?TerminalSession
    {
        $touchpoint = $this->touchpointFor($device);
        if ($touchpoint === null) {
            return null;
        }

        return TerminalSession::query()
            ->where('touchpoint', $touchpoint)
            ->orderByDesc('last_activity_at')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Compose the "Device Impact / Action Needed" cell (V1.4 §5).
     *
     * Replaces the vague "Blocking Reason" the V2.3 dashboard shows. We
     * compose a device-owned narrative + safe route — never leak the raw
     * session blocker text the FE shouldn't be parsing.
     */
    public function resolveDeviceImpact(HardwareDevice $device, ?TerminalSession $session): array
    {
        // Service mode is its own bucket — always announce, never silent.
        if ($device->service_mode) {
            return [
                'severity' => 'info',
                'label' => 'Device in service mode',
                'action' => 'Open device detail (registry)',
                'route' => "/system-devices/hardware-devices/{$device->id}",
            ];
        }

        $kind = TerminalPanelKind::deriveFrom(
            $device->subsystem ?? '',
            $device->device_type ?? '',
            $device->physical_location ?? '',
        );

        // Hardware-level abnormality dominates session-level state.
        if (HardwareDeviceHealth::isAbnormal($device->health ?? '')) {
            return [
                'severity' => 'danger',
                'label' => $this->hardwareImpactLabel($kind, $device),
                'action' => 'Open device detail (registry)',
                'route' => "/system-devices/hardware-devices/{$device->id}",
            ];
        }

        // Live session signals — only when the hardware itself is healthy.
        if ($session !== null) {
            return $this->sessionImpactLabel($session);
        }

        return [
            'severity' => 'neutral',
            'label' => 'No active session — device healthy',
            'action' => null,
            'route' => null,
        ];
    }

    protected function hardwareImpactLabel(string $kind, HardwareDevice $device): string
    {
        $kindLabel = TerminalPanelKind::label($kind);
        $healthLabel = HardwareDeviceHealth::label($device->health ?? '');
        return "{$kindLabel} {$healthLabel} — operator review required";
    }

    protected function sessionImpactLabel(TerminalSession $session): array
    {
        $state = $session->session_state ?? '';
        return match ($state) {
            GateTerminalSessionState::DEVICE_FAULT => [
                'severity' => 'danger',
                'label' => 'Device fault during session',
                'action' => 'Open Gate & Terminal Monitor',
                'route' => "/operations/gate-terminal-monitor/sessions/{$session->id}",
            ],
            GateTerminalSessionState::DENIED => [
                'severity' => 'warning',
                'label' => 'Driver denied at this touchpoint',
                'action' => 'Open Gate & Terminal Monitor',
                'route' => "/operations/gate-terminal-monitor/sessions/{$session->id}",
            ],
            GateTerminalSessionState::NEEDS_OPERATOR => [
                'severity' => 'warning',
                'label' => 'Driver needs operator review',
                'action' => 'Open Gate & Terminal Monitor',
                'route' => "/operations/gate-terminal-monitor/sessions/{$session->id}",
            ],
            GateTerminalSessionState::SERVICE_MODE => [
                'severity' => 'info',
                'label' => 'Touchpoint in service mode',
                'action' => 'Open device detail (registry)',
                'route' => null,
            ],
            GateTerminalSessionState::ACTIVE => [
                'severity' => 'info',
                'label' => 'Active driver session',
                'action' => 'Open Gate & Terminal Monitor',
                'route' => "/operations/gate-terminal-monitor/sessions/{$session->id}",
            ],
            default => [
                'severity' => 'neutral',
                'label' => 'Idle — touchpoint healthy',
                'action' => null,
                'route' => null,
            ],
        };
    }

    /* ============================================================
     * Summary bar
     * ============================================================ */

    /**
     * V1.4 §5 / §4.2 summary block tailored to this tab's scope.
     *
     * @return array{
     *   totalPanels:int, abnormalPanels:int, activeSessions:int,
     *   inServiceMode:int,
     *   availableFilterValues:array{healths:array<int,string>,terminalKinds:array<int,string>,locations:array<int,string>}
     * }
     */
    public function buildSummary(): array
    {
        $base = HardwareDevice::query();
        $this->applyKindPrefilter($base);

        // Restrict to TBC-clean rows for operational counters — same rule
        // the HardwareDeviceService.buildSummaryBar() uses.
        $operational = (clone $base)->where(function (Builder $q) {
            $q->whereNull('data_status')->orWhere('data_status', '!=', 'tbc_engineering');
        });

        $totalPanels = (clone $operational)->count();

        $abnormalPanels = (clone $operational)
            ->whereIn('health', [
                HardwareDeviceHealth::WARNING,
                HardwareDeviceHealth::ALARM,
                HardwareDeviceHealth::FAULT,
                HardwareDeviceHealth::OFFLINE,
            ])
            ->count();

        $inServiceMode = (clone $operational)->where('service_mode', true)->count();

        // Active sessions across the V2.3 touchpoints this tab maps to.
        $activeSessions = TerminalSession::query()
            ->where('session_state', GateTerminalSessionState::ACTIVE)
            ->whereIn('touchpoint', [
                GateTerminalTouchpoint::ENTRY_GATE,
                GateTerminalTouchpoint::DRIVER_TERMINAL,
                GateTerminalTouchpoint::EXIT_GATE,
            ])
            ->count();

        return [
            'totalPanels' => $totalPanels,
            'abnormalPanels' => $abnormalPanels,
            'activeSessions' => $activeSessions,
            'inServiceMode' => $inServiceMode,
            'availableFilterValues' => $this->availableFilterValues(),
        ];
    }

    /**
     * V1.4 §3 + §11 rule: dropdown options come from the current tab
     * dataset only (TBC rows excluded).
     */
    public function availableFilterValues(): array
    {
        $base = HardwareDevice::query();
        $this->applyKindPrefilter($base);
        $base->where(function (Builder $q) {
            $q->whereNull('data_status')->orWhere('data_status', '!=', 'tbc_engineering');
        });

        $rows = (clone $base)->get(['device_type', 'subsystem', 'physical_location', 'health']);

        $healths = $rows->pluck('health')->filter()->unique()->values()->all();
        $locations = $rows->pluck('physical_location')->filter()->unique()->values()->all();

        $kinds = $rows
            ->map(fn ($d) => TerminalPanelKind::deriveFrom(
                $d->subsystem ?? '',
                $d->device_type ?? '',
                $d->physical_location ?? '',
            ))
            ->filter(fn ($k) => in_array($k, TerminalPanelKind::inScopeKinds(), true))
            ->unique()
            ->values()
            ->all();

        return [
            'healths' => $healths,
            'terminalKinds' => $kinds,
            'locations' => $locations,
        ];
    }

    /* ============================================================
     * Filter plumbing
     * ============================================================ */

    /**
     * Narrow the result set to rows whose (subsystem, device_type,
     * physical_location) match one of the 5 in-scope kinds. Done in SQL
     * so pagination math stays accurate.
     */
    protected function applyKindPrefilter(Builder $query): void
    {
        $query->where(function (Builder $q) {
            // entry / exit gate
            $q->orWhere(function (Builder $w) {
                $w->where('subsystem', HardwareDeviceSubsystem::GATE)
                    ->whereIn('physical_location', [
                        HardwarePhysicalLocation::ENTRY_GATE,
                        HardwarePhysicalLocation::EXIT_GATE,
                    ]);
            });
            // driver terminal
            $q->orWhere('subsystem', HardwareDeviceSubsystem::DRIVER_TERMINAL);
            // filling HMI panels
            $q->orWhere(function (Builder $w) {
                $w->where('device_type', HardwareDeviceType::HMI_PANEL)
                    ->where('subsystem', HardwareDeviceSubsystem::FILLING);
            });
            // operator clients
            $q->orWhere(function (Builder $w) {
                $w->where('device_type', HardwareDeviceType::SERVER)
                    ->where('subsystem', HardwareDeviceSubsystem::INTEGRATION)
                    ->where('physical_location', HardwarePhysicalLocation::CONTROL_ROOM);
            });
        });
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('asset_tag', 'like', $search)
                    ->orWhere('vendor_tag', 'like', $search)
                    ->orWhere('name', 'like', $search)
                    ->orWhere('last_message', 'like', $search)
                    ->orWhere('affected_process_label', 'like', $search);
            });
        }

        if (! empty($filters['health'])) {
            $values = array_filter(array_map('trim', explode(',', (string) $filters['health'])));
            $values = array_values(array_intersect($values, HardwareDeviceHealth::all()));
            if ($values) {
                $query->whereIn('health', $values);
            }
        }
        if (! empty($filters['terminal_kind']) && in_array($filters['terminal_kind'], TerminalPanelKind::inScopeKinds(), true)) {
            $this->narrowToKind($query, $filters['terminal_kind']);
        }
        if (! empty($filters['physical_location']) && in_array($filters['physical_location'], HardwarePhysicalLocation::all(), true)) {
            $query->where('physical_location', $filters['physical_location']);
        }
        if (! empty($filters['subsystem'])) {
            $allowed = [
                HardwareDeviceSubsystem::GATE,
                HardwareDeviceSubsystem::DRIVER_TERMINAL,
                HardwareDeviceSubsystem::FILLING,
                HardwareDeviceSubsystem::INTEGRATION,
            ];
            if (in_array($filters['subsystem'], $allowed, true)) {
                $query->where('subsystem', $filters['subsystem']);
            }
        }
        if (array_key_exists('service_mode', $filters) && $filters['service_mode'] !== null && $filters['service_mode'] !== '') {
            $query->where('service_mode', filter_var($filters['service_mode'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    /**
     * Narrow the query further when the FE picks a specific terminal_kind.
     */
    protected function narrowToKind(Builder $query, string $kind): void
    {
        match ($kind) {
            TerminalPanelKind::ENTRY_GATE => $query
                ->where('subsystem', HardwareDeviceSubsystem::GATE)
                ->where('physical_location', HardwarePhysicalLocation::ENTRY_GATE),
            TerminalPanelKind::EXIT_GATE => $query
                ->where('subsystem', HardwareDeviceSubsystem::GATE)
                ->where('physical_location', HardwarePhysicalLocation::EXIT_GATE),
            TerminalPanelKind::DRIVER_TERMINAL => $query
                ->where('subsystem', HardwareDeviceSubsystem::DRIVER_TERMINAL),
            TerminalPanelKind::FILLING_HMI_PANEL => $query
                ->where('device_type', HardwareDeviceType::HMI_PANEL)
                ->where('subsystem', HardwareDeviceSubsystem::FILLING),
            TerminalPanelKind::OPERATOR_CLIENT => $query
                ->where('device_type', HardwareDeviceType::SERVER)
                ->where('subsystem', HardwareDeviceSubsystem::INTEGRATION)
                ->where('physical_location', HardwarePhysicalLocation::CONTROL_ROOM),
            default => null,
        };
    }

    /* ============================================================
     * V2.3 touchpoint mapping
     * ============================================================ */

    /**
     * Map a hardware_devices row to the V2.3 touchpoint it should pull
     * a live session from. Returns null for kinds without a V2.3
     * touchpoint (filling HMI panels, operator clients).
     */
    protected function touchpointFor(HardwareDevice $device): ?string
    {
        if ($device->subsystem === HardwareDeviceSubsystem::GATE) {
            if ($device->physical_location === HardwarePhysicalLocation::ENTRY_GATE) {
                return GateTerminalTouchpoint::ENTRY_GATE;
            }
            if ($device->physical_location === HardwarePhysicalLocation::EXIT_GATE) {
                return GateTerminalTouchpoint::EXIT_GATE;
            }
        }
        if ($device->subsystem === HardwareDeviceSubsystem::DRIVER_TERMINAL) {
            return GateTerminalTouchpoint::DRIVER_TERMINAL;
        }
        return null;
    }

    /* ============================================================
     * Detail-mode session history
     * ============================================================ */

    /**
     * Last 20 terminal_sessions rows for the device's touchpoint, newest
     * first. Used by the detail endpoint.
     *
     * @return Collection<int,TerminalSession>
     */
    public function sessionHistoryFor(HardwareDevice $device): Collection
    {
        $touchpoint = $this->touchpointFor($device);
        if ($touchpoint === null) {
            return collect();
        }

        return TerminalSession::query()
            ->where('touchpoint', $touchpoint)
            ->orderByDesc('last_activity_at')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();
    }
}
