<?php

namespace App\Http\Controllers\Api;

use App\Enums\HardwareDeviceCriticality;
use App\Enums\HardwareDeviceHealth;
use App\Enums\HardwareDeviceSubsystem;
use App\Enums\HardwareDeviceType;
use App\Enums\HardwarePhysicalLocation;
use App\Http\Requests\HardwareDevice\RestoreFromServiceModeRequest;
use App\Http\Requests\HardwareDevice\SetServiceModeRequest;
use App\Http\Resources\HardwareDeviceDetailResource;
use App\Http\Resources\HardwareDeviceListResource;
use App\Models\AuditLog;
use App\Models\EventLog;
use App\Models\HardwareDevice;
use App\Services\ApiResponse;
use App\Services\HardwareDevice\HardwareDeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Hardware Devices (V1.4) — master registry + Device Registry / Overview
 * tab + 3 safe write actions.
 *
 * No PLC writes / equipment start-stop / gate open / ESD reset / force
 * match per V1.4 §10. The 3 writes here (service-mode set / restore /
 * connection-test) are all permission-controlled, audit-tracked and
 * non-invasive against the device itself.
 */
class HardwareDeviceController extends ApiController
{
    public function __construct(protected HardwareDeviceService $service) {}

    /* ============================================================
     * READ
     * ============================================================ */

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = HardwareDevice::query();
        $this->applyFilters($query, $request->all());

        $sort = (string) $request->query('sort', '');
        $allowed = ['last_event_at', 'last_seen_at', 'asset_tag', 'health', 'criticality'];

        if ($sort !== '') {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');
            if (! in_array($column, $allowed, true)) {
                $column = 'last_event_at';
                $direction = 'desc';
            }
            $query->orderBy($column, $direction);
        } else {
            // V1.4 §9 default: fault/critical first, then operational impact, then oldest unresolved
            $query->orderByRaw($this->service->defaultSortExpression());
        }

        $paginator = $query->paginate($perPage);
        $rows = HardwareDeviceListResource::collection($paginator->items());

        $lastUpdated = HardwareDevice::query()->max('last_event_at');

        return ApiResponse::list(
            $rows,
            $paginator,
            $this->service->buildSummaryBar(),
            $lastUpdated,
            __('hardware.messages.devices_retrieved')
        );
    }

    public function show(string $id)
    {
        $device = HardwareDevice::query()->find($id);
        if (! $device) {
            return $this->error(__('hardware.messages.device_not_found'), 'HARDWARE_DEVICE_NOT_FOUND', 404);
        }

        $auditRows = AuditLog::query()
            ->where('entity_type', $device->getMorphClass())
            ->where('entity_id', $device->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'actorUserId' => $a->actor_user_id,
                'actorName' => $a->actor_name,
                'reason' => $a->reason,
                'createdAt' => $a->created_at?->toIso8601String(),
            ])
            ->all();

        $resource = (new HardwareDeviceDetailResource($device))
            ->additional(['auditRows' => $auditRows]);

        return $this->success($resource, __('hardware.messages.device_retrieved'));
    }

    public function events(Request $request, string $id)
    {
        $device = HardwareDevice::query()->find($id);
        if (! $device) {
            return $this->error(__('hardware.messages.device_not_found'), 'HARDWARE_DEVICE_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 20);
        $paginator = EventLog::query()
            ->where('entity_type', $device->getMorphClass())
            ->where('entity_id', $device->id)
            ->orderByDesc('occurred_at')
            ->paginate($perPage);

        $rows = collect($paginator->items())->map(fn ($e) => [
            'id' => $e->id,
            'eventType' => $e->event_type,
            'eventCategory' => $e->event_category,
            'severity' => $e->severity,
            'message' => $e->message,
            'details' => $e->details,
            'actorUserId' => $e->actor_user_id,
            'actorName' => $e->actor_name,
            'occurredAt' => $e->occurred_at?->toIso8601String(),
        ])->all();

        return ApiResponse::list($rows, $paginator, [], $paginator->max('occurred_at'), __('hardware.messages.device_events_retrieved'));
    }

    /* ============================================================
     * WRITE — 3 safe lifecycle actions
     * ============================================================ */

    public function setServiceMode(SetServiceModeRequest $request, string $id)
    {
        $device = HardwareDevice::query()->find($id);
        if (! $device) {
            return $this->error(__('hardware.messages.device_not_found'), 'HARDWARE_DEVICE_NOT_FOUND', 404);
        }

        $data = $request->validated();
        $endsAt = isset($data['expected_end_at']) && $data['expected_end_at']
            ? Carbon::parse($data['expected_end_at'])
            : null;

        try {
            $fresh = $this->service->setServiceMode($device, $data['reason'], $endsAt);
        } catch (\DomainException $e) {
            if ($e->getMessage() === 'ALREADY_IN_SERVICE_MODE') {
                return $this->error(
                    __('hardware.messages.already_in_service_mode'),
                    'ALREADY_IN_SERVICE_MODE',
                    409
                );
            }
            throw $e;
        }

        return $this->success(
            new HardwareDeviceDetailResource($fresh),
            __('hardware.messages.service_mode_set')
        );
    }

    public function restoreFromServiceMode(RestoreFromServiceModeRequest $request, string $id)
    {
        $device = HardwareDevice::query()->find($id);
        if (! $device) {
            return $this->error(__('hardware.messages.device_not_found'), 'HARDWARE_DEVICE_NOT_FOUND', 404);
        }

        $data = $request->validated();

        try {
            $fresh = $this->service->clearServiceMode($device, $data['reason']);
        } catch (\DomainException $e) {
            if ($e->getMessage() === 'NOT_IN_SERVICE_MODE') {
                return $this->error(
                    __('hardware.messages.not_in_service_mode'),
                    'NOT_IN_SERVICE_MODE',
                    409
                );
            }
            throw $e;
        }

        return $this->success(
            new HardwareDeviceDetailResource($fresh),
            __('hardware.messages.service_mode_restored')
        );
    }

    public function connectionTest(string $id)
    {
        $device = HardwareDevice::query()->find($id);
        if (! $device) {
            return $this->error(__('hardware.messages.device_not_found'), 'HARDWARE_DEVICE_NOT_FOUND', 404);
        }

        try {
            $fresh = $this->service->runConnectionTest($device);
        } catch (\DomainException $e) {
            if ($e->getMessage() === 'CONNECTION_TEST_NOT_SUPPORTED') {
                return $this->error(
                    __('hardware.messages.test_not_supported'),
                    'CONNECTION_TEST_NOT_SUPPORTED',
                    409
                );
            }
            throw $e;
        }

        return $this->success(
            new HardwareDeviceDetailResource($fresh),
            __('hardware.messages.connection_test_recorded')
        );
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'like', $search)
                    ->orWhere('vendor_tag', 'like', $search)
                    ->orWhere('name', 'like', $search)
                    ->orWhere('last_message', 'like', $search);
            });
        }

        if (! empty($filters['health'])) {
            $values = array_filter(array_map('trim', explode(',', (string) $filters['health'])));
            $values = array_values(array_intersect($values, HardwareDeviceHealth::all()));
            if ($values) {
                $query->whereIn('health', $values);
            }
        }
        if (! empty($filters['device_type']) && in_array($filters['device_type'], HardwareDeviceType::all(), true)) {
            $query->where('device_type', $filters['device_type']);
        }
        if (! empty($filters['subsystem']) && in_array($filters['subsystem'], HardwareDeviceSubsystem::all(), true)) {
            $query->where('subsystem', $filters['subsystem']);
        }
        if (! empty($filters['physical_location']) && in_array($filters['physical_location'], HardwarePhysicalLocation::all(), true)) {
            $query->where('physical_location', $filters['physical_location']);
        }
        if (! empty($filters['criticality']) && in_array($filters['criticality'], HardwareDeviceCriticality::all(), true)) {
            $query->where('criticality', $filters['criticality']);
        }

        if (array_key_exists('service_mode', $filters) && $filters['service_mode'] !== null && $filters['service_mode'] !== '') {
            $query->where('service_mode', filter_var($filters['service_mode'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('blocking_only', $filters) && filter_var($filters['blocking_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_blocking_critical_process', true);
        }

        if (! empty($filters['data_status'])) {
            $query->where('data_status', $filters['data_status']);
        }
    }
}
