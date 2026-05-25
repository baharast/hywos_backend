<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\ParkingLoadState;
use App\Enums\ParkingNextAction;
use App\Enums\ParkingSlotStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Parking\BlockSlotRequest;
use App\Http\Requests\Parking\ClearSlotRequest;
use App\Http\Requests\Parking\OccupySlotRequest;
use App\Http\Requests\Parking\OutOfServiceSlotRequest;
use App\Http\Requests\Parking\ReserveSlotRequest;
use App\Http\Requests\Parking\RestoreSlotRequest;
use App\Http\Requests\Parking\UnblockSlotRequest;
use App\Http\Requests\StoreParkingRequest;
use App\Http\Requests\UpdateParkingRequest;
use App\Http\Resources\ParkingResource;
use App\Models\Parking;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParkingController extends ApiController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = Parking::query();

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', $search)
                    ->orWhere('name', 'like', $search);
            });
        }

        if ($request->filled('slot_status')) {
            $query->where('slot_status', $request->query('slot_status'));
        }
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->query('site_id'));
        }
        if ($request->filled('plant_area_id')) {
            $query->where('plant_area_id', $request->query('plant_area_id'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $base = Parking::query();
        $summary = [
            'total' => (clone $base)->count(),
            'free' => (clone $base)->where('slot_status', ParkingSlotStatus::FREE)->count(),
            'occupied' => (clone $base)->where('slot_status', ParkingSlotStatus::OCCUPIED)->count(),
            'blocked' => (clone $base)->whereIn('slot_status', [
                ParkingSlotStatus::BLOCKED,
                ParkingSlotStatus::OUT_OF_SERVICE,
            ])->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
        ];

        $paginator = $query->orderBy('code')->paginate($perPage);
        $items = ParkingResource::collection($paginator->items());
        $lastUpdated = Parking::query()->max('updated_at');

        return \App\Services\ApiResponse::list($items, $paginator, $summary, $lastUpdated, 'Parkings retrieved');
    }

    public function store(StoreParkingRequest $request)
    {
        $data = $request->validated();
        $parking = Parking::create($data);

        return $this->created(new ParkingResource($parking), 'Parking created');
    }

    public function show($id)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        return $this->success(new ParkingResource($parking), 'Parking retrieved');
    }

    public function update(UpdateParkingRequest $request, $id)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        $parking->update($request->validated());

        return $this->success(new ParkingResource($parking), 'Parking updated');
    }

    public function activate(Request $request, $id)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        $parking->update(['is_active' => true]);

        return $this->success(new ParkingResource($parking), 'Parking activated');
    }

    public function deactivate(Request $request, $id)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        $parking->update(['is_active' => false]);

        return $this->success(new ParkingResource($parking), 'Parking deactivated');
    }

    public function destroy(Request $request, $id)
    {
        // V2.1 — slots are part of the fixed site layout, not user-created
        // master data. DELETE acts as a soft deactivate so the row + history
        // remain. Auth is intentionally disabled in this MVP phase.
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        $parking->update(['is_active' => false]);

        return $this->success(null, 'Parking deactivated');
    }

    /* ============================================================
     * Lifecycle actions (V2.1 §16.1) — critical actions:
     * POST + required reason + audit row + event row, in a single
     * DB transaction so an outage can never half-commit a slot move.
     * `next_action` is re-derived from the post-mutation state per
     * V2.1 §16.1 by deriveNextAction() below; callers do NOT pass it.
     * ============================================================
     */

    public function reserve(ReserveSlotRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }
        if ($parking->slot_status !== ParkingSlotStatus::FREE) {
            return $this->error('Slot is not free', 'SLOT_NOT_FREE', 409);
        }

        $data = $request->validated();
        $reason = $data['reason'];
        $reasonCode = $data['reason_code'] ?? null;

        return DB::transaction(function () use ($parking, $data, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($parking);

            $parking->fill([
                'slot_status' => ParkingSlotStatus::RESERVED,
                'reserved_for' => $data['reservation_for'] ?? null,
                'linked_order_id' => $data['linked_order_id'] ?? null,
                'linked_order_no' => $data['linked_order_no'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
            ]);
            $parking->next_action = $this->deriveNextAction($parking);
            $parking->save();

            $fresh = $parking->fresh();
            $audit->record(
                $parking,
                AuditAction::PARKING_SLOT_RESERVED,
                $old,
                $audit->snapshotModel($fresh),
                $reason,
                $reasonCode
            );
            $events->record(
                AuditAction::PARKING_SLOT_RESERVED,
                $parking,
                "Parking {$parking->code} reserved",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new ParkingResource($fresh), 'Parking slot reserved');
        });
    }

    public function occupy(OccupySlotRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }
        if (in_array($parking->slot_status, [ParkingSlotStatus::BLOCKED, ParkingSlotStatus::OUT_OF_SERVICE], true)) {
            return $this->error('Slot is blocked or out of service', 'SLOT_BLOCKED', 409);
        }

        $data = $request->validated();
        $reason = $data['reason'];
        $reasonCode = $data['reason_code'] ?? null;

        return DB::transaction(function () use ($parking, $data, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($parking);

            $parking->fill([
                'slot_status' => ParkingSlotStatus::OCCUPIED,
                'current_trailer_id' => $data['trailer_id'],
                'current_trailer_label' => $data['trailer_label'] ?? null,
                'current_trailer_plate' => $data['trailer_plate'] ?? null,
                'current_trailer_chip' => $data['current_trailer_chip'] ?? null,
                'current_load_state' => $data['load_state'],
                'linked_order_id' => $data['linked_order_id'] ?? null,
                'linked_order_no' => $data['linked_order_no'] ?? null,
                'active_visit_id' => $data['active_visit_id'] ?? null,
                'active_visit_no' => $data['active_visit_no'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'tractor_plate' => $data['tractor_plate'] ?? null,
                'parked_since' => now(),
                'reserved_for' => null,
            ]);
            $parking->next_action = $this->deriveNextAction($parking);
            $parking->save();

            $fresh = $parking->fresh();
            $audit->record(
                $parking,
                AuditAction::PARKING_SLOT_OCCUPIED,
                $old,
                $audit->snapshotModel($fresh),
                $reason,
                $reasonCode
            );
            $events->record(
                AuditAction::PARKING_SLOT_OCCUPIED,
                $parking,
                "Parking {$parking->code} occupied by trailer {$data['trailer_id']}",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new ParkingResource($fresh), 'Parking slot occupied');
        });
    }

    public function clear(ClearSlotRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        $data = $request->validated();
        $reason = $data['reason'];
        $reasonCode = $data['reason_code'] ?? null;

        return DB::transaction(function () use ($parking, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($parking);

            $parking->fill([
                'slot_status' => ParkingSlotStatus::FREE,
                'current_trailer_id' => null,
                'current_trailer_label' => null,
                'current_trailer_plate' => null,
                'current_trailer_chip' => null,
                'current_load_state' => null,
                'linked_order_id' => null,
                'linked_order_no' => null,
                'active_visit_id' => null,
                'active_visit_no' => null,
                'driver_id' => null,
                'driver_name' => null,
                'tractor_plate' => null,
                'parked_since' => null,
                'reserved_for' => null,
                'document_summary' => null,
                'next_action' => ParkingNextAction::NONE,
            ]);
            $parking->save();

            $fresh = $parking->fresh();
            $audit->record(
                $parking,
                AuditAction::PARKING_SLOT_CLEARED,
                $old,
                $audit->snapshotModel($fresh),
                $reason,
                $reasonCode
            );
            $events->record(
                AuditAction::PARKING_SLOT_CLEARED,
                $parking,
                "Parking {$parking->code} cleared",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new ParkingResource($fresh), 'Parking slot cleared');
        });
    }

    public function block(BlockSlotRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }
        if ($parking->slot_status === ParkingSlotStatus::BLOCKED) {
            return $this->error('Slot is already blocked', 'ALREADY_BLOCKED', 409);
        }

        $data = $request->validated();
        $reason = $data['reason'];
        $reasonCode = $data['reason_code'] ?? null;

        return DB::transaction(function () use ($parking, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($parking);

            // Block preserves the trailer / order / visit snapshot by design —
            // the slot is still physically occupied; the block flag overlays
            // an operational hold on top of the existing context.
            $parking->fill([
                'slot_status' => ParkingSlotStatus::BLOCKED,
                'blocker_reason' => $reason,
            ]);
            $parking->next_action = $this->deriveNextAction($parking);
            $parking->save();

            $fresh = $parking->fresh();
            $audit->record(
                $parking,
                AuditAction::PARKING_SLOT_BLOCKED,
                $old,
                $audit->snapshotModel($fresh),
                $reason,
                $reasonCode
            );
            $events->record(
                AuditAction::PARKING_SLOT_BLOCKED,
                $parking,
                "Parking {$parking->code} blocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new ParkingResource($fresh), 'Parking slot blocked');
        });
    }

    public function unblock(UnblockSlotRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }
        if ($parking->slot_status !== ParkingSlotStatus::BLOCKED) {
            return $this->error('Slot is not blocked', 'NOT_BLOCKED', 409);
        }

        $data = $request->validated();
        $reason = $data['reason'] ?? null;
        $reasonCode = $data['reason_code'] ?? null;

        return DB::transaction(function () use ($parking, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($parking);

            $restoredStatus = $parking->current_trailer_id
                ? ParkingSlotStatus::OCCUPIED
                : ParkingSlotStatus::FREE;

            $parking->fill([
                'slot_status' => $restoredStatus,
                'blocker_reason' => null,
            ]);
            $parking->next_action = $this->deriveNextAction($parking);
            $parking->save();

            $fresh = $parking->fresh();
            $audit->record(
                $parking,
                AuditAction::PARKING_SLOT_UNBLOCKED,
                $old,
                $audit->snapshotModel($fresh),
                $reason,
                $reasonCode
            );
            $events->record(
                AuditAction::PARKING_SLOT_UNBLOCKED,
                $parking,
                "Parking {$parking->code} unblocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new ParkingResource($fresh), 'Parking slot unblocked');
        });
    }

    public function outOfService(OutOfServiceSlotRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }

        $data = $request->validated();
        $reason = $data['reason'];
        $reasonCode = $data['reason_code'] ?? null;

        return DB::transaction(function () use ($parking, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($parking);

            // Out-of-service means the slot is physically unavailable, so any
            // previously parked trailer / order / visit snapshot is dropped.
            $parking->fill([
                'slot_status' => ParkingSlotStatus::OUT_OF_SERVICE,
                'blocker_reason' => $reason,
                'current_trailer_id' => null,
                'current_trailer_label' => null,
                'current_trailer_plate' => null,
                'current_trailer_chip' => null,
                'current_load_state' => null,
                'linked_order_id' => null,
                'linked_order_no' => null,
                'active_visit_id' => null,
                'active_visit_no' => null,
                'driver_id' => null,
                'driver_name' => null,
                'tractor_plate' => null,
                'parked_since' => null,
                'reserved_for' => null,
                'document_summary' => null,
            ]);
            $parking->next_action = $this->deriveNextAction($parking);
            $parking->save();

            $fresh = $parking->fresh();
            $audit->record(
                $parking,
                AuditAction::PARKING_SLOT_OUT_OF_SERVICE,
                $old,
                $audit->snapshotModel($fresh),
                $reason,
                $reasonCode
            );
            $events->record(
                AuditAction::PARKING_SLOT_OUT_OF_SERVICE,
                $parking,
                "Parking {$parking->code} taken out of service",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new ParkingResource($fresh), 'Parking slot out of service');
        });
    }

    public function restore(RestoreSlotRequest $request, $id, AuditLogger $audit, EventLogger $events)
    {
        $parking = Parking::find($id);
        if (! $parking) {
            return $this->error('Parking not found', 'PARKING_NOT_FOUND', 404);
        }
        if (! in_array($parking->slot_status, [ParkingSlotStatus::OUT_OF_SERVICE, ParkingSlotStatus::BLOCKED], true)) {
            return $this->error('Slot is not out of service', 'NOT_OUT_OF_SERVICE', 409);
        }

        $data = $request->validated();
        $reason = $data['reason'] ?? null;
        $reasonCode = $data['reason_code'] ?? null;

        return DB::transaction(function () use ($parking, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($parking);

            $parking->fill([
                'slot_status' => ParkingSlotStatus::FREE,
                'blocker_reason' => null,
                'next_action' => ParkingNextAction::NONE,
            ]);
            $parking->save();

            $fresh = $parking->fresh();
            $audit->record(
                $parking,
                AuditAction::PARKING_SLOT_RESTORED,
                $old,
                $audit->snapshotModel($fresh),
                $reason,
                $reasonCode
            );
            $events->record(
                AuditAction::PARKING_SLOT_RESTORED,
                $parking,
                "Parking {$parking->code} restored",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new ParkingResource($fresh), 'Parking slot restored');
        });
    }

    /**
     * V2.1 §16.1 — derive the operator-facing next-action chip from the slot's
     * current state. Called after every lifecycle mutation so the value on the
     * row always matches the visible slot status / trailer snapshot.
     */
    private function deriveNextAction(Parking $p): string
    {
        $caseId = $p->clarification_case_id;

        switch ($p->slot_status) {
            case ParkingSlotStatus::FREE:
                return ParkingNextAction::NONE;

            case ParkingSlotStatus::RESERVED:
                return ParkingNextAction::WAIT_FOR_ARRIVAL;

            case ParkingSlotStatus::OCCUPIED:
                if ($p->linked_order_id && $p->current_load_state === ParkingLoadState::LOADED) {
                    return $this->documentsReady($p)
                        ? ParkingNextAction::OPEN_DOCUMENTS
                        : ParkingNextAction::OPEN_VISIT;
                }
                if (! $p->linked_order_id) {
                    return $caseId
                        ? ParkingNextAction::OPEN_CLARIFICATION
                        : ParkingNextAction::WAIT_FOR_PICKUP;
                }
                return ParkingNextAction::OPEN_VISIT;

            case ParkingSlotStatus::BLOCKED:
            case ParkingSlotStatus::OUT_OF_SERVICE:
                return $caseId
                    ? ParkingNextAction::OPEN_CLARIFICATION
                    : ParkingNextAction::NONE;
        }

        return ParkingNextAction::NONE;
    }

    private function documentsReady(Parking $p): bool
    {
        $summary = $p->document_summary;
        if (! is_array($summary)) {
            return false;
        }
        foreach (['certificateStatus', 'deliveryNoteStatus', 'qmDocumentStatus'] as $key) {
            if (($summary[$key] ?? null) === 'available') {
                return true;
            }
        }
        return false;
    }
}
