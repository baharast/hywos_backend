<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\ClarificationEntityType;
use App\Enums\ClarificationSeverity;
use App\Enums\ClarificationStatus;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\PlantVisitStatus;
use App\Http\Requests\PlantVisit\AdvanceStepRequest;
use App\Http\Requests\PlantVisit\BlockVisitRequest;
use App\Http\Requests\PlantVisit\ChangeLocationRequest;
use App\Http\Requests\PlantVisit\CloseVisitRequest;
use App\Http\Requests\PlantVisit\CreatePlantVisitRequest;
use App\Http\Requests\PlantVisit\ForceCloseVisitRequest;
use App\Http\Requests\PlantVisit\MarkReadyForExitRequest;
use App\Http\Requests\PlantVisit\RaiseClarificationRequest;
use App\Http\Requests\PlantVisit\ResumeVisitRequest;
use App\Http\Requests\PlantVisit\UnblockVisitRequest;
use App\Http\Requests\PlantVisit\WaitVisitRequest;
use App\Http\Resources\PlantVisitResource;
use App\Models\AuditLog;
use App\Models\ClarificationCase;
use App\Models\Driver;
use App\Models\EventLog;
use App\Models\LoadingOrder;
use App\Models\PlantVisit;
use App\Models\TractorVehicle;
use App\Models\Trailer;
use App\Services\ApiResponse;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use App\Services\PlantVisits\PlantVisitStepValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Plant Visits HTTP layer per Active Plant Visits V1.6.
 *
 * The model owns the safety nets (waiting-reason demote, step matrix
 * fallback to CLARIFICATION+step_mismatch, snapshot immutability). This
 * controller rejects bad payloads at the boundary with explicit error codes
 * so the dispatcher dashboard never has to interpret a silent demotion.
 *
 * The THREE workflow concepts (task_flow / current_step / visit_status)
 * are emitted as INDEPENDENT badges (PlantVisitResource handles the split).
 */
class PlantVisitController extends ApiController
{
    /* =========================================================
     * GET /api/plant-visits — list with filters + summary
     * ========================================================= */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $query = PlantVisit::query();

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search): void {
                $q->where('visit_no', 'like', $search)
                    ->orWhere('driver_name', 'like', $search)
                    ->orWhere('trailer_label', 'like', $search)
                    ->orWhere('tractor_plate', 'like', $search)
                    ->orWhere('order_no', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('visit_status', $request->query('status'));
        }
        if ($request->filled('task_flow')) {
            $query->where('task_flow', $request->query('task_flow'));
        }
        if ($request->filled('current_step')) {
            $query->where('current_step', $request->query('current_step'));
        }
        if ($request->filled('current_location')) {
            $query->where('current_location', $request->query('current_location'));
        }
        if ($request->filled('order_id')) {
            $query->where('order_id', $request->query('order_id'));
        }
        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->query('driver_id'));
        }
        if ($request->filled('trailer_id')) {
            $query->where('trailer_id', $request->query('trailer_id'));
        }

        if ($request->filled('is_open')) {
            $open = filter_var($request->query('is_open'), FILTER_VALIDATE_BOOLEAN);
            if ($open) {
                $query->where('visit_status', '!=', PlantVisitStatus::CLOSED);
            } else {
                $query->where('visit_status', PlantVisitStatus::CLOSED);
            }
        }
        if ($request->filled('needs_action')) {
            if (filter_var($request->query('needs_action'), FILTER_VALIDATE_BOOLEAN)) {
                $query->whereIn('visit_status', [
                    PlantVisitStatus::BLOCKED,
                    PlantVisitStatus::CLARIFICATION,
                ]);
            }
        }
        if ($request->filled('entry_from')) {
            $query->where('entry_time', '>=', $request->query('entry_from'));
        }
        if ($request->filled('entry_to')) {
            $query->where('entry_time', '<=', $request->query('entry_to'));
        }

        // sort: default `-entry_time` (newest first). Allowed columns only.
        $sort = (string) $request->query('sort', '-entry_time');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['entry_time', 'updated_at', 'status'];
        if (! in_array($column, $allowed, true)) {
            $column = 'entry_time';
            $direction = 'desc';
        }
        $sortColumn = $column === 'status' ? 'visit_status' : $column;

        $paginator = $query->orderBy($sortColumn, $direction)->paginate($perPage);
        $rows = PlantVisitResource::collection($paginator->items());

        $summary = $this->summary();
        $lastUpdated = PlantVisit::query()->max('updated_at');

        return ApiResponse::list($rows, $paginator, $summary, $lastUpdated, 'Plant visits retrieved');
    }

    protected function summary(): array
    {
        $base = PlantVisit::query();

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->where('visit_status', '!=', PlantVisitStatus::CLOSED)->count(),
            'needsAction' => (clone $base)->whereIn('visit_status', [
                PlantVisitStatus::BLOCKED,
                PlantVisitStatus::CLARIFICATION,
            ])->count(),
            'waiting' => (clone $base)->where('visit_status', PlantVisitStatus::WAITING)->count(),
            'readyForExit' => (clone $base)->where('visit_status', PlantVisitStatus::READY_FOR_EXIT)->count(),
            'closed' => (clone $base)->where('visit_status', PlantVisitStatus::CLOSED)->count(),
        ];
    }

    /* =========================================================
     * GET /api/plant-visits/{id}
     * ========================================================= */
    public function show($id)
    {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }

        return $this->success(new PlantVisitResource($visit), 'Plant visit retrieved');
    }

    /* =========================================================
     * POST /api/plant-visits — create + auto visit_no + snapshots
     * ========================================================= */
    public function store(CreatePlantVisitRequest $request, AuditLogger $audit, EventLogger $events)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data, $audit, $events) {
            // Resolve denormalized labels from soft FKs at create time.
            $driver = ! empty($data['driver_id']) ? Driver::find($data['driver_id']) : null;
            $trailer = ! empty($data['trailer_id']) ? Trailer::find($data['trailer_id']) : null;
            $tractor = ! empty($data['tractor_vehicle_id']) ? TractorVehicle::find($data['tractor_vehicle_id']) : null;
            $order = ! empty($data['order_id']) ? LoadingOrder::find($data['order_id']) : null;

            if ($driver) {
                $data['driver_name'] = $driver->full_name ?? trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? ''));
                $data['driver_code'] = $driver->driver_code;
                $data['driver_snapshot'] = [
                    'driver_id' => $driver->id,
                    'driver_code' => $driver->driver_code,
                    'full_name' => $data['driver_name'],
                ];
            }
            if ($trailer) {
                $data['trailer_label'] = $trailer->trailer_label ?? $trailer->trailer_code;
                $data['trailer_plate'] = $trailer->plate;
                $data['trailer_snapshot'] = [
                    'trailer_id' => $trailer->id,
                    'trailer_label' => $data['trailer_label'],
                    'plate' => $trailer->plate,
                    'chip' => $data['trailer_chip'] ?? null,
                ];
            }
            if ($tractor) {
                $data['tractor_snapshot'] = [
                    'tractor_vehicle_id' => $tractor->id,
                    'vehicle_code' => $tractor->vehicle_code ?? null,
                    'plate' => $tractor->license_plate ?? null,
                ];
                // If tractor_plate wasn't explicitly captured at the terminal,
                // fall back to the master-data plate so the visit isn't blank.
                if (empty($data['tractor_plate']) && ! empty($tractor->license_plate)) {
                    $data['tractor_plate'] = $tractor->license_plate;
                }
            }
            if ($order) {
                $data['order_no'] = $order->order_no;
                $data['sap_reference'] = $order->sap_reference;
                $data['order_snapshot'] = [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'sap_reference' => $order->sap_reference,
                    'product_quality' => $order->product_quality,
                    'target_quantity' => $order->target_quantity,
                    'unit' => $order->unit,
                ];
            }

            $visit = PlantVisit::create($data);

            // Back-fill the order so LoadingOrder.status flips to in_progress
            // via its own saving() hook + readiness service. We use direct
            // update() (not model relationship mutation) to keep the
            // LoadingOrder model untouched in this task.
            if ($order) {
                $order->update([
                    'active_plant_visit_id' => $visit->id,
                    'active_plant_visit_no' => $visit->visit_no,
                ]);
            }

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_CREATED,
                null,
                $audit->snapshotModel($visit),
                null,
                null
            );
            $events->record(
                'plant_visit.created',
                $visit,
                "Plant visit {$visit->visit_no} opened",
                [
                    'task_flow' => $visit->task_flow,
                    'current_step' => $visit->current_step,
                    'order_id' => $visit->order_id,
                    'driver_id' => $visit->driver_id,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return ApiResponse::success(new PlantVisitResource($visit->fresh()), 'Plant visit created', 201);
        });
    }

    /* =========================================================
     * POST /api/plant-visits/{id}/advance-step
     * ========================================================= */
    public function advanceStep(
        AdvanceStepRequest $request,
        $id,
        PlantVisitStepValidator $stepValidator,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }

        $newStep = $request->validated()['current_step'];

        // Explicit pre-save check so the operator gets a clean 422 instead of
        // the model's silent rewrite to CLARIFICATION + step_mismatch.
        if (! $stepValidator->isAllowed($visit->task_flow, $newStep)) {
            return $this->error(
                "Step '{$newStep}' is not allowed for task flow '{$visit->task_flow}'.",
                'STEP_NOT_ALLOWED_FOR_TASK',
                422,
                [
                    'task_flow' => $visit->task_flow,
                    'current_step' => $newStep,
                    'allowed_steps' => $stepValidator->allowedStepsFor($visit->task_flow),
                ]
            );
        }

        return DB::transaction(function () use ($visit, $newStep, $audit, $events) {
            $old = $audit->snapshotModel($visit);
            $previousStep = $visit->current_step;

            $visit->update(['current_step' => $newStep]);

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_STEP_ADVANCED,
                $old,
                $audit->snapshotModel($visit->fresh()),
                null,
                null
            );
            $events->record(
                'plant_visit.step_advanced',
                $visit,
                "Plant visit {$visit->visit_no} step: {$previousStep} → {$newStep}",
                ['from' => $previousStep, 'to' => $newStep],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new PlantVisitResource($visit->fresh()), 'Plant visit step advanced');
        });
    }

    /* =========================================================
     * POST /api/plant-visits/{id}/change-location
     * ========================================================= */
    public function changeLocation(
        ChangeLocationRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }

        $newLocation = $request->validated()['current_location'];

        return DB::transaction(function () use ($visit, $newLocation, $audit, $events) {
            $old = $audit->snapshotModel($visit);
            $previous = $visit->current_location;

            $visit->update(['current_location' => $newLocation]);

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_LOCATION_CHANGED,
                $old,
                $audit->snapshotModel($visit->fresh()),
                null,
                null
            );
            $events->record(
                'plant_visit.location_changed',
                $visit,
                "Plant visit {$visit->visit_no} location: {$previous} → {$newLocation}",
                ['from' => $previous, 'to' => $newLocation],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new PlantVisitResource($visit->fresh()), 'Plant visit location changed');
        });
    }

    /* =========================================================
     * POST /api/plant-visits/{id}/wait
     * ========================================================= */
    public function wait(
        WaitVisitRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }

        $reason = $request->validated()['waiting_reason'];

        return DB::transaction(function () use ($visit, $reason, $audit, $events) {
            $old = $audit->snapshotModel($visit);

            $visit->update([
                'visit_status' => PlantVisitStatus::WAITING,
                'waiting_reason' => $reason,
            ]);

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_STATUS_CHANGED,
                $old,
                $audit->snapshotModel($visit->fresh()),
                $reason,
                null
            );
            $events->record(
                'plant_visit.status_changed',
                $visit,
                "Plant visit {$visit->visit_no} now waiting: {$reason}",
                ['to' => PlantVisitStatus::WAITING, 'waiting_reason' => $reason],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new PlantVisitResource($visit->fresh()), 'Plant visit set to waiting');
        });
    }

    /* =========================================================
     * POST /api/plant-visits/{id}/resume
     * ========================================================= */
    public function resume(
        ResumeVisitRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }

        $reason = $request->input('reason');

        return DB::transaction(function () use ($visit, $reason, $audit, $events) {
            $old = $audit->snapshotModel($visit);

            $visit->update([
                'visit_status' => PlantVisitStatus::NORMAL,
                'waiting_reason' => null,
            ]);

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_STATUS_CHANGED,
                $old,
                $audit->snapshotModel($visit->fresh()),
                $reason,
                null
            );
            $events->record(
                'plant_visit.status_changed',
                $visit,
                "Plant visit {$visit->visit_no} resumed",
                ['to' => PlantVisitStatus::NORMAL, 'reason' => $reason],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new PlantVisitResource($visit->fresh()), 'Plant visit resumed');
        });
    }

    /* =========================================================
     * POST /api/plant-visits/{id}/block
     * ========================================================= */
    public function block(
        BlockVisitRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }
        if ($visit->visit_status === PlantVisitStatus::BLOCKED) {
            return $this->error('Plant visit is already blocked', 'ALREADY_BLOCKED', 409);
        }

        $reason = $request->validated()['reason'];
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($visit, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($visit);

            $visit->update([
                'visit_status' => PlantVisitStatus::BLOCKED,
                'blocker_reason' => $reason,
            ]);

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_BLOCKED,
                $old,
                $audit->snapshotModel($visit->fresh()),
                $reason,
                $reasonCode
            );
            $events->record(
                'plant_visit.blocked',
                $visit,
                "Plant visit {$visit->visit_no} blocked",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new PlantVisitResource($visit->fresh()), 'Plant visit blocked');
        });
    }

    /* =========================================================
     * POST /api/plant-visits/{id}/unblock
     * ========================================================= */
    public function unblock(
        UnblockVisitRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }
        if ($visit->visit_status !== PlantVisitStatus::BLOCKED) {
            return $this->error('Plant visit is not blocked', 'NOT_BLOCKED', 409);
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($visit, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($visit);

            $visit->update([
                'visit_status' => PlantVisitStatus::NORMAL,
                'blocker_reason' => null,
            ]);

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_STATUS_CHANGED,
                $old,
                $audit->snapshotModel($visit->fresh()),
                $reason,
                $reasonCode
            );
            $events->record(
                'plant_visit.status_changed',
                $visit,
                "Plant visit {$visit->visit_no} unblocked",
                ['to' => PlantVisitStatus::NORMAL, 'reason' => $reason],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new PlantVisitResource($visit->fresh()), 'Plant visit unblocked');
        });
    }

    /* =========================================================
     * POST /api/plant-visits/{id}/raise-clarification
     * ========================================================= */
    public function raiseClarification(
        RaiseClarificationRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }

        $data = $request->validated();
        $reason = $data['reason'];
        $category = $data['category'];
        $createCase = (bool) ($data['create_case'] ?? false);

        return DB::transaction(function () use ($visit, $reason, $category, $createCase, $audit, $events) {
            $old = $audit->snapshotModel($visit);

            $caseId = $visit->clarification_case_id;

            if ($createCase) {
                $case = ClarificationCase::create([
                    // B1 enum reconciliation in progress — 'open' is stable.
                    'status' => ClarificationStatus::OPEN,
                    'severity' => ClarificationSeverity::NORMAL,
                    'category' => $category,
                    'title' => "Clarification on visit {$visit->visit_no}",
                    'description' => $reason,
                    'entity_type' => ClarificationEntityType::PLANT_VISIT,
                    'entity_id' => (string) $visit->id,
                    'entity_label' => $visit->visit_no,
                    'related_plant_visit_id' => $visit->id,
                    'related_order_id' => $visit->order_id,
                    'related_driver_id' => $visit->driver_id,
                    'related_trailer_id' => $visit->trailer_id,
                    'owner_role' => $visit->owner_role ?: 'Dispatcher',
                    'is_blocking' => true,
                ]);
                $caseId = $case->id;
            }

            $visit->update([
                'visit_status' => PlantVisitStatus::CLARIFICATION,
                'blocker_reason' => $reason,
                'clarification_case_id' => $caseId,
            ]);

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_CLARIFICATION_RAISED,
                $old,
                $audit->snapshotModel($visit->fresh()),
                $reason,
                $category
            );
            $events->record(
                'plant_visit.clarification_raised',
                $visit,
                "Plant visit {$visit->visit_no} clarification raised: {$category}",
                [
                    'reason' => $reason,
                    'category' => $category,
                    'clarification_case_id' => $caseId,
                    'created_case' => $createCase,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new PlantVisitResource($visit->fresh()), 'Plant visit clarification raised');
        });
    }

    /* =========================================================
     * POST /api/plant-visits/{id}/mark-ready-for-exit
     * ========================================================= */
    public function markReadyForExit(
        MarkReadyForExitRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }

        return DB::transaction(function () use ($visit, $audit, $events) {
            $old = $audit->snapshotModel($visit);

            $visit->update([
                'visit_status' => PlantVisitStatus::READY_FOR_EXIT,
            ]);

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_READY_FOR_EXIT,
                $old,
                $audit->snapshotModel($visit->fresh()),
                null,
                null
            );
            $events->record(
                'plant_visit.ready_for_exit',
                $visit,
                "Plant visit {$visit->visit_no} ready for exit",
                ['to' => PlantVisitStatus::READY_FOR_EXIT],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new PlantVisitResource($visit->fresh()), 'Plant visit ready for exit');
        });
    }

    /* =========================================================
     * POST /api/plant-visits/{id}/close — clean close
     * ========================================================= */
    public function close(
        CloseVisitRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }
        if ($visit->visit_status === PlantVisitStatus::CLOSED) {
            return $this->error('Plant visit is already closed', 'ALREADY_CLOSED', 409);
        }
        // V1.6 §11.5 — clean close is not allowed while blocked or in
        // clarification; operator must resolve or use force-close.
        if (in_array($visit->visit_status, [PlantVisitStatus::BLOCKED, PlantVisitStatus::CLARIFICATION], true)) {
            return $this->error(
                'Cannot close a visit while blocked or in clarification. Resolve the blocker or use force-close.',
                'CLOSE_NOT_ALLOWED',
                409,
                ['status' => $visit->visit_status]
            );
        }

        $reason = $request->input('reason');
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($visit, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($visit);

            $now = now();
            $visit->update([
                'visit_status' => PlantVisitStatus::CLOSED,
                'closed_at' => $now,
                'exit_time' => $now,
                'closure_reason' => $reason,
            ]);

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_CLOSED,
                $old,
                $audit->snapshotModel($visit->fresh()),
                $reason,
                $reasonCode
            );
            $events->record(
                'plant_visit.closed',
                $visit,
                "Plant visit {$visit->visit_no} closed",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $this->success(new PlantVisitResource($visit->fresh()), 'Plant visit closed');
        });
    }

    /* =========================================================
     * POST /api/plant-visits/{id}/force-close
     * ========================================================= */
    public function forceClose(
        ForceCloseVisitRequest $request,
        $id,
        AuditLogger $audit,
        EventLogger $events
    ) {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }

        $reason = $request->validated()['reason'];
        $reasonCode = $request->input('reason_code');

        return DB::transaction(function () use ($visit, $reason, $reasonCode, $audit, $events) {
            $old = $audit->snapshotModel($visit);

            $now = now();
            $visit->update([
                'visit_status' => PlantVisitStatus::CLOSED,
                'closed_at' => $now,
                'exit_time' => $now,
                'closure_reason' => $reason,
            ]);

            $audit->record(
                $visit,
                AuditAction::PLANT_VISIT_FORCE_CLOSED,
                $old,
                $audit->snapshotModel($visit->fresh()),
                $reason,
                $reasonCode
            );
            $events->record(
                'plant_visit.force_closed',
                $visit,
                "Plant visit {$visit->visit_no} force-closed: {$reason}",
                ['reason' => $reason, 'reason_code' => $reasonCode],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $this->success(new PlantVisitResource($visit->fresh()), 'Plant visit force-closed');
        });
    }

    /* =========================================================
     * GET /api/plant-visits/{id}/events-audit
     * ========================================================= */
    public function eventsAudit(Request $request, $id)
    {
        $visit = PlantVisit::find($id);
        if (! $visit) {
            return $this->error('Plant visit not found', 'PLANT_VISIT_NOT_FOUND', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $entityType = $visit->getMorphClass();

        $audits = AuditLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $visit->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $events = EventLog::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $visit->id)
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
        ], 'Plant visit events & audit retrieved');
    }
}
