<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\DriverWorkflow\CompleteVisitRequest;
use App\Http\Requests\DriverWorkflow\ConfirmOrderRequest;
use App\Http\Requests\DriverWorkflow\SelectTaskRequest;
use App\Http\Requests\DriverWorkflow\SetTractorPlateRequest;
use App\Http\Requests\DriverWorkflow\SetTrailerCheckRequest;
use App\Http\Requests\DriverWorkflow\SetTrailerInfoRequest;
use App\Http\Resources\DriverWorkflowOrderResource;
use App\Services\DriverWorkflow\DriverWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * Driver post-login workflow (V6 §8) — kiosk endpoints.
 *
 * Identity in this layer is the driver `terminal_sessions.id` carried in
 * the URL. There is no Sanctum middleware; drivers aren't dashboard users.
 * The service-layer guard rejects unknown / logged-out sessions with
 * 404 SESSION_NOT_FOUND.
 *
 * Every method is a thin pass-through to DriverWorkflowService. The
 * controller's only job is shape: validate input, resolve the session,
 * delegate, project the response. All audit / event emission lives in the
 * service so a future scheduled-job or driver-app caller writes the same
 * trail.
 */
class DriverWorkflowController extends ApiController
{
    public function __construct(protected DriverWorkflowService $service) {}

    /* ============================================================
     * V6 §8.6 — Orders list for the driver's task
     * ============================================================ */

    public function orders(Request $request, string $id)
    {
        $session = $this->service->findActiveSession($id);
        if (! $session) {
            return $this->error(__('driver.workflow.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        // Task is a query param here; reuse the SelectTaskRequest's whitelist
        // via inline validation so the contract matches.
        $task = (string) $request->query('task', '');
        $v = Validator::make(['task' => $task], [
            'task' => ['required', 'string', Rule::in(DriverWorkflowService::TASKS_NEEDING_ORDER)],
        ]);
        if ($v->fails()) {
            return $this->error(
                'task query param must be one of: filling, pickup, print_exit.',
                'INVALID_TASK',
                422,
                ['errors' => $v->errors()->toArray()]
            );
        }

        if (! $session->driver_id) {
            // Defensive — an active session without a driver_id can't have
            // any assigned orders. Return an empty list rather than a 500.
            return $this->success([
                'data' => [],
                'task' => $task,
            ], __('driver.workflow.no_orders'));
        }

        $rows = $this->service->listOrdersForDriver($session, $task);

        return $this->success([
            'data' => DriverWorkflowOrderResource::collection(collect($rows)),
            'task' => $task,
        ], __('driver.workflow.orders_retrieved'));
    }

    /* ============================================================
     * V6 §8.2 — Trailer info (chip-card path)
     * ============================================================ */

    public function trailerInfo(SetTrailerInfoRequest $request, string $id)
    {
        $session = $this->service->findActiveSession($id);
        if (! $session) {
            return $this->error(__('driver.workflow.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        $data = $request->validated();
        $fresh = $this->service->setTrailer($session, [
            'situation' => $data['situation'],
            'trailerPlate' => $data['trailerPlate'] ?? null,
            'source' => 'chip',
        ]);

        return $this->success($this->sessionShape($fresh), __('driver.workflow.trailer_info_recorded'));
    }

    /* ============================================================
     * V6 §8.3 — Trailer check (TAN path)
     * ============================================================ */

    public function trailerCheck(SetTrailerCheckRequest $request, string $id)
    {
        $session = $this->service->findActiveSession($id);
        if (! $session) {
            return $this->error(__('driver.workflow.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        $data = $request->validated();
        $hasTrailer = filter_var($data['hasTrailer'], FILTER_VALIDATE_BOOLEAN);

        $fresh = $this->service->setTrailer($session, [
            // Normalise to the same `situation` vocabulary used by §8.2 so
            // downstream readers don't branch on path.
            'situation' => $hasTrailer ? 'chip_attached' : 'none',
            'trailerPlate' => $hasTrailer ? ($data['trailerPlate'] ?? null) : null,
            'source' => 'tan',
        ]);

        return $this->success($this->sessionShape($fresh), __('driver.workflow.trailer_check_recorded'));
    }

    /* ============================================================
     * V6 §8.4 — Tractor / Machine plate
     * ============================================================ */

    public function tractorPlate(SetTractorPlateRequest $request, string $id)
    {
        $session = $this->service->findActiveSession($id);
        if (! $session) {
            return $this->error(__('driver.workflow.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        $fresh = $this->service->setTractorPlate($session, $request->validated()['plate']);

        return $this->success($this->sessionShape($fresh), __('driver.workflow.tractor_plate_recorded'));
    }

    /* ============================================================
     * V6 §8.5 — Task selection
     * ============================================================ */

    public function task(SelectTaskRequest $request, string $id)
    {
        $session = $this->service->findActiveSession($id);
        if (! $session) {
            return $this->error(__('driver.workflow.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        $task = $request->validated()['task'];
        $fresh = $this->service->selectTask($session, $task);

        return $this->success(
            array_merge(
                $this->sessionShape($fresh),
                [
                    'task' => $task,
                    'nextRoute' => $this->service->nextRouteForTask($task),
                ]
            ),
            __('driver.workflow.task_selected')
        );
    }

    /* ============================================================
     * V6 §8.7 — Confirm selected order
     * ============================================================ */

    public function confirmOrder(ConfirmOrderRequest $request, string $id)
    {
        $session = $this->service->findActiveSession($id);
        if (! $session) {
            return $this->error(__('driver.workflow.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        $data = $request->validated();

        if (! $session->driver_id) {
            return $this->error(
                __('driver.workflow.session_no_driver'),
                'SESSION_HAS_NO_DRIVER',
                409
            );
        }

        $order = $this->service->confirmOrder($session, $data['task'], $data['orderId']);

        if (! $order) {
            return $this->error(
                __('driver.workflow.order_not_eligible'),
                'ORDER_NOT_ELIGIBLE',
                409
            );
        }

        return $this->success([
            'order' => $this->service->orderRow($order),
            'task' => $data['task'],
            'nextRoute' => $this->service->nextRouteAfterOrder($data['task']),
            'fillingTan' => $this->service->fillingTanFor($order),
        ], __('driver.workflow.order_confirmed'));
    }

    /* ============================================================
     * V6 §8.8 — Bay-line assignment (read-only)
     * ============================================================ */

    public function bayLineAssignment(string $id)
    {
        $session = $this->service->findActiveSession($id);
        if (! $session) {
            return $this->error(__('driver.workflow.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        $payload = $this->service->bayLineAssignment($session);

        return $this->success($payload, __('driver.workflow.bay_line_assignment'));
    }

    /* ============================================================
     * V6 §8.8b — Parking assignment (read-only)
     *
     * Sibling of bayLineAssignment for the `task=parking` workflow
     * leg. The kiosk calls this after the driver picks `task=parking`
     * at /terminal/task and lands on /terminal/done?task=parking.
     * Read-only — no occupancy mutation here; the dashboard owns
     * parkings.slot_status lifecycle.
     * ============================================================ */

    public function parkingAssignment(string $id)
    {
        $session = $this->service->findActiveSession($id);
        if (! $session) {
            return $this->error(__('driver.workflow.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        $payload = $this->service->parkingAssignment($session);

        return $this->success($payload, __('driver.workflow.parking_assignment'));
    }

    /* ============================================================
     * V6 §8.9 — Print delivery note
     * ============================================================ */

    public function printDeliveryNote(string $id)
    {
        $session = $this->service->findActiveSession($id);
        if (! $session) {
            return $this->error(__('driver.workflow.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        if (! $session->order_id) {
            return $this->error(
                __('driver.workflow.no_order_confirmed'),
                'NO_ORDER_CONFIRMED',
                409
            );
        }

        $payload = $this->service->printDeliveryNote($session);

        return $this->success($payload, __('driver.workflow.delivery_note_printed'));
    }

    /* ============================================================
     * V6 §8.10 — Complete workflow
     * ============================================================ */

    public function complete(CompleteVisitRequest $request, string $id)
    {
        $session = $this->service->findActiveSession($id);
        if (! $session) {
            return $this->error(__('driver.workflow.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        $payload = $this->service->complete($session, $request->validated()['task']);

        return $this->success($payload, __('driver.workflow.workflow_completed'));
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    /**
     * Compact JSON projection of a session used by mutating endpoints.
     * Mirrors TerminalAuthController::session() shape so the FE can drop
     * the response into the same store slice it already manages.
     */
    protected function sessionShape($session): array
    {
        return [
            'id' => $session->id,
            'sessionNo' => $session->session_no,
            'state' => $session->session_state,
            'currentScreen' => $session->current_screen,
            'driverId' => $session->driver_id,
            'driverName' => $session->driver_name,
            'driverCode' => $session->driver_code,
            'trailerLabel' => $session->trailer_label,
            'orderId' => $session->order_id,
            'orderNo' => $session->order_no,
            'lastActivityAt' => $session->last_activity_at?->toIso8601String(),
            'notes' => $session->notes,
        ];
    }
}
