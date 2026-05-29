<?php

namespace App\Services\HardwareDevice;

use App\Enums\HardwareDeviceHealth;
use App\Enums\HardwareDeviceSubsystem;
use App\Enums\HardwareDeviceType;
use App\Enums\HardwarePhysicalLocation;
use App\Enums\PlcConnectionState;
use App\Enums\PlcControllerGroup;
use App\Models\HardwareDevice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Source-facing read for V1.4 §8 PLC / OPC UA Health internal tab.
 *
 * Composite read over `hardware_devices` filtered to controller-style
 * device types (PLC, smart_gate_controller, compressor_controller,
 * electrolyzer_controller, analyzer, rio_cabinet, safety_device). No
 * sibling table is joined in V1 — the "Curated Signal Groups" and
 * "Safety Diagnostics" sections per §8 surface as a TBC stub keyed by
 * controller group, until a dedicated plc_signal_samples table lands.
 *
 * Per V1.4 §8 boundary note + §10 read-only intent, NO write surface
 * exists here. The FE renders the fixed boundary note: "Diagnostic
 * only: unsafe controls are not available from FillTrack."
 */
class PlcHealthTabService
{
    /**
     * V1.4 §8 in-scope device types. Note that PLC isn't seeded yet
     * (HMI panels live on the Filling Bay PLCs but the PLC rows
     * themselves aren't emitted) — when those rows arrive the SQL
     * prefilter picks them up automatically.
     */
    public const IN_SCOPE_TYPES = [
        HardwareDeviceType::PLC,
        HardwareDeviceType::SMART_GATE_CONTROLLER,
        HardwareDeviceType::COMPRESSOR_CONTROLLER,
        HardwareDeviceType::ELECTROLYZER_CONTROLLER,
        HardwareDeviceType::ANALYZER,
        HardwareDeviceType::RIO_CABINET,
        HardwareDeviceType::SAFETY_DEVICE,
    ];

    public function __construct(
        protected HardwareDeviceService $hardwareDevices,
    ) {}

    /* ============================================================
     * List query
     * ============================================================ */

    public function listForTab(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = HardwareDevice::query()
            ->whereIn('device_type', self::IN_SCOPE_TYPES);

        $this->applyFilters($query, $filters);

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
     * Compose the response shape for one PLC/controller row.
     *
     * @return array<string,mixed>
     */
    public function enrichRow(HardwareDevice $device): array
    {
        $group = PlcControllerGroup::deriveFrom(
            $device->device_type ?? '',
            $device->subsystem ?? '',
            $device->asset_tag ?? null,
        );

        $connectionState = PlcConnectionState::derive(
            $device->health,
            $device->last_message,
        );

        return [
            'controllerGroup' => [
                'value' => $group,
                'label' => PlcControllerGroup::label($group),
            ],
            'connectionState' => [
                'value' => $connectionState,
                'label' => PlcConnectionState::label($connectionState),
                'tone' => PlcConnectionState::tone($connectionState),
            ],
            'connectionSummary' => $this->resolveConnectionSummary($device),
            'affectedProcess' => $this->resolveAffectedProcess($device),
            'curatedSignalGroups' => array_map(fn ($g) => [
                'value' => $g,
                'label' => ucwords(str_replace('_', ' ', $g)),
            ], PlcControllerGroup::curatedSignalGroups($group)),
            'safetyDiagnostics' => $this->resolveSafetyDiagnostics($device),
            'safeRoute' => $this->resolveSafeRoute($device, $connectionState),
        ];
    }

    /**
     * V1.4 §8 "Connection Summary" detail section. Compact, link-layer
     * facing fields drawn from the registry row.
     *
     * @return array{
     *   protocol:string|null, lastSeenAt:string|null, lastEventAt:string|null,
     *   lastMessage:string|null, reconnectCount:int|null
     * }
     */
    public function resolveConnectionSummary(HardwareDevice $device): array
    {
        return [
            'protocol' => $device->protocol,
            'lastSeenAt' => $device->last_seen_at?->toIso8601String(),
            'lastEventAt' => $device->last_event_at?->toIso8601String(),
            'lastMessage' => $device->last_message,
            // Reconnect count is TBC — surfaced as null until a dedicated
            // heartbeat table lands.
            'reconnectCount' => null,
        ];
    }

    /**
     * V1.4 §8 "Affected Process" detail section. Composed from the
     * registry's affected_process / affected_process_label hint.
     *
     * @return array{label:string,description:string|null}
     */
    public function resolveAffectedProcess(HardwareDevice $device): array
    {
        return [
            'label' => $device->affected_process_label
                ?? $this->defaultProcessLabel($device),
            'description' => $device->affected_process,
        ];
    }

    protected function defaultProcessLabel(HardwareDevice $device): string
    {
        return match ($device->subsystem) {
            HardwareDeviceSubsystem::FILLING => 'Loading release / fill control',
            HardwareDeviceSubsystem::COMPRESSOR => 'Hydrogen compression chain',
            HardwareDeviceSubsystem::ELECTROLYZER => 'Hydrogen production',
            HardwareDeviceSubsystem::ANALYSIS => 'Quality analysis',
            HardwareDeviceSubsystem::GATE => 'Gate authorization',
            HardwareDeviceSubsystem::SAFETY => 'Safety interlocks',
            default => 'Plant control plane',
        };
    }

    /**
     * V1.4 §8 "Safety Diagnostics" detail section — V1 stub. Spec rule:
     * "Read-only ESD/gas/fire/safety rearm data. No bypass or reset
     * controls in MVP." We surface a typed placeholder so the FE knows
     * the section is intentionally empty for now (rather than a missing
     * field).
     *
     * @return array{available:bool,note:string,signals:array}
     */
    public function resolveSafetyDiagnostics(HardwareDevice $device): array
    {
        return [
            'available' => false,
            'note' => 'Safety diagnostics feed not yet wired. Use the on-site safety HMI.',
            'signals' => [],
        ];
    }

    /**
     * V1.4 §8 "Safe Route / Action Needed" — composed server-side. Spec
     * rule: NO bypass, NO reset, NO force from this surface. Route is
     * always read-only deep-link.
     *
     * @return array{severity:string,label:string,action:string|null,route:string|null}
     */
    public function resolveSafeRoute(HardwareDevice $device, string $connectionState): array
    {
        if ($device->service_mode) {
            return [
                'severity' => 'info',
                'label' => 'Controller in service mode',
                'action' => 'Open device detail (registry)',
                'route' => "/system-devices/hardware-devices/{$device->id}",
            ];
        }

        if ($connectionState === PlcConnectionState::DISCONNECTED) {
            return [
                'severity' => 'danger',
                'label' => 'Controller disconnected — link-layer review required',
                'action' => 'Open device detail (registry)',
                'route' => "/system-devices/hardware-devices/{$device->id}",
            ];
        }

        if ($connectionState === PlcConnectionState::CERTIFICATE_ERROR) {
            return [
                'severity' => 'danger',
                'label' => 'OPC UA certificate error — IT/Support required',
                'action' => 'Open Interface Health',
                'route' => '/system-devices/interface-health?family=plc_fieldbus',
            ];
        }

        if ($connectionState === PlcConnectionState::TIMEOUT) {
            return [
                'severity' => 'danger',
                'label' => 'Read timeout — endpoint may need network triage',
                'action' => 'Open Interface Health',
                'route' => '/system-devices/interface-health?family=plc_fieldbus',
            ];
        }

        if ($connectionState === PlcConnectionState::DEGRADED) {
            return [
                'severity' => 'warning',
                'label' => 'Controller degraded — monitor and escalate if persists',
                'action' => 'Open device detail (registry)',
                'route' => "/system-devices/hardware-devices/{$device->id}",
            ];
        }

        return [
            'severity' => 'neutral',
            'label' => 'Controller connected — no action',
            'action' => null,
            'route' => null,
        ];
    }

    /* ============================================================
     * Summary bar
     * ============================================================ */

    /**
     * @return array{
     *   totalEndpoints:int, abnormalEndpoints:int,
     *   disconnectedEndpoints:int, inServiceMode:int,
     *   availableFilterValues:array<string,array<int,string>>
     * }
     */
    public function buildSummary(): array
    {
        $base = HardwareDevice::query()
            ->whereIn('device_type', self::IN_SCOPE_TYPES);

        $operational = (clone $base)->where(function (Builder $q) {
            $q->whereNull('data_status')->orWhere('data_status', '!=', 'tbc_engineering');
        });

        $totalEndpoints = (clone $operational)->count();

        $abnormalEndpoints = (clone $operational)
            ->whereIn('health', [
                HardwareDeviceHealth::WARNING,
                HardwareDeviceHealth::ALARM,
                HardwareDeviceHealth::FAULT,
                HardwareDeviceHealth::OFFLINE,
            ])
            ->count();

        $disconnectedEndpoints = (clone $operational)
            ->where('health', HardwareDeviceHealth::OFFLINE)
            ->count();

        $inServiceMode = (clone $operational)->where('service_mode', true)->count();

        return [
            'totalEndpoints' => $totalEndpoints,
            'abnormalEndpoints' => $abnormalEndpoints,
            'disconnectedEndpoints' => $disconnectedEndpoints,
            'inServiceMode' => $inServiceMode,
            'availableFilterValues' => $this->availableFilterValues(),
        ];
    }

    /**
     * V1.4 §3 + §11: dropdown values come from the current tab dataset
     * only (TBC rows excluded). Protocols flagged TBD/TBC stay out of
     * the dropdown — handled by `protocol IS NOT NULL`.
     */
    public function availableFilterValues(): array
    {
        $base = HardwareDevice::query()
            ->whereIn('device_type', self::IN_SCOPE_TYPES)
            ->where(function (Builder $q) {
                $q->whereNull('data_status')->orWhere('data_status', '!=', 'tbc_engineering');
            });

        $rows = (clone $base)->get([
            'device_type', 'subsystem', 'physical_location',
            'health', 'protocol', 'asset_tag',
        ]);

        $healths = $rows->pluck('health')->filter()->unique()->values()->all();
        $locations = $rows->pluck('physical_location')->filter()->unique()->values()->all();
        $protocols = $rows->pluck('protocol')->filter()->unique()->values()->all();

        $controllerGroups = $rows
            ->map(fn ($d) => PlcControllerGroup::deriveFrom(
                $d->device_type ?? '',
                $d->subsystem ?? '',
                $d->asset_tag ?? null,
            ))
            ->filter(fn ($g) => in_array($g, PlcControllerGroup::inScopeGroups(), true))
            ->unique()
            ->values()
            ->all();

        return [
            'healths' => $healths,
            'locations' => $locations,
            'protocols' => $protocols,
            'controllerGroups' => $controllerGroups,
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
                $q->where('asset_tag', 'like', $search)
                    ->orWhere('vendor_tag', 'like', $search)
                    ->orWhere('name', 'like', $search)
                    ->orWhere('last_message', 'like', $search)
                    ->orWhere('affected_process_label', 'like', $search)
                    ->orWhere('protocol', 'like', $search);
            });
        }

        if (! empty($filters['health'])) {
            $values = array_filter(array_map('trim', explode(',', (string) $filters['health'])));
            $values = array_values(array_intersect($values, HardwareDeviceHealth::all()));
            if ($values) {
                $query->whereIn('health', $values);
            }
        }

        if (! empty($filters['controller_group']) && in_array($filters['controller_group'], PlcControllerGroup::inScopeGroups(), true)) {
            $this->narrowToGroup($query, $filters['controller_group']);
        }

        if (! empty($filters['physical_location']) && in_array($filters['physical_location'], HardwarePhysicalLocation::all(), true)) {
            $query->where('physical_location', $filters['physical_location']);
        }

        if (! empty($filters['protocol'])) {
            $query->where('protocol', $filters['protocol']);
        }

        if (! empty($filters['connection_state']) && in_array($filters['connection_state'], PlcConnectionState::all(), true)) {
            // Connection state is derived from health — map back to the
            // smallest health set that yields this state.
            $healthSet = $this->healthsForConnectionState($filters['connection_state']);
            if (! empty($healthSet)) {
                $query->whereIn('health', $healthSet);
            }
        }

        if (array_key_exists('service_mode', $filters) && $filters['service_mode'] !== null && $filters['service_mode'] !== '') {
            $query->where('service_mode', filter_var($filters['service_mode'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    protected function healthsForConnectionState(string $connectionState): array
    {
        return match ($connectionState) {
            PlcConnectionState::CONNECTED => [HardwareDeviceHealth::ONLINE],
            PlcConnectionState::DEGRADED => [
                HardwareDeviceHealth::WARNING,
                HardwareDeviceHealth::ALARM,
                HardwareDeviceHealth::FAULT,
                HardwareDeviceHealth::SERVICE_MODE,
            ],
            PlcConnectionState::DISCONNECTED => [HardwareDeviceHealth::OFFLINE],
            PlcConnectionState::CERTIFICATE_ERROR, PlcConnectionState::TIMEOUT => [
                // Best-effort: any non-online health may carry the
                // message hint. We don't narrow further at the SQL layer.
                HardwareDeviceHealth::WARNING,
                HardwareDeviceHealth::ALARM,
                HardwareDeviceHealth::FAULT,
                HardwareDeviceHealth::OFFLINE,
            ],
            PlcConnectionState::UNKNOWN => [HardwareDeviceHealth::UNKNOWN],
            default => [],
        };
    }

    protected function narrowToGroup(Builder $query, string $group): void
    {
        match ($group) {
            PlcControllerGroup::OVERALL_PLC => $query
                ->where('device_type', HardwareDeviceType::PLC)
                ->where('subsystem', '!=', HardwareDeviceSubsystem::FILLING),
            PlcControllerGroup::MAIN_FILLING_PLC => $query
                ->where('device_type', HardwareDeviceType::PLC)
                ->where('subsystem', HardwareDeviceSubsystem::FILLING),
            PlcControllerGroup::FILLING_BAY_PLC => $query
                ->where('device_type', HardwareDeviceType::PLC)
                ->where('subsystem', HardwareDeviceSubsystem::FILLING)
                ->whereIn('physical_location', [
                    HardwarePhysicalLocation::FILLING_BAY_01,
                    HardwarePhysicalLocation::FILLING_BAY_02,
                    HardwarePhysicalLocation::FILLING_BAY_03,
                    HardwarePhysicalLocation::FILLING_BAY_04,
                    HardwarePhysicalLocation::FILLING_BAY_05,
                    HardwarePhysicalLocation::FILLING_BAY_06,
                ]),
            PlcControllerGroup::ANALYSIS_UNIT => $query
                ->where('device_type', HardwareDeviceType::ANALYZER)
                ->where('subsystem', HardwareDeviceSubsystem::ANALYSIS),
            PlcControllerGroup::COMPRESSOR_AB => $query
                ->where('device_type', HardwareDeviceType::COMPRESSOR_CONTROLLER)
                ->where(function (Builder $q) {
                    $q->where('asset_tag', 'like', '%-A')
                        ->orWhere('asset_tag', 'like', '%-B')
                        ->orWhereNull('asset_tag');
                }),
            PlcControllerGroup::COMPRESSOR_DE => $query
                ->where('device_type', HardwareDeviceType::COMPRESSOR_CONTROLLER)
                ->where(function (Builder $q) {
                    $q->where('asset_tag', 'like', '%-D')
                        ->orWhere('asset_tag', 'like', '%-E');
                }),
            PlcControllerGroup::ELECTROLYZER => $query
                ->where('device_type', HardwareDeviceType::ELECTROLYZER_CONTROLLER),
            PlcControllerGroup::GATE_CONTROLLER => $query
                ->where('device_type', HardwareDeviceType::SMART_GATE_CONTROLLER),
            PlcControllerGroup::REMOTE_IO => $query
                ->where('device_type', HardwareDeviceType::RIO_CABINET),
            default => null,
        };
    }
}
