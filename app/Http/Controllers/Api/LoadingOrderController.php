<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\LoadingOrderStatus;
use App\Enums\TrailerStatus;
use App\Exceptions\SapFieldProtectionException;
use App\Http\Requests\LoadingOrder\AssignDriverRequest;
use App\Http\Requests\LoadingOrder\AssignTrailerRequest;
use App\Http\Requests\LoadingOrder\BlockLoadingOrderRequest;
use App\Http\Requests\LoadingOrder\CancelLoadingOrderRequest;
use App\Http\Requests\LoadingOrder\CreateLoadingOrderRequest;
use App\Http\Requests\LoadingOrder\UnassignDriverRequest;
use App\Http\Requests\LoadingOrder\UnassignTrailerRequest;
use App\Http\Requests\LoadingOrder\UnblockLoadingOrderRequest;
use App\Http\Requests\LoadingOrder\UpdateLoadingOrderRequest;
use App\Http\Resources\LoadingOrderResource;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\EventLog;
use App\Models\FreightForwarder;
use App\Models\LoadingOrder;
use App\Models\Trailer;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use App\Services\Sap\SapFieldGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Loading Orders controller — implements api/15-loading-orders.md §3-§7.
 *
 * Architecture rules (do not violate):
 * - NEVER set `status` directly. Mutate anchors (`blocked_at`, `cancelled_at`,
 *   `active_*`). LoadingOrderReadinessService re-derives via the model's
 *   saving hook.
 * - SAP-owned field protection is enforced by SapFieldGuard on PUT.
 * - Execution-lock protection (is_locked_by_execution) blocks driver/trailer/
 *   task_flow/planned_window_* edits.
 * - Every state change writes audit + event in a DB::transaction.
 */
class LoadingOrderController extends ApiController
{
    /**
     * Fields additionally locked once an active plant visit / loading
     * operation is bound. Used by both PUT and assign/unassign endpoints.
     */
    public const EXECUTION_LOCKED_FIELDS = [
        'assigned_driver_id',
        'assigned_trailer_id',
        'task_flow',
        'planned_window_start',
        'planned_window_end',
    ];

    public const SORT_WHITELIST = [
        'order_no',
        'created_at',
        'updated_at',
        'planned_window_start',
        'status',
    ];

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = LoadingOrder::query();
        $this->applyFilters($query, $request->all());

        // Sort whitelist (prefix `-` = desc)
        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (! in_array($column, self::SORT_WHITELIST, true)) {
            $column = 'updated_at';
            $direction = 'desc';
        }

        $paginator = $query->orderBy($column, $direction)->paginate($perPage);
        $rows = LoadingOrderResource::collection($paginator->items());

        $summary = $this->summary();
        $lastUpdated = LoadingOrder::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'Loading orders retrieved');
    }

    public function show($id)
    {
        $order = LoadingOrder::find($id);
        if (! $order) {
            return $this->error('Loading order not found', 'LOADING_ORDER_NOT_FOUND', 404);
        }

        return $this->success(new LoadingOrderResource($order), 'Loading order retrieved');
    }

    public function store(CreateLoadingOrderRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $data = $request->validated();

        // Strip any forbidden fields defensively (Laravel already validates only
        // listed rules but a client could still POST extras that match Eloquent
        // fillable; the request rules don't permit them so they'd be ignored,
        // but explicit > implicit).
        foreach (CreateLoadingOrderRequest::FORBIDDEN_CREATE_FIELDS as $f) {
            unset($data[$f]);
        }

        // Reject create when targeting a blocked / inactive customer.
        $customer = Customer::find($data['customer_id']);
        if (! $customer) {
            return $this->error('Customer not found', 'CUSTOMER_NOT_FOUND', 404);
        }
        if ($customer->status === 'blocked' || $customer->is_active === false) {
            return $this->error(
                'Cannot create loading order for a blocked or inactive customer.',
                'CUSTOMER_BLOCKED',
                409,
                ['customer_id' => $customer->id, 'status' => $customer->status]
            );
        }

        // Denormalise customer/carrier names for list-view speed (spec §8).
        $data['customer_name'] = $customer->name;
        if (! empty($data['carrier_id'])) {
            $carrier = FreightForwarder::find($data['carrier_id']);
            $data['carrier_name'] = $carrier?->name;
        }

        if (! empty($data['assigned_driver_id'])) {
            $driver = Driver::find($data['assigned_driver_id']);
            if ($conflict = $this->driverAssignmentConflict($driver)) {
                return $conflict;
            }
            $data['assigned_driver_name'] = trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? ''));
            $data['assigned_driver_code'] = $driver->driver_code;
        }
        if (! empty($data['assigned_trailer_id'])) {
            $trailer = Trailer::find($data['assigned_trailer_id']);
            if ($conflict = $this->trailerAssignmentConflict($trailer)) {
                return $conflict;
            }
            $data['assigned_trailer_label'] = $trailer->trailer_label ?? $trailer->trailer_code;
            $data['assigned_trailer_plate'] = $trailer->plate;
        }

        // Manual source by default (SAP imports land via a future endpoint).
        $data['source'] = 'manual';
        $data['is_sap_owned'] = false;

        return DB::transaction(function () use ($data, $audit, $events) {
            $data['order_no'] = $this->nextOrderNo();

            // TODO: re-enable when auth lands — populate created_by_user_id from request()->user().
            $order = LoadingOrder::create($data);

            $audit->record(
                $order,
                AuditAction::LOADING_ORDER_CREATED,
                null,
                $audit->snapshotModel($order),
                null,
                null
            );

            $events->record(
                'loading_order.created',
                $order,
                "Loading order {$order->order_no} created",
                ['source' => $order->source, 'customer_id' => $order->customer_id],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return ApiResponse::success(new LoadingOrderResource($order->fresh()), 'Loading order created', 201);
        });
    }

    public function update(
        UpdateLoadingOrderRequest $request,
        $id,
        SapFieldGuard $sapGuard,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $order = LoadingOrder::find($id);
        if (! $order) {
            return $this->error('Loading order not found', 'LOADING_ORDER_NOT_FOUND', 404);
        }

        $payload = $request->validated();
        // Defensive strip: the request rules omit these, but be explicit.
        foreach (UpdateLoadingOrderRequest::FORBIDDEN_UPDATE_FIELDS as $f) {
            unset($payload[$f]);
        }

        // Execution-lock check (PUT only — assign/unassign use their own check below).
        if ($order->is_locked_by_execution) {
            $touched = array_intersect(self::EXECUTION_LOCKED_FIELDS, array_keys($payload));
            if (! empty($touched)) {
                return $this->error(
                    'This loading order is locked by an active execution. The listed fields cannot be changed.',
                    'ORDER_LOCKED_BY_EXECUTION',
                    423,
                    ['lockedFields' => array_values($touched)]
                );
            }
        }

        // SAP-owned field protection.
        try {
            $sapGuard->assertCanUpdate($order, $payload, LoadingOrder::SAP_OWNED_FIELDS);
        } catch (SapFieldProtectionException $e) {
            $audit->record(
                $order,
                AuditAction::LOADING_ORDER_SAP_FIELD_UPDATE_REJECTED,
                $audit->snapshotModel($order),
                null,
                'Rejected local update on SAP-owned fields',
                null
            );
            $events->record(
                'loading_order.sap_field_update_rejected',
                $order,
                "Local update rejected on SAP-owned fields for {$order->order_no}",
                ['locked_fields' => $e->lockedFields],
                EventCategory::SECURITY,
                EventSeverity::WARNING
            );
            return $this->error(
                $e->getMessage(),
                'SAP_FIELDS_LOCKED',
                423,
                ['lockedFields' => $e->lockedFields]
            );
        }

        return DB::transaction(function () use ($order, $payload, $audit, $events) {
            $old = $audit->snapshotModel($order);
            $order->update($payload);

            $audit->record(
                $order,
                AuditAction::LOADING_ORDER_UPDATED,
                $old,
                $audit->snapshotModel($order->fresh()),
                null,
                null
            );

            $events->record(
                'loading_order.updated',
                $order,
                "Loading order {$order->order_no} updated",
                null,
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new LoadingOrderResource($order->fresh()), 'Loading order updated');
        });
    }

    public function assignDriver(AssignDriverRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $order = LoadingOrder::find($id);
        if (! $order) {
            return $this->error('Loading order not found', 'LOADING_ORDER_NOT_FOUND', 404);
        }
        if ($lock = $this->executionLockCheck($order, ['assigned_driver_id'])) {
            return $lock;
        }

        $driver = Driver::find($request->input('driver_id'));
        if ($conflict = $this->driverAssignmentConflict($driver)) {
            return $conflict;
        }

        return DB::transaction(function () use ($order, $driver, $audit, $events) {
            $old = $audit->snapshotModel($order);

            $order->update([
                'assigned_driver_id' => $driver->id,
                'assigned_driver_name' => trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? '')),
                'assigned_driver_code' => $driver->driver_code,
            ]);

            $audit->record(
                $order,
                AuditAction::LOADING_ORDER_DRIVER_ASSIGNED,
                $old,
                $audit->snapshotModel($order->fresh()),
                null,
                null
            );

            $events->record(
                'loading_order.driver_assigned',
                $order,
                "Driver {$driver->driver_code} assigned to {$order->order_no}",
                ['driver_id' => $driver->id, 'driver_code' => $driver->driver_code],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new LoadingOrderResource($order->fresh()), 'Driver assigned');
        });
    }

    public function unassignDriver(UnassignDriverRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $order = LoadingOrder::find($id);
        if (! $order) {
            return $this->error('Loading order not found', 'LOADING_ORDER_NOT_FOUND', 404);
        }
        if ($lock = $this->executionLockCheck($order, ['assigned_driver_id'])) {
            return $lock;
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($order, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($order);

            $order->update([
                'assigned_driver_id' => null,
                'assigned_driver_name' => null,
                'assigned_driver_code' => null,
            ]);

            $audit->record(
                $order,
                AuditAction::LOADING_ORDER_DRIVER_UNASSIGNED,
                $old,
                $audit->snapshotModel($order->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'loading_order.driver_unassigned',
                $order,
                "Driver unassigned from {$order->order_no}",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new LoadingOrderResource($order->fresh()), 'Driver unassigned');
        });
    }

    public function assignTrailer(AssignTrailerRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $order = LoadingOrder::find($id);
        if (! $order) {
            return $this->error('Loading order not found', 'LOADING_ORDER_NOT_FOUND', 404);
        }
        if ($lock = $this->executionLockCheck($order, ['assigned_trailer_id'])) {
            return $lock;
        }

        $trailer = Trailer::find($request->input('trailer_id'));
        if ($conflict = $this->trailerAssignmentConflict($trailer)) {
            return $conflict;
        }

        return DB::transaction(function () use ($order, $trailer, $audit, $events) {
            $old = $audit->snapshotModel($order);

            $order->update([
                'assigned_trailer_id' => $trailer->id,
                'assigned_trailer_label' => $trailer->trailer_label ?? $trailer->trailer_code,
                'assigned_trailer_plate' => $trailer->plate,
            ]);

            $audit->record(
                $order,
                AuditAction::LOADING_ORDER_TRAILER_ASSIGNED,
                $old,
                $audit->snapshotModel($order->fresh()),
                null,
                null
            );

            $events->record(
                'loading_order.trailer_assigned',
                $order,
                "Trailer {$trailer->trailer_code} assigned to {$order->order_no}",
                ['trailer_id' => $trailer->id, 'trailer_code' => $trailer->trailer_code],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new LoadingOrderResource($order->fresh()), 'Trailer assigned');
        });
    }

    public function unassignTrailer(UnassignTrailerRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $order = LoadingOrder::find($id);
        if (! $order) {
            return $this->error('Loading order not found', 'LOADING_ORDER_NOT_FOUND', 404);
        }
        if ($lock = $this->executionLockCheck($order, ['assigned_trailer_id'])) {
            return $lock;
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($order, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($order);

            $order->update([
                'assigned_trailer_id' => null,
                'assigned_trailer_label' => null,
                'assigned_trailer_plate' => null,
            ]);

            $audit->record(
                $order,
                AuditAction::LOADING_ORDER_TRAILER_UNASSIGNED,
                $old,
                $audit->snapshotModel($order->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'loading_order.trailer_unassigned',
                $order,
                "Trailer unassigned from {$order->order_no}",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new LoadingOrderResource($order->fresh()), 'Trailer unassigned');
        });
    }

    public function block(BlockLoadingOrderRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $order = LoadingOrder::find($id);
        if (! $order) {
            return $this->error('Loading order not found', 'LOADING_ORDER_NOT_FOUND', 404);
        }
        if (! is_null($order->blocked_at)) {
            return $this->error('Loading order is already blocked', 'ALREADY_BLOCKED', 409);
        }
        if (! is_null($order->cancelled_at)) {
            return $this->error('Cancelled orders cannot be blocked', 'ALREADY_CANCELLED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($order, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($order);

            // TODO: re-enable when auth lands — set blocked_by_user_id.
            $order->update([
                'blocked_at' => now(),
                'blocking_reason' => $reason,
                'blocking_reason_code' => $reasonCode,
            ]);

            $audit->record(
                $order,
                AuditAction::LOADING_ORDER_BLOCKED,
                $old,
                $audit->snapshotModel($order->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'loading_order.blocked',
                $order,
                "Loading order {$order->order_no} blocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new LoadingOrderResource($order->fresh()), 'Loading order blocked');
        });
    }

    public function unblock(UnblockLoadingOrderRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $order = LoadingOrder::find($id);
        if (! $order) {
            return $this->error('Loading order not found', 'LOADING_ORDER_NOT_FOUND', 404);
        }
        if (is_null($order->blocked_at)) {
            return $this->error('Loading order is not blocked', 'NOT_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($order, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($order);

            $order->update([
                'blocked_at' => null,
                'blocking_reason' => null,
                'blocking_reason_code' => null,
                'blocked_by_user_id' => null,
            ]);

            $audit->record(
                $order,
                AuditAction::LOADING_ORDER_UNBLOCKED,
                $old,
                $audit->snapshotModel($order->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'loading_order.unblocked',
                $order,
                "Loading order {$order->order_no} unblocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new LoadingOrderResource($order->fresh()), 'Loading order unblocked');
        });
    }

    public function cancel(CancelLoadingOrderRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $order = LoadingOrder::find($id);
        if (! $order) {
            return $this->error('Loading order not found', 'LOADING_ORDER_NOT_FOUND', 404);
        }
        if (! is_null($order->cancelled_at)) {
            return $this->error('Loading order is already cancelled', 'ALREADY_CANCELLED', 409);
        }
        // Cancel is forbidden once execution has started (active visit / operation).
        if ($order->is_locked_by_execution
            || $order->active_plant_visit_id
            || $order->active_loading_operation_id
        ) {
            return $this->error(
                'Cannot cancel an order with an active plant visit or loading operation.',
                'ORDER_LOCKED_BY_EXECUTION',
                423,
                ['activePlantVisitId' => $order->active_plant_visit_id,
                 'activeLoadingOperationId' => $order->active_loading_operation_id]
            );
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($order, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($order);

            // TODO: re-enable when auth lands — set cancelled_by_user_id.
            $order->update([
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancellation_reason_code' => $reasonCode,
            ]);

            $audit->record(
                $order,
                AuditAction::LOADING_ORDER_CANCELLED,
                $old,
                $audit->snapshotModel($order->fresh()),
                $reason,
                $reasonCode
            );

            $events->record(
                'loading_order.cancelled',
                $order,
                "Loading order {$order->order_no} cancelled",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new LoadingOrderResource($order->fresh()), 'Loading order cancelled');
        });
    }

    public function eventsAudit(Request $request, $id)
    {
        $order = LoadingOrder::find($id);
        if (! $order) {
            return $this->error('Loading order not found', 'LOADING_ORDER_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 50);
        $entityType = $order->getMorphClass();

        $audits = AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $order->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $events = EventLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $order->id)
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();

        return $this->success([
            'audits' => $audits,
            'events' => $events,
            'meta' => [
                'per_page' => $perPage,
                'audits_count' => $audits->count(),
                'events_count' => $events->count(),
            ],
        ], 'Loading order events & audit retrieved');
    }

    // ---------- helpers ----------

    /**
     * Generate the next order_no in the form `LO-YYYY-NNNN`.
     */
    protected function nextOrderNo(): string
    {
        $year = now()->format('Y');
        $countThisYear = LoadingOrder::query()->whereYear('created_at', now()->year)->count();
        $seq = str_pad((string) ($countThisYear + 1), 4, '0', STR_PAD_LEFT);
        return "LO-{$year}-{$seq}";
    }

    /**
     * Returns a JsonResponse with 423 when the order is execution-locked AND
     * the touched-fields set intersects with EXECUTION_LOCKED_FIELDS.
     * Otherwise returns null.
     *
     * @return \Illuminate\Http\JsonResponse|null
     */
    protected function executionLockCheck(LoadingOrder $order, array $touchedFields)
    {
        if (! $order->is_locked_by_execution) {
            return null;
        }
        $hits = array_intersect(self::EXECUTION_LOCKED_FIELDS, $touchedFields);
        if (empty($hits)) {
            return null;
        }
        return $this->error(
            'This loading order is locked by an active execution.',
            'ORDER_LOCKED_BY_EXECUTION',
            423,
            ['lockedFields' => array_values($hits)]
        );
    }

    /**
     * Returns a JsonResponse with 409 when the driver is missing / inactive /
     * blocked. Otherwise returns null.
     */
    protected function driverAssignmentConflict(?Driver $driver)
    {
        if (! $driver) {
            return $this->error('Driver not found', 'DRIVER_NOT_FOUND', 404);
        }
        if ($driver->block_status === 'blocked') {
            return $this->error(
                'Cannot assign a blocked driver.',
                'DRIVER_BLOCKED',
                409,
                ['driver_id' => $driver->id]
            );
        }
        if ($driver->is_active === false) {
            return $this->error(
                'Cannot assign an inactive driver.',
                'DRIVER_BLOCKED',
                409,
                ['driver_id' => $driver->id]
            );
        }
        return null;
    }

    /**
     * Returns a JsonResponse with 409 when the trailer is missing / blocked /
     * has no usable chip. Otherwise returns null.
     */
    protected function trailerAssignmentConflict(?Trailer $trailer)
    {
        if (! $trailer) {
            return $this->error('Trailer not found', 'TRAILER_NOT_FOUND', 404);
        }
        if ($trailer->status === TrailerStatus::BLOCKED) {
            return $this->error(
                'Cannot assign a blocked trailer.',
                'TRAILER_BLOCKED',
                409,
                ['trailer_id' => $trailer->id]
            );
        }
        // chip_state derived accessor returns one of: assigned, missing, blocked, mismatch, not_required
        $chip = $trailer->chip_state;
        if (in_array($chip, ['missing', 'blocked', 'mismatch'], true)) {
            return $this->error(
                'Trailer does not have a usable chip.',
                'TRAILER_CHIP_MISSING',
                409,
                ['trailer_id' => $trailer->id, 'chipState' => $chip]
            );
        }
        return null;
    }

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'like', $search)
                    ->orWhere('sap_reference', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('assigned_driver_name', 'like', $search)
                    ->orWhere('assigned_trailer_label', 'like', $search);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['carrier_id'])) {
            $query->where('carrier_id', $filters['carrier_id']);
        }
        if (! empty($filters['assigned_driver_id'])) {
            $query->where('assigned_driver_id', $filters['assigned_driver_id']);
        }
        if (! empty($filters['assigned_trailer_id'])) {
            $query->where('assigned_trailer_id', $filters['assigned_trailer_id']);
        }
        if (! empty($filters['task_flow'])) {
            $query->where('task_flow', $filters['task_flow']);
        }
        if (! empty($filters['planned_from'])) {
            $query->where('planned_window_start', '>=', $filters['planned_from']);
        }
        if (! empty($filters['planned_to'])) {
            $query->where('planned_window_end', '<=', $filters['planned_to']);
        }
        if (array_key_exists('is_sap_owned', $filters)
            && $filters['is_sap_owned'] !== null
            && $filters['is_sap_owned'] !== ''
        ) {
            $query->where('is_sap_owned', filter_var($filters['is_sap_owned'], FILTER_VALIDATE_BOOLEAN));
        }
        if (array_key_exists('attention', $filters)
            && filter_var($filters['attention'], FILTER_VALIDATE_BOOLEAN)
        ) {
            $query->whereIn('status', [
                LoadingOrderStatus::NEEDS_ASSIGNMENT,
                LoadingOrderStatus::BLOCKED,
            ]);
        }
    }

    protected function summary(): array
    {
        $base = LoadingOrder::query();

        $statuses = (clone $base)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        return [
            'total' => (clone $base)->count(),
            'draft' => (int) ($statuses[LoadingOrderStatus::DRAFT] ?? 0),
            'needsAssignment' => (int) ($statuses[LoadingOrderStatus::NEEDS_ASSIGNMENT] ?? 0),
            'ready' => (int) ($statuses[LoadingOrderStatus::READY] ?? 0),
            'inProgress' => (int) ($statuses[LoadingOrderStatus::IN_PROGRESS] ?? 0),
            'blocked' => (int) ($statuses[LoadingOrderStatus::BLOCKED] ?? 0),
            'cancelled' => (int) ($statuses[LoadingOrderStatus::CANCELLED] ?? 0),
            'completed' => (int) ($statuses[LoadingOrderStatus::COMPLETED] ?? 0),
        ];
    }
}
