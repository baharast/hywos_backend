<?php

namespace App\Services\HardwareDevice;

use App\Enums\GateTerminalSessionState;
use App\Enums\GateTerminalTouchpoint;
use App\Enums\HardwareDeviceHealth;
use App\Enums\HardwareDeviceSubsystem;
use App\Enums\HardwareDeviceType;
use App\Enums\HardwarePhysicalLocation;
use App\Enums\IdentificationResultStatus;
use App\Enums\ReaderKind;
use App\Enums\SupportedReaderMedia;
use App\Models\HardwareDevice;
use App\Models\TerminalSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Source-facing read for V1.4 §7 Card Readers / Trailer-Chip Readers
 * internal tab.
 *
 * Composite read over:
 *   - `hardware_devices` (Track A registry — reader health + service mode)
 *   - `terminal_sessions` (Gate & Terminal Monitor V2.3 — best-effort
 *     "last identification event" for gate/driver-terminal readers)
 *
 * No tables added. Filling-bay readers and trailer-registration readers
 * don't sit on a V2.3 touchpoint by design; their lastIdentificationEvent
 * stays UNKNOWN until a dedicated identification-events table lands.
 *
 * Per V1.4 §7's action-menu rule and §10's read-only intent, NO write
 * surface exists here. The boundary note rendered by the FE: "This
 * module is diagnostic only; unsafe controls are not available."
 */
class CardReaderTabService
{
    public function __construct(
        protected HardwareDeviceService $hardwareDevices,
    ) {}

    /* ============================================================
     * List query
     * ============================================================ */

    public function listForTab(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = HardwareDevice::query()
            ->whereIn('device_type', [
                HardwareDeviceType::RFID_READER,
                HardwareDeviceType::TRAILER_CHIP_READER,
            ]);

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
     * Compose the response shape for one reader.
     *
     * @return array<string,mixed>
     */
    public function enrichRow(HardwareDevice $device): array
    {
        $kind = ReaderKind::deriveFrom(
            $device->device_type ?? '',
            $device->subsystem ?? '',
            $device->physical_location ?? '',
        );

        $session = $this->matchLatestSession($device);
        $identification = $this->resolveIdentificationEvent($device, $session);

        $supportedMedia = SupportedReaderMedia::forKind($kind);

        return [
            'kind' => [
                'value' => $kind,
                'label' => ReaderKind::label($kind),
                'tone' => ReaderKind::tone($kind),
            ],
            'supportedMedia' => array_map(fn ($m) => [
                'value' => $m,
                'label' => SupportedReaderMedia::label($m),
            ], $supportedMedia),
            'linkedDevice' => $this->resolveLinkedDevice($device, $kind),
            'linkedProcess' => [
                'value' => ReaderKind::linkedProcess($kind),
                'label' => ReaderKind::linkedProcessLabel($kind),
            ],
            'lastIdentificationEvent' => $identification,
            'safeRoute' => $this->resolveSafeRoute($device, $kind, $identification, $session),
        ];
    }

    /**
     * Map a reader to its V2.3 touchpoint (when one exists) and pick the
     * latest terminal_session there. Filling-bay readers and trailer-chip
     * readers return null — they don't sit on a V2.3 touchpoint.
     */
    public function matchLatestSession(HardwareDevice $device): ?TerminalSession
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

    protected function touchpointFor(HardwareDevice $device): ?string
    {
        if ($device->device_type !== HardwareDeviceType::RFID_READER) {
            return null;
        }
        return match ($device->physical_location) {
            HardwarePhysicalLocation::ENTRY_GATE => GateTerminalTouchpoint::ENTRY_GATE,
            HardwarePhysicalLocation::EXIT_GATE => GateTerminalTouchpoint::EXIT_GATE,
            HardwarePhysicalLocation::DRIVER_TERMINAL => GateTerminalTouchpoint::DRIVER_TERMINAL,
            default => null,
        };
    }

    /**
     * V1.4 §7 "Last Identification Event" column.
     *
     * For gate/driver-terminal readers, derived from the touchpoint's
     * latest terminal_sessions.session_state. For filling-bay readers and
     * trailer-chip readers, falls back to UNKNOWN — the dedicated
     * identification-events table is TBC.
     *
     * @return array{
     *   result:array{value:string,label:string,tone:string},
     *   at:string|null,
     *   driverName:string|null,
     *   driverCode:string|null,
     *   linkedVisitNo:string|null,
     *   linkedOrderNo:string|null,
     *   source:string
     * }
     */
    public function resolveIdentificationEvent(HardwareDevice $device, ?TerminalSession $session): array
    {
        if ($session !== null) {
            $value = IdentificationResultStatus::fromSessionState($session->session_state);
            return [
                'result' => [
                    'value' => $value,
                    'label' => IdentificationResultStatus::label($value),
                    'tone' => IdentificationResultStatus::tone($value),
                ],
                'at' => $session->last_activity_at?->toIso8601String(),
                'driverName' => $session->driver_name,
                'driverCode' => $session->driver_code,
                'linkedVisitNo' => $session->visit_no,
                'linkedOrderNo' => $session->order_no,
                'source' => 'terminal_session',
            ];
        }

        // No session source — surface as UNKNOWN with the device's own
        // last_event_at as a coarse timestamp.
        $value = IdentificationResultStatus::UNKNOWN;
        return [
            'result' => [
                'value' => $value,
                'label' => IdentificationResultStatus::label($value),
                'tone' => IdentificationResultStatus::tone($value),
            ],
            'at' => $device->last_event_at?->toIso8601String(),
            'driverName' => null,
            'driverCode' => null,
            'linkedVisitNo' => null,
            'linkedOrderNo' => null,
            'source' => 'device_heartbeat',
        ];
    }

    /**
     * V1.4 §7 "Linked Device" column: the gate / terminal / bay panel
     * this reader belongs to. Composed from kind + location.
     *
     * @return array{label:string,route:string|null}
     */
    public function resolveLinkedDevice(HardwareDevice $device, string $kind): array
    {
        return match ($kind) {
            ReaderKind::ENTRY_GATE_PERSONAL => [
                'label' => 'Entry Gate',
                'route' => '/operations/gate-terminal-monitor',
            ],
            ReaderKind::EXIT_ID_READER => [
                'label' => 'Exit Gate',
                'route' => '/operations/gate-terminal-monitor',
            ],
            ReaderKind::DRIVER_TERMINAL_READER => [
                'label' => 'Driver Terminal',
                'route' => '/operations/gate-terminal-monitor',
            ],
            ReaderKind::FILLING_BAY_READER => [
                'label' => HardwarePhysicalLocation::label($device->physical_location ?? ''),
                'route' => '/operations/loading-control',
            ],
            ReaderKind::TRAILER_REGISTRATION_READER => [
                'label' => 'Trailer Registration',
                'route' => null,
            ],
            default => [
                'label' => 'Unlinked',
                'route' => null,
            ],
        };
    }

    /**
     * V1.4 §7 "Safe Route / Action Needed" column. Composed server-side
     * to avoid leaking raw blocker text. Spec rule: open identification
     * event, route to clarification, open affected visit/order/device;
     * NO force-match or force-success.
     *
     * @return array{severity:string,label:string,action:string|null,route:string|null}
     */
    public function resolveSafeRoute(
        HardwareDevice $device,
        string $kind,
        array $identification,
        ?TerminalSession $session,
    ): array {
        if ($device->service_mode) {
            return [
                'severity' => 'info',
                'label' => 'Reader in service mode',
                'action' => 'Open device detail (registry)',
                'route' => "/system-devices/hardware-devices/{$device->id}",
            ];
        }

        if (HardwareDeviceHealth::isAbnormal($device->health ?? '')) {
            return [
                'severity' => 'danger',
                'label' => 'Reader fault — operator review required',
                'action' => 'Open device detail (registry)',
                'route' => "/system-devices/hardware-devices/{$device->id}",
            ];
        }

        $resultValue = $identification['result']['value'] ?? IdentificationResultStatus::UNKNOWN;

        if ($resultValue === IdentificationResultStatus::DENIED) {
            return [
                'severity' => 'warning',
                'label' => 'Last identification denied — open clarification flow',
                'action' => 'Open Clarification Cases',
                'route' => '/operations/clarification-cases?source=identification_event',
            ];
        }

        if ($resultValue === IdentificationResultStatus::MULTIPLE_MATCHES) {
            return [
                'severity' => 'warning',
                'label' => 'Multiple driver matches — operator review needed',
                'action' => $session === null
                    ? null
                    : 'Open Gate & Terminal Monitor',
                'route' => $session === null
                    ? null
                    : "/operations/gate-terminal-monitor/sessions/{$session->id}",
            ];
        }

        if ($resultValue === IdentificationResultStatus::READER_ERROR) {
            return [
                'severity' => 'danger',
                'label' => 'Reader error during last identification',
                'action' => 'Open device detail (registry)',
                'route' => "/system-devices/hardware-devices/{$device->id}",
            ];
        }

        if ($resultValue === IdentificationResultStatus::ACCEPTED && $session !== null) {
            return [
                'severity' => 'neutral',
                'label' => 'Last identification accepted — no action',
                'action' => 'Open Gate & Terminal Monitor',
                'route' => "/operations/gate-terminal-monitor/sessions/{$session->id}",
            ];
        }

        return [
            'severity' => 'neutral',
            'label' => 'Reader healthy — no recent action needed',
            'action' => null,
            'route' => null,
        ];
    }

    /* ============================================================
     * Summary bar
     * ============================================================ */

    /**
     * @return array{
     *   totalReaders:int, abnormalReaders:int,
     *   inServiceMode:int, recentDenials:int,
     *   availableFilterValues:array<string,array<int,string>>
     * }
     */
    public function buildSummary(): array
    {
        $base = HardwareDevice::query()
            ->whereIn('device_type', [
                HardwareDeviceType::RFID_READER,
                HardwareDeviceType::TRAILER_CHIP_READER,
            ]);

        $operational = (clone $base)->where(function (Builder $q) {
            $q->whereNull('data_status')->orWhere('data_status', '!=', 'tbc_engineering');
        });

        $totalReaders = (clone $operational)->count();

        $abnormalReaders = (clone $operational)
            ->whereIn('health', [
                HardwareDeviceHealth::WARNING,
                HardwareDeviceHealth::ALARM,
                HardwareDeviceHealth::FAULT,
                HardwareDeviceHealth::OFFLINE,
            ])
            ->count();

        $inServiceMode = (clone $operational)->where('service_mode', true)->count();

        // Recent denials across the V2.3 touchpoints this tab maps to.
        $recentDenials = TerminalSession::query()
            ->where('session_state', GateTerminalSessionState::DENIED)
            ->whereIn('touchpoint', [
                GateTerminalTouchpoint::ENTRY_GATE,
                GateTerminalTouchpoint::DRIVER_TERMINAL,
                GateTerminalTouchpoint::EXIT_GATE,
            ])
            ->count();

        return [
            'totalReaders' => $totalReaders,
            'abnormalReaders' => $abnormalReaders,
            'inServiceMode' => $inServiceMode,
            'recentDenials' => $recentDenials,
            'availableFilterValues' => $this->availableFilterValues(),
        ];
    }

    public function availableFilterValues(): array
    {
        $base = HardwareDevice::query()
            ->whereIn('device_type', [
                HardwareDeviceType::RFID_READER,
                HardwareDeviceType::TRAILER_CHIP_READER,
            ])
            ->where(function (Builder $q) {
                $q->whereNull('data_status')->orWhere('data_status', '!=', 'tbc_engineering');
            });

        $rows = (clone $base)->get(['device_type', 'subsystem', 'physical_location', 'health']);

        $healths = $rows->pluck('health')->filter()->unique()->values()->all();
        $locations = $rows->pluck('physical_location')->filter()->unique()->values()->all();
        $deviceTypes = $rows->pluck('device_type')->filter()->unique()->values()->all();

        $kinds = $rows
            ->map(fn ($d) => ReaderKind::deriveFrom(
                $d->device_type ?? '',
                $d->subsystem ?? '',
                $d->physical_location ?? '',
            ))
            ->filter(fn ($k) => in_array($k, ReaderKind::inScopeKinds(), true))
            ->unique()
            ->values()
            ->all();

        return [
            'healths' => $healths,
            'locations' => $locations,
            'deviceTypes' => $deviceTypes,
            'readerKinds' => $kinds,
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

        if (! empty($filters['device_type']) && in_array($filters['device_type'], [
            HardwareDeviceType::RFID_READER,
            HardwareDeviceType::TRAILER_CHIP_READER,
        ], true)) {
            $query->where('device_type', $filters['device_type']);
        }

        if (! empty($filters['physical_location']) && in_array($filters['physical_location'], HardwarePhysicalLocation::all(), true)) {
            $query->where('physical_location', $filters['physical_location']);
        }

        if (! empty($filters['reader_kind']) && in_array($filters['reader_kind'], ReaderKind::inScopeKinds(), true)) {
            $this->narrowToKind($query, $filters['reader_kind']);
        }

        if (! empty($filters['supported_medium']) && in_array($filters['supported_medium'], SupportedReaderMedia::all(), true)) {
            // Restrict to reader kinds that support this medium.
            $matchingKinds = array_filter(
                ReaderKind::inScopeKinds(),
                fn ($k) => in_array($filters['supported_medium'], SupportedReaderMedia::forKind($k), true),
            );
            $query->where(function (Builder $q) use ($matchingKinds) {
                foreach ($matchingKinds as $kind) {
                    $q->orWhere(function (Builder $inner) use ($kind) {
                        $this->narrowToKind($inner, $kind);
                    });
                }
            });
        }

        if (array_key_exists('service_mode', $filters) && $filters['service_mode'] !== null && $filters['service_mode'] !== '') {
            $query->where('service_mode', filter_var($filters['service_mode'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    protected function narrowToKind(Builder $query, string $kind): void
    {
        match ($kind) {
            ReaderKind::ENTRY_GATE_PERSONAL => $query
                ->where('device_type', HardwareDeviceType::RFID_READER)
                ->where('physical_location', HardwarePhysicalLocation::ENTRY_GATE),
            ReaderKind::EXIT_ID_READER => $query
                ->where('device_type', HardwareDeviceType::RFID_READER)
                ->where('physical_location', HardwarePhysicalLocation::EXIT_GATE),
            ReaderKind::DRIVER_TERMINAL_READER => $query
                ->where('device_type', HardwareDeviceType::RFID_READER)
                ->where('physical_location', HardwarePhysicalLocation::DRIVER_TERMINAL),
            ReaderKind::FILLING_BAY_READER => $query
                ->where('device_type', HardwareDeviceType::RFID_READER)
                ->whereIn('physical_location', [
                    HardwarePhysicalLocation::FILLING_BAY_01,
                    HardwarePhysicalLocation::FILLING_BAY_02,
                    HardwarePhysicalLocation::FILLING_BAY_03,
                    HardwarePhysicalLocation::FILLING_BAY_04,
                    HardwarePhysicalLocation::FILLING_BAY_05,
                    HardwarePhysicalLocation::FILLING_BAY_06,
                ]),
            ReaderKind::TRAILER_REGISTRATION_READER => $query
                ->where('device_type', HardwareDeviceType::TRAILER_CHIP_READER),
            default => null,
        };
    }

    /* ============================================================
     * Detail-mode session history
     * ============================================================ */

    /**
     * Last 20 terminal_sessions rows for the reader's touchpoint, newest
     * first. Returns an empty collection for readers that don't sit on
     * a V2.3 touchpoint.
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
