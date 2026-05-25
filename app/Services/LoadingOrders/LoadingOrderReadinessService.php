<?php

namespace App\Services\LoadingOrders;

use App\Enums\AssignmentState;
use App\Enums\DriverTask;
use App\Enums\LoadingOrderStatus;
use App\Models\LoadingOrder;

/**
 * Derives Loading Order readiness state per Loading Orders UX Spec V2.2 §4.4.
 *
 * Readiness is calculated; there is NO separate "Release to Driver" action.
 *
 * Resolution order (first match wins):
 *   1. CANCELLED — sticky once `cancelled_at` is set.
 *   2. BLOCKED   — sticky once `blocked_at` is set and not unblocked.
 *   3. IN_PROGRESS — when `active_plant_visit_id` or `active_loading_operation_id` is bound.
 *   4. COMPLETED — when an execution lock was released cleanly (no active operation, has been in-progress before).
 *   5. NEEDS_ASSIGNMENT — when required driver or trailer is missing.
 *   6. READY — when required assignments are complete and order data is valid.
 *   7. DRAFT — fallback for incomplete order data.
 *
 * Driver/trailer requirement depends on `task_flow` — see DriverTask::requiresDriver()
 * and DriverTask::requiresTrailer(). When `task_flow` is null we default to
 * "driver required, trailer required" (safer for parking/loading tasks).
 */
class LoadingOrderReadinessService
{
    public function deriveStatus(LoadingOrder $order): string
    {
        if (! is_null($order->cancelled_at)) {
            return LoadingOrderStatus::CANCELLED;
        }
        if (! is_null($order->blocked_at)) {
            return LoadingOrderStatus::BLOCKED;
        }
        if ($order->active_plant_visit_id || $order->active_loading_operation_id) {
            return LoadingOrderStatus::IN_PROGRESS;
        }
        if ($order->status === LoadingOrderStatus::COMPLETED) {
            return LoadingOrderStatus::COMPLETED;
        }
        if (! $this->hasRequiredOrderData($order)) {
            return LoadingOrderStatus::DRAFT;
        }
        if ($this->driverAssignmentState($order) === AssignmentState::NOT_ASSIGNED) {
            return LoadingOrderStatus::NEEDS_ASSIGNMENT;
        }
        if ($this->trailerAssignmentState($order) === AssignmentState::NOT_ASSIGNED) {
            return LoadingOrderStatus::NEEDS_ASSIGNMENT;
        }
        return LoadingOrderStatus::READY;
    }

    public function driverAssignmentState(LoadingOrder $order): string
    {
        if ($order->assigned_driver_id) {
            return AssignmentState::ASSIGNED;
        }
        $task = $order->task_flow;
        if ($task && ! DriverTask::requiresDriver($task)) {
            return AssignmentState::NOT_REQUIRED;
        }
        return AssignmentState::NOT_ASSIGNED;
    }

    public function trailerAssignmentState(LoadingOrder $order): string
    {
        if ($order->assigned_trailer_id) {
            return AssignmentState::ASSIGNED;
        }
        $task = $order->task_flow;
        if ($task && ! DriverTask::requiresTrailer($task)) {
            return AssignmentState::NOT_REQUIRED;
        }
        return AssignmentState::NOT_ASSIGNED;
    }

    /**
     * Refresh the persisted `status` snapshot to match the derived value.
     * Does NOT save — caller decides when to flush.
     */
    public function refresh(LoadingOrder $order): LoadingOrder
    {
        $order->status = $this->deriveStatus($order);
        return $order;
    }

    protected function hasRequiredOrderData(LoadingOrder $order): bool
    {
        return $order->customer_id
            && $order->product_quality
            && $order->target_quantity
            && $order->unit;
    }
}
