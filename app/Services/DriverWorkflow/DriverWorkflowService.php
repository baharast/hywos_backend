<?php

namespace App\Services\DriverWorkflow;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\GateTerminalSessionState;
use App\Enums\ParkingSlotStatus;
use App\Enums\TanPurpose;
use App\Enums\TanUsageState;
use App\Models\AuthMedium;
use App\Models\Driver;
use App\Models\LoadingOrder;
use App\Models\Parking;
use App\Models\TerminalSession;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use App\Services\LoadingOrders\OrderProvisioningService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single source of truth for the driver post-login workflow (V6 §8).
 *
 * The kiosk walks the driver through trailer → tractor → task → order →
 * bay-line / delivery-note → done. Each step mutates the driver's
 * `terminal_sessions` row and writes one audit + one event row. The
 * controller is a thin HTTP shell; ALL business logic lives here.
 *
 * Schema note (MVP, no new migration): the existing `terminal_sessions`
 * table carries `trailer_id`, `trailer_label`, `order_id`, `order_no` and
 * `notes`. It does NOT have dedicated columns for trailer situation,
 * tractor plate, task or delivery-note ref — those are tagged on the
 * `notes` text column with `[ISO_TS] key=value` lines. The pattern matches
 * the existing TerminalAuthService usage (login_method, language_changed).
 * When the workflow needs first-class columns later, replace the tagged
 * lines with proper fields; the audit log is the durable record either way.
 */
class DriverWorkflowService
{
    /**
     * V6 §8.10 — workflow `task` values the FE may send.
     */
    public const TASKS = ['filling', 'pickup', 'parking', 'print_exit', 'exit'];

    /**
     * V6 §8.5 — tasks that route forward to the orders list (vs straight
     * to the done page for parking/exit).
     */
    public const TASKS_NEEDING_ORDER = ['filling', 'pickup', 'print_exit'];

    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events,
        protected OrderProvisioningService $provisioning,
    ) {}

    /* ============================================================
     * Session guard
     * ============================================================ */

    /**
     * Resolve an active driver session by id. Returns null when the row is
     * missing or has been logged out (state=idle) — the controller maps
     * that to 404 SESSION_NOT_FOUND. State=denied sessions also count as
     * "not an active workflow" — those are failed login attempts.
     */
    public function findActiveSession(string $id): ?TerminalSession
    {
        $session = TerminalSession::find($id);
        if (! $session) {
            return null;
        }
        if (in_array($session->session_state, [
            GateTerminalSessionState::IDLE,
            GateTerminalSessionState::DENIED,
        ], true)) {
            return null;
        }
        return $session;
    }

    /* ============================================================
     * V6 §8.2 / §8.3 — Trailer info / Trailer check
     * ============================================================ */

    /**
     * Persist the trailer choice. Both the chip-card path (situation =
     * chip_present / chip_attached / none) and the TAN path (hasTrailer
     * boolean + optional plate) land here through a normalised shape.
     *
     * @param  array{situation: string, trailerPlate?: ?string, source: 'chip'|'tan'}  $payload
     */
    public function setTrailer(TerminalSession $session, array $payload): TerminalSession
    {
        $situation = $payload['situation'];                 // chip_present | chip_attached | none
        $plate = $payload['trailerPlate'] ?? null;
        $source = $payload['source'];                       // chip | tan

        return DB::transaction(function () use ($session, $situation, $plate, $source) {
            $old = $this->audit->snapshotModel($session);

            // Plate (when provided) goes into the existing trailer_label
            // column; the no-trailer choice clears it so the column stays
            // truthful about the current step's outcome.
            $session->trailer_label = $plate ? strtoupper(trim($plate)) : null;

            $session->notes = $this->appendNote(
                $session->notes,
                "trailer_set source={$source} situation={$situation}"
                . ($plate ? " plate=" . strtoupper(trim($plate)) : '')
            );
            $session->last_activity_at = now();
            $session->save();

            $fresh = $session->fresh();

            $this->audit->record(
                $session,
                AuditAction::TERMINAL_WORKFLOW_TRAILER_SET,
                $old,
                $this->audit->snapshotModel($fresh),
                null,
                null
            );
            $this->events->record(
                'terminal.workflow.trailer_set',
                $session,
                "Driver {$session->driver_code} set trailer ({$situation}) at session {$session->session_no}",
                [
                    'source' => $source,
                    'situation' => $situation,
                    'trailer_plate' => $plate,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $fresh;
        });
    }

    /* ============================================================
     * V6 §8.4 — Tractor / Machine plate
     * ============================================================ */

    public function setTractorPlate(TerminalSession $session, string $plate): TerminalSession
    {
        $plate = strtoupper(trim($plate));

        return DB::transaction(function () use ($session, $plate) {
            $old = $this->audit->snapshotModel($session);

            // No dedicated column — tag onto notes. We also keep the most
            // recent value queryable by storing it on a single canonical
            // line so future code can grep it out without parsing the full
            // notes blob.
            $session->notes = $this->appendNote($session->notes, "tractor_plate={$plate}");
            $session->last_activity_at = now();
            $session->save();

            $fresh = $session->fresh();

            $this->audit->record(
                $session,
                AuditAction::TERMINAL_WORKFLOW_TRACTOR_SET,
                $old,
                $this->audit->snapshotModel($fresh),
                null,
                null
            );
            $this->events->record(
                'terminal.workflow.tractor_set',
                $session,
                "Driver {$session->driver_code} set tractor plate {$plate} at session {$session->session_no}",
                ['tractor_plate' => $plate],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $fresh;
        });
    }

    /* ============================================================
     * V6 §8.5 — Task selection
     * ============================================================ */

    public function selectTask(TerminalSession $session, string $task): TerminalSession
    {
        return DB::transaction(function () use ($session, $task) {
            $old = $this->audit->snapshotModel($session);

            $session->notes = $this->appendNote($session->notes, "task={$task}");
            $session->last_activity_at = now();
            $session->save();

            $fresh = $session->fresh();

            $this->audit->record(
                $session,
                AuditAction::TERMINAL_WORKFLOW_TASK_SELECTED,
                $old,
                $this->audit->snapshotModel($fresh),
                null,
                null
            );
            $this->events->record(
                'terminal.workflow.task_selected',
                $session,
                "Driver {$session->driver_code} selected task '{$task}' at session {$session->session_no}",
                ['task' => $task],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $fresh;
        });
    }

    public function nextRouteForTask(string $task): string
    {
        return in_array($task, self::TASKS_NEEDING_ORDER, true)
            ? "/terminal/orders?task={$task}"
            : "/terminal/done?task={$task}";
    }

    public function nextRouteAfterOrder(string $task): string
    {
        return $task === 'print_exit'
            ? '/terminal/delivery-note?task=print_exit'
            : "/terminal/bay-line?task={$task}";
    }

    /* ============================================================
     * V6 §8.6 — Orders list for this driver, filtered by task
     * ============================================================ */

    /**
     * @return array<int, array<string, mixed>>  shaped rows ready for resource collection
     */
    public function listOrdersForDriver(TerminalSession $session, string $task): array
    {
        $orders = $this->driverOrdersQuery($session, $task)
            ->orderByRaw('CASE WHEN planned_window_start IS NULL THEN 1 ELSE 0 END, planned_window_start ASC')
            ->orderBy('order_no')
            ->get();

        // V6 §8.6 — exactly one row carries `recommended: true`. Earliest
        // planned window wins; on a tie, the order_no ordering picks
        // deterministically. Empty list → no recommendation.
        $recommendedId = $orders->first()?->id;

        return $orders->map(fn (LoadingOrder $o) => $this->orderRow($o, $recommendedId))->all();
    }

    /**
     * Order-row shape used by both the list endpoint and the bay-line /
     * confirm-order responses. Kept centrally so the FE sees a stable
     * representation regardless of which endpoint surfaced it.
     */
    public function orderRow(LoadingOrder $o, ?string $recommendedId = null): array
    {
        return [
            'orderId' => $o->id,
            'sapNumber' => $o->sap_reference,
            'orderNo' => $o->order_no,
            'customer' => $o->customer_name,
            'productQuality' => $o->product_quality,
            'targetKg' => $o->target_quantity !== null ? (float) $o->target_quantity : null,
            'unit' => $o->unit ?? 'kg',
            'bayLine' => $o->active_loading_operation_id
                ? $this->bayLineRefFor($o)
                : null,
            'plannedTime' => $o->planned_window_start?->toIso8601String(),
            'recommended' => $recommendedId !== null && $o->id === $recommendedId,
        ];
    }

    /**
     * The same eligibility predicate used by both the list endpoint and the
     * confirm-order endpoint (so the FE can't confirm an order it wasn't
     * shown).
     */
    public function driverOrdersQuery(TerminalSession $session, string $task)
    {
        $q = LoadingOrder::query()->where('assigned_driver_id', $session->driver_id);

        // V6 §8.6 — each task config narrows the relevant status set. We
        // stay deliberately permissive in MVP: any non-completed,
        // non-cancelled order assigned to the driver is fair game, with
        // task-specific filters layered on top. When the operational
        // status enum grows finer-grained labels per task, tighten here.
        $q->whereNotIn('status', ['completed', 'cancelled']);

        switch ($task) {
            case 'filling':
                // Trailer-filling orders are the ones not yet in pickup-only
                // mode. Include draft so the operator-prepared orders show
                // up immediately after assignment.
                $q->whereIn('status', ['draft', 'needs_assignment', 'ready', 'in_progress']);
                break;
            case 'pickup':
                // Pre-filled orders ready to leave — the simplest proxy is
                // `ready`. `in_progress` covers cases where the visit was
                // already opened for the same driver.
                $q->whereIn('status', ['ready', 'in_progress']);
                break;
            case 'print_exit':
                // Driver is finishing — orders whose documents may still
                // need printing. `in_progress` is the natural state; we
                // also include `ready` so the kiosk doesn't 404 when the
                // operator skipped a step.
                $q->whereIn('status', ['ready', 'in_progress']);
                break;
        }

        return $q;
    }

    /* ============================================================
     * V6 §8.7 — Confirm selected order
     * ============================================================ */

    /**
     * Validate the chosen orderId against the driver's eligible list for
     * the given task and pin it to the session. Returns null when the
     * order doesn't belong to this driver for that task.
     */
    public function confirmOrder(TerminalSession $session, string $task, string $orderId): ?LoadingOrder
    {
        /** @var LoadingOrder|null $order */
        $order = $this->driverOrdersQuery($session, $task)
            ->where('id', $orderId)
            ->first();

        if (! $order) {
            return null;
        }

        DB::transaction(function () use ($session, $order, $task) {
            $old = $this->audit->snapshotModel($session);

            $session->order_id = $order->id;
            $session->order_no = $order->order_no;
            $session->notes = $this->appendNote(
                $session->notes,
                "order_confirmed task={$task} order_no={$order->order_no}"
            );
            $session->last_activity_at = now();
            $session->save();

            $fresh = $session->fresh();

            $this->audit->record(
                $session,
                AuditAction::TERMINAL_WORKFLOW_ORDER_CONFIRMED,
                $old,
                $this->audit->snapshotModel($fresh),
                null,
                null
            );
            $this->events->record(
                'terminal.workflow.order_confirmed',
                $session,
                "Driver {$session->driver_code} confirmed order {$order->order_no} (task={$task}) at session {$session->session_no}",
                [
                    'task' => $task,
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            // Issue (or fetch the existing) filling-station TAN for
            // tasks that route forward to the bayline. parking / exit
            // tasks don't need one. Best-effort: failure logs a
            // warning and does NOT roll back the confirmation.
            if ($task === 'filling' && $session->driver_id) {
                $driver = Driver::find($session->driver_id);
                if ($driver !== null) {
                    $tan = $this->provisioning->provisionFillingTanFor($order, $driver);
                    if ($tan !== null) {
                        $tan->update([
                            'related_terminal_session_id' => $session->id,
                        ]);
                    }
                }
            }
        });

        return $order;
    }

    /**
     * Compact FE shape of the filling TAN attached to a confirmed
     * order. Returns null when the driver hasn't confirmed a filling
     * task at the terminal yet.
     *
     * @return array<string,mixed>|null
     */
    public function fillingTanFor(LoadingOrder $order): ?array
    {
        $tan = AuthMedium::query()
            ->where('order_id', $order->id)
            ->where('tan_purpose', TanPurpose::FILLING)
            ->where('status', 'active')
            ->orderByDesc('issued_at')
            ->first();

        if ($tan === null) return null;

        $usage = $tan->usage_state ?? TanUsageState::UNUSED;

        return [
            'id' => $tan->id,
            'reference' => $tan->tan_reference,
            'display' => $tan->display_identifier ?? $tan->tan_masked,
            'purpose' => [
                'value' => TanPurpose::FILLING,
                'label' => TanPurpose::label(TanPurpose::FILLING),
                'tone' => TanPurpose::tone(TanPurpose::FILLING),
            ],
            'usageState' => [
                'value' => $usage,
                'label' => TanUsageState::label($usage),
                'tone' => TanUsageState::tone($usage),
            ],
            'isSingleUse' => (bool) $tan->is_single_use,
            'issuedAt' => $tan->issued_at?->toIso8601String(),
            'expiresAt' => $tan->expires_at?->toIso8601String(),
        ];
    }

    /* ============================================================
     * V6 §8.8 — Bay line / pickup location
     * ============================================================ */

    /**
     * Resolve the bay line currently assigned to the session's confirmed
     * order. Returns a shape suitable for the FE bay-line page; when no
     * order is confirmed or the order has no active loading operation,
     * returns null + a reason string.
     *
     * @return array{bayLine: ?array{code: string, label: ?string},
     *               order: ?array<string, mixed>,
     *               reason: ?string}
     */
    public function bayLineAssignment(TerminalSession $session): array
    {
        if (! $session->order_id) {
            return [
                'bayLine' => null,
                'order' => null,
                'reason' => 'No order confirmed for this session yet.',
            ];
        }

        $order = LoadingOrder::find($session->order_id);
        if (! $order) {
            return [
                'bayLine' => null,
                'order' => null,
                'reason' => 'Confirmed order no longer exists.',
            ];
        }

        $bayLine = $this->bayLineRefFor($order);

        return [
            'bayLine' => $bayLine,
            'order' => $this->orderRow($order),
            'fillingTan' => $this->fillingTanFor($order),
            'reason' => $bayLine === null
                ? 'No bay line is assigned to this order yet.'
                : null,
        ];
    }

    /* ============================================================
     * V6 §8.8b — Parking-assignment (read-only)
     *
     * Mirror of bayLineAssignment for the `task=parking` workflow leg.
     * Returns the parking slot the driver should drive to. Two slots
     * exist on the site (PARKING-1 / PARKING-2 — see ParkingSeeder),
     * matching the V2.1 §0.2 two-slot board the dashboard already
     * tracks. Selection rule is the simplest correct one: first FREE
     * slot ordered by code (deterministic; PARKING-1 wins ties so
     * demo behaviour is repeatable).
     *
     * READ-ONLY: this method does NOT mutate parkings.slot_status.
     * The physical park happens off-system; the dashboard / operator
     * owns the reservation lifecycle (parkings/{id}/reserve etc.).
     * If a future revision wants kiosk-side reservation, fold it into
     * a sibling POST endpoint — do not retrofit it here.
     * ============================================================ */

    /**
     * @return array{
     *   slot: ?array{code: string, label: ?string, status: string},
     *   trailer: ?array{id: ?string, label: ?string, plate: ?string},
     *   reason: ?string
     * }
     */
    public function parkingAssignment(TerminalSession $session): array
    {
        // Trailer echo for the kiosk's "Park trailer X in Parking Y"
        // copy line. Free — already on the session row.
        $trailer = ! empty($session->trailer_id) || ! empty($session->trailer_label)
            ? [
                'id' => $session->trailer_id,
                'label' => $session->trailer_label,
                'plate' => $session->trailer_plate ?? null,
            ]
            : null;

        $slot = $this->pickFreeParkingSlot();

        if ($slot === null) {
            // Distinguish "all slots taken" from "no parkings configured" so
            // the kiosk shows the right copy.
            $totalConfigured = Parking::query()->count();
            $reason = $totalConfigured === 0
                ? 'No parking slots are configured on this site. Please ask the operator.'
                : 'Both parking slots are occupied. Please wait for an operator.';

            return [
                'slot' => null,
                'trailer' => $trailer,
                'reason' => $reason,
            ];
        }

        return [
            'slot' => [
                'code' => $slot->code,
                'label' => $slot->name,
                'status' => $slot->slot_status,
            ],
            'trailer' => $trailer,
            'reason' => null,
        ];
    }

    /**
     * Selection rule: first FREE slot ordered by code. PARKING-1 wins
     * over PARKING-2 when both are free. Returns null when no slot is
     * free (or when no parkings exist at all).
     */
    protected function pickFreeParkingSlot(): ?Parking
    {
        return Parking::query()
            ->where('slot_status', ParkingSlotStatus::FREE)
            ->orderBy('code')
            ->first();
    }

    /* ============================================================
     * V6 §8.9 — Delivery note printed
     * ============================================================ */

    /**
     * Record that a delivery-note print was triggered. No real printer
     * integration in MVP — we mint a synthetic reference (DN-{order_no}-
     * {ts}) and log it so the audit trail still tells the story.
     *
     * @return array{printedAt: string, deliveryNoteRef: string}
     */
    public function printDeliveryNote(TerminalSession $session): array
    {
        if (! $session->order_id) {
            // Caller handles the 409; service signals via an exception-ish
            // sentinel so we don't bury a print event for nothing.
            return ['printedAt' => '', 'deliveryNoteRef' => ''];
        }

        $order = LoadingOrder::find($session->order_id);
        $ref = 'DN-' . ($order?->order_no ?? Str::upper(Str::substr($session->id, 0, 8)))
            . '-' . now()->format('His');
        $printedAt = now()->toIso8601String();

        DB::transaction(function () use ($session, $ref, $printedAt, $order) {
            $old = $this->audit->snapshotModel($session);

            $session->notes = $this->appendNote(
                $session->notes,
                "delivery_note_printed ref={$ref} order_no=" . ($order->order_no ?? '?')
            );
            $session->last_activity_at = now();
            $session->save();

            $fresh = $session->fresh();

            $this->audit->record(
                $session,
                // The spec asks us to reuse DOCUMENT_QUEUED_FOR_PRINT for
                // parity with the documents module audit trail. We ALSO
                // emit TERMINAL_WORKFLOW_DELIVERY_NOTE_PRINTED on the event
                // log so the kiosk timeline is complete.
                AuditAction::DOCUMENT_QUEUED_FOR_PRINT,
                $old,
                $this->audit->snapshotModel($fresh),
                "Delivery note {$ref} printed from kiosk",
                null
            );
            $this->events->record(
                'terminal.workflow.delivery_note_printed',
                $session,
                "Delivery note {$ref} printed for {$session->session_no}"
                    . ($order ? " (order {$order->order_no})" : ''),
                [
                    'delivery_note_ref' => $ref,
                    'printed_at' => $printedAt,
                    'order_id' => $session->order_id,
                    'order_no' => $session->order_no,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );
        });

        return [
            'printedAt' => $printedAt,
            'deliveryNoteRef' => $ref,
        ];
    }

    /* ============================================================
     * V6 §8.10 — Workflow recorded
     * ============================================================ */

    /**
     * Finalise the visit. Flips session_state → idle and clears
     * current_screen so the gate monitor stops surfacing the row on the
     * active touchpoint card.
     *
     * @return array{sessionNo: string, completedAt: string, task: string}
     */
    public function complete(TerminalSession $session, string $task): array
    {
        $completedAt = now()->toIso8601String();

        DB::transaction(function () use ($session, $task, $completedAt) {
            $old = $this->audit->snapshotModel($session);

            $session->session_state = GateTerminalSessionState::IDLE;
            $session->current_screen = null;
            $session->notes = $this->appendNote(
                $session->notes,
                "workflow_completed task={$task}"
            );
            $session->last_activity_at = now();
            $session->save();

            $fresh = $session->fresh();

            $this->audit->record(
                $session,
                AuditAction::TERMINAL_WORKFLOW_COMPLETED,
                $old,
                $this->audit->snapshotModel($fresh),
                "Workflow completed (task={$task})",
                null
            );
            $this->events->record(
                'terminal.workflow.completed',
                $session,
                "Driver {$session->driver_code} completed workflow ({$task}) at session {$session->session_no}",
                [
                    'task' => $task,
                    'completed_at' => $completedAt,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );
        });

        return [
            'sessionNo' => $session->session_no,
            'completedAt' => $completedAt,
            'task' => $task,
            'driver' => $this->driverSnapshot($session),
            'trailer' => $this->trailerSnapshot($session),
        ];
    }

    /* ============================================================
     * Internals
     * ============================================================ */

    /**
     * Append one tagged line `[ISO] payload` to the session.notes blob.
     * Preserves prior content; the kiosk uses this for the audit narrative
     * and TerminalAuthService::changeLanguage() uses the same convention.
     */
    protected function appendNote(?string $existing, string $line): string
    {
        $stamp = now()->toIso8601String();
        $prefix = $existing ? rtrim($existing) . "\n" : '';
        return $prefix . "[{$stamp}] {$line}";
    }

    /**
     * Driver recap for the exit-summary screen (§12.3). Reads the
     * denormalised driver columns captured on the session at login.
     * Returns null when the session never bound a driver.
     *
     * @return array{name: ?string, code: ?string}|null
     */
    protected function driverSnapshot(TerminalSession $session): ?array
    {
        if (! $session->driver_name && ! $session->driver_code) {
            return null;
        }

        return [
            'name' => $session->driver_name,
            'code' => $session->driver_code,
        ];
    }

    /**
     * Trailer recap for the exit-summary screen (§12.3).
     *  - label     : the `trailer_label` column (uppercased plate, or null).
     *  - chip      : trailer chip tag — not tracked on the session yet, so null.
     *  - situation : chip_present | chip_attached | none, recovered from the
     *                latest `trailer_set` notes line; 'none' when the driver
     *                never reached the trailer step.
     *
     * @return array{label: ?string, chip: ?string, situation: string}
     */
    protected function trailerSnapshot(TerminalSession $session): array
    {
        return [
            'label' => $session->trailer_label,
            'chip' => null,
            'situation' => $this->latestTrailerSituation($session->notes),
        ];
    }

    /**
     * Recover the last-recorded trailer situation from the tagged `notes`
     * lines (`[ISO] trailer_set source=… situation=… [plate=…]`, see §3).
     * The last matching line wins; 'none' when no trailer_set line exists.
     */
    protected function latestTrailerSituation(?string $notes): string
    {
        if (! $notes) {
            return 'none';
        }

        $situation = 'none';
        foreach (preg_split('/\r?\n/', $notes) as $line) {
            if (preg_match('/\btrailer_set\b.*\bsituation=(\w+)/', $line, $matches)) {
                $situation = $matches[1];
            }
        }

        return $situation;
    }

    /**
     * Resolve the bay-line code/name a given LoadingOrder is using.
     *
     * Two sources of truth, in priority order:
     *   1. ACTIVE — when an `active_loading_operation_id` is set, the
     *      operation row carries the canonical `bay_line_id` (the slot
     *      Loading Control actually reserved). Reflects real-time
     *      reservation, not planning.
     *   2. PLANNED — falls back to `loading_orders.assigned_bay_line_id`
     *      populated at order-create time by OrderProvisioningService
     *      (or seeded into SAP-source rows by LoadingOrderSeeder). The
     *      kiosk shows this so the driver knows where to go BEFORE
     *      Loading Control reserves the bay.
     *
     * Returns a shape with `code`, `label`, and `source` so the FE can
     * tell which of the two it got — useful when the planned bay
     * differs from the eventual reservation. Returns null when neither
     * source has data.
     *
     * Soft FK: select-by-id, no joins, no exceptions on missing rows.
     *
     * @return array{code:string,label:?string,source:string}|null
     */
    protected function bayLineRefFor(LoadingOrder $order): ?array
    {
        // 1) Active operation wins.
        if ($order->active_loading_operation_id) {
            $op = DB::table('loading_operations')
                ->where('id', $order->active_loading_operation_id)
                ->first(['bay_line_id']);
            if ($op && ! empty($op->bay_line_id)) {
                $bayLine = DB::table('baylines')
                    ->where('id', $op->bay_line_id)
                    ->first(['code', 'name']);
                if ($bayLine) {
                    return [
                        'code' => $bayLine->code,
                        'label' => $bayLine->name,
                        'source' => 'active_operation',
                    ];
                }
            }
        }

        // 2) Planned bayline from the order row (set at create time).
        //    The denormalized columns are usually enough; we fall back
        //    to looking the row up only when code/name are missing.
        if (! empty($order->assigned_bay_line_id)) {
            $code = $order->assigned_bay_line_code;
            $name = $order->assigned_bay_line_name;
            if (empty($code) || empty($name)) {
                $bayLine = DB::table('baylines')
                    ->where('id', $order->assigned_bay_line_id)
                    ->first(['code', 'name']);
                if ($bayLine) {
                    $code = $code ?: $bayLine->code;
                    $name = $name ?: $bayLine->name;
                }
            }
            if (! empty($code)) {
                return [
                    'code' => $code,
                    'label' => $name,
                    'source' => 'planned',
                ];
            }
        }

        return null;
    }
}
