<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\VehicleStatus;
use App\Http\Requests\Vehicle\BlockVehicleRequest;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UnblockVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Resources\TractorTrailerCouplingResource;
use App\Http\Resources\TractorVehicleResource;
use App\Models\AuditLog;
use App\Models\EventLog;
use App\Models\TractorTrailerCoupling;
use App\Models\TractorVehicle;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TractorVehicleController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = TractorVehicle::query()->with(['carrier', 'defaultDriver']);

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('license_plate', 'like', $search)
                    ->orWhere('vehicle_code', 'like', $search)
                    ->orWhere('last_driver_name', 'like', $search)
                    ->orWhere('current_visit_no', 'like', $search)
                    ->orWhereHas('carrier', function ($q2) use ($search) {
                        $q2->where('name', 'like', $search);
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('carrier_id')) {
            $query->where('carrier_id', $request->query('carrier_id'));
        }

        if ($request->filled('plate_country')) {
            $query->where('plate_country', $request->query('plate_country'));
        }

        if ($request->filled('vehicle_type')) {
            $query->where('vehicle_type', $request->query('vehicle_type'));
        }

        if ($request->filled('active_visit')) {
            $hasVisit = filter_var($request->query('active_visit'), FILTER_VALIDATE_BOOLEAN);
            if ($hasVisit) {
                $query->whereNotNull('current_visit_id');
            } else {
                $query->whereNull('current_visit_id');
            }
        }

        if ($request->filled('has_open_clarification')) {
            $query->where('has_open_clarification', filter_var($request->query('has_open_clarification'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('sort')) {
            $sort = $request->query('sort');
            $direction = 'asc';
            if (str_starts_with($sort, '-')) {
                $direction = 'desc';
                $sort = ltrim($sort, '-');
            }
            $allowed = ['license_plate', 'vehicle_code', 'status', 'created_at', 'updated_at', 'last_visit_at'];
            if (in_array($sort, $allowed, true)) {
                $query->orderBy($sort, $direction);
            } else {
                $query->orderByDesc('created_at');
            }
        } else {
            $query->orderByDesc('created_at');
        }

        $paginator = $query->paginate($perPage);
        $rows = TractorVehicleResource::collection($paginator->items());

        $summary = [
            'totalVehicles' => TractorVehicle::query()->count(),
            'activeVehicles' => TractorVehicle::query()
                ->where('status', VehicleStatus::ACTIVE)
                ->where('is_active', true)
                ->count(),
            'blockedVehicles' => TractorVehicle::query()
                ->where('status', VehicleStatus::BLOCKED)
                ->count(),
            'needsAttention' => TractorVehicle::query()
                ->where(function ($q) {
                    $q->where('has_open_clarification', true)
                        ->orWhereNull('carrier_id')
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('registration_expiry')
                                ->whereDate('registration_expiry', '<', now());
                        })
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('insurance_expiry')
                                ->whereDate('insurance_expiry', '<', now());
                        });
                })
                ->count(),
        ];

        $lastUpdated = TractorVehicle::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'Vehicles retrieved');
    }

    public function store(StoreVehicleRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $data = $request->validated();

        // vehicle_code is always system-minted: StoreVehicleRequest strips
        // any client-supplied value, so the next monotonic TR-NNN is
        // generated here. The empty() check is a defensive backstop.
        if (empty($data['vehicle_code'])) {
            $data['vehicle_code'] = $this->nextVehicleCode();
        }

        return DB::transaction(function () use ($data, $audit, $events) {
            $vehicle = TractorVehicle::create($data);
            $vehicle->load(['carrier', 'defaultDriver']);

            $audit->recordCreated($vehicle, AuditAction::VEHICLE_CREATED);
            $events->record(
                'vehicle.created',
                $vehicle,
                "Vehicle {$vehicle->license_plate} created",
                null,
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->created(new TractorVehicleResource($vehicle), 'Vehicle created');
        });
    }

    public function show($id)
    {
        $vehicle = TractorVehicle::with(['carrier', 'defaultDriver', 'activeCoupling'])->find($id);
        if (! $vehicle) {
            return $this->error('Vehicle not found', 'VEHICLE_NOT_FOUND', 404);
        }

        return $this->success(new TractorVehicleResource($vehicle), 'Vehicle retrieved');
    }

    public function update(UpdateVehicleRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $vehicle = TractorVehicle::find($id);
        if (! $vehicle) {
            return $this->error('Vehicle not found', 'VEHICLE_NOT_FOUND', 404);
        }

        $data = $request->validated();
        $oldPlate = $vehicle->license_plate;

        return DB::transaction(function () use ($vehicle, $data, $oldPlate, $audit, $events) {
            $old = $audit->snapshotModel($vehicle);

            $vehicle->update($data);
            $vehicle->load(['carrier', 'defaultDriver']);
            $fresh = $audit->snapshotModel($vehicle->fresh());

            $audit->record($vehicle, AuditAction::VEHICLE_UPDATED, $old, $fresh);

            $newPlate = $vehicle->license_plate;
            if ($oldPlate !== $newPlate) {
                // Dedicated audit row so plate-change history is queryable independently.
                $audit->record(
                    $vehicle,
                    AuditAction::VEHICLE_PLATE_CHANGED,
                    ['license_plate' => $oldPlate],
                    ['license_plate' => $newPlate],
                    'License plate updated. Historical operational snapshots remain unchanged.'
                );
                $events->record(
                    'vehicle.plate_changed',
                    $vehicle,
                    "Vehicle plate changed: {$oldPlate} → {$newPlate}",
                    ['old' => $oldPlate, 'new' => $newPlate],
                    EventCategory::OPERATIONS,
                    EventSeverity::WARNING
                );
            } else {
                $events->record(
                    'vehicle.updated',
                    $vehicle,
                    "Vehicle {$vehicle->license_plate} updated",
                    null,
                    EventCategory::OPERATIONS,
                    EventSeverity::INFO
                );
            }

            return $this->success(new TractorVehicleResource($vehicle), 'Vehicle updated');
        });
    }

    public function block(BlockVehicleRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $vehicle = TractorVehicle::find($id);
        if (! $vehicle) {
            return $this->error('Vehicle not found', 'VEHICLE_NOT_FOUND', 404);
        }
        if ($vehicle->status === VehicleStatus::BLOCKED) {
            return $this->error('Vehicle already blocked', 'ALREADY_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($vehicle, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($vehicle);

            $vehicle->update([
                'status' => VehicleStatus::BLOCKED,
                'block_reason' => $reason,
                'blocked_at' => now(),
                // TODO: re-enable when auth lands — set blocked_by_user_id from request()->user().
            ]);

            $audit->record(
                $vehicle,
                AuditAction::VEHICLE_BLOCKED,
                $old,
                $audit->snapshotModel($vehicle->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'vehicle.blocked',
                $vehicle,
                "Vehicle {$vehicle->license_plate} blocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new TractorVehicleResource($vehicle->fresh()->load(['carrier', 'defaultDriver'])), 'Vehicle blocked');
        });
    }

    public function unblock(UnblockVehicleRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $vehicle = TractorVehicle::find($id);
        if (! $vehicle) {
            return $this->error('Vehicle not found', 'VEHICLE_NOT_FOUND', 404);
        }
        if ($vehicle->status !== VehicleStatus::BLOCKED) {
            return $this->error('Vehicle is not blocked', 'NOT_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($vehicle, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($vehicle);

            $vehicle->update([
                'status' => VehicleStatus::ACTIVE,
                'block_reason' => null,
                'blocked_at' => null,
                'blocked_by_user_id' => null,
            ]);

            $audit->record(
                $vehicle,
                AuditAction::VEHICLE_UNBLOCKED,
                $old,
                $audit->snapshotModel($vehicle->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'vehicle.unblocked',
                $vehicle,
                "Vehicle {$vehicle->license_plate} unblocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new TractorVehicleResource($vehicle->fresh()->load(['carrier', 'defaultDriver'])), 'Vehicle unblocked');
        });
    }

    public function couplings(Request $request, $id)
    {
        $vehicle = TractorVehicle::find($id);
        if (! $vehicle) {
            return $this->error('Vehicle not found', 'VEHICLE_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $onlyActive = $request->filled('active') && filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN);

        $query = TractorTrailerCoupling::query()
            ->where('tractor_vehicle_id', $vehicle->id)
            ->orderByDesc('coupled_at');

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        $paginator = $query->paginate($perPage);
        $rows = TractorTrailerCouplingResource::collection($paginator->items());
        $lastUpdated = $query->max('updated_at');

        return ApiResponse::list($rows, $paginator, null, $lastUpdated, 'Couplings retrieved');
    }

    public function plantVisits($id)
    {
        $vehicle = TractorVehicle::find($id);
        if (! $vehicle) {
            return $this->error('Vehicle not found', 'VEHICLE_NOT_FOUND', 404);
        }

        return $this->success(
            ['data' => [], 'note' => 'Plant visits module not yet implemented'],
            'Plant visits placeholder'
        );
    }

    public function clarifications($id)
    {
        $vehicle = TractorVehicle::find($id);
        if (! $vehicle) {
            return $this->error('Vehicle not found', 'VEHICLE_NOT_FOUND', 404);
        }

        return $this->success(
            ['data' => [], 'note' => 'Clarification cases module not yet implemented'],
            'Clarifications placeholder'
        );
    }

    public function eventsAudit(Request $request, $id)
    {
        $vehicle = TractorVehicle::find($id);
        if (! $vehicle) {
            return $this->error('Vehicle not found', 'VEHICLE_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $entityType = $vehicle->getMorphClass();
        $entityId = (string) $vehicle->id;

        $events = EventLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('occurred_at')
            ->limit($perPage)
            ->get();

        $audits = AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->limit($perPage)
            ->get();

        return $this->success([
            'events' => $events,
            'audits' => $audits,
        ], 'Events + audit retrieved');
    }

    public function export(Request $request)
    {
        $visible = $request->input('visible_columns', []);
        $filters = $request->only([
            'search', 'status', 'carrier_id', 'plate_country', 'vehicle_type',
            'active_visit', 'has_open_clarification', 'is_active',
        ]);

        $query = TractorVehicle::query()->with(['carrier', 'defaultDriver']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['carrier_id'])) {
            $query->where('carrier_id', $filters['carrier_id']);
        }
        if (! empty($filters['plate_country'])) {
            $query->where('plate_country', $filters['plate_country']);
        }
        if (! empty($filters['vehicle_type'])) {
            $query->where('vehicle_type', $filters['vehicle_type']);
        }

        $rows = $query->orderBy('license_plate')->get()->map(function ($v) use ($visible) {
            $resource = (new TractorVehicleResource($v))->toArray(request());
            if (empty($visible)) {
                return $resource;
            }
            return array_intersect_key($resource, array_flip($visible));
        });

        return $this->success([
            'data' => $rows,
            'visibleColumns' => $visible,
            'filters' => $filters,
            'format' => 'json',
            'note' => 'CSV/XLSX delivery TBC',
        ], 'Vehicle export prepared');
    }

    /**
     * Next vehicle_code in the `TR-NNN` family (tractor demo range).
     * Service vehicles seeded as `SVC-NNN` are ignored by the prefix
     * scan, so the counter starts after the tractor max and the SVC
     * sequence stays untouched.
     */
    protected function nextVehicleCode(): string
    {
        $prefix = 'TR-';

        $lastNo = TractorVehicle::query()
            ->where('vehicle_code', 'like', $prefix . '%')
            ->selectRaw("MAX(CAST(SUBSTRING(vehicle_code, " . (strlen($prefix) + 1) . ") AS UNSIGNED)) AS max_no")
            ->value('max_no');

        $next = max(200, ((int) ($lastNo ?? 0)) + 1);
        return $prefix . $next;
    }
}
