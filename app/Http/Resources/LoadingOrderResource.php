<?php

namespace App\Http\Resources;

use App\Enums\AssignmentState;
use App\Enums\DriverTask;
use App\Enums\LoadingOrderSource;
use App\Enums\LoadingOrderStatus;
use App\Models\LoadingOrder;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Loading Order resource — matches §4.2 / §8 of api/15-loading-orders.md.
 *
 * Notes:
 * - driver and trailer assignment states are SEPARATE objects (V2.2 §4.2/§4.3).
 * - status is the cached derived value (LoadingOrderReadinessService keeps
 *   the column in sync via the model's saving hook).
 * - lockedFields appears only when the order is is_sap_owned=true so the FE
 *   can disable inputs proactively.
 */
class LoadingOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var LoadingOrder $order */
        $order = $this->resource;

        $source = $order->source ?? LoadingOrderSource::MANUAL;
        $status = $order->status ?? LoadingOrderStatus::DRAFT;
        $taskFlow = $order->task_flow;

        $payload = [
            'id' => $order->id,
            'orderNo' => $order->order_no,
            'source' => [
                'value' => $source,
                'label' => LoadingOrderSource::label($source),
            ],
            'sapReference' => $order->sap_reference,
            'externalReference' => $order->external_reference,

            'customer' => [
                'id' => $order->customer_id,
                'name' => $order->customer_name,
            ],
            'carrier' => [
                'id' => $order->carrier_id,
                'name' => $order->carrier_name,
            ],

            'productQuality' => $order->product_quality,
            'targetQuantity' => $order->target_quantity !== null
                ? (string) $order->target_quantity
                : null,
            'unit' => $order->unit,

            'plannedWindow' => [
                'start' => $order->planned_window_start?->toIso8601String(),
                'end' => $order->planned_window_end?->toIso8601String(),
            ],

            'driver' => [
                'id' => $order->assigned_driver_id,
                'name' => $order->assigned_driver_name,
                'code' => $order->assigned_driver_code,
                'assignmentState' => $this->stateObject($order->driver_assignment_state),
            ],
            'trailer' => [
                'id' => $order->assigned_trailer_id,
                'label' => $order->assigned_trailer_label,
                'plate' => $order->assigned_trailer_plate,
                'assignmentState' => $this->stateObject($order->trailer_assignment_state),
            ],

            'taskFlow' => $taskFlow ? [
                'value' => $taskFlow,
                'label' => DriverTask::label($taskFlow),
            ] : null,
            'currentStep' => $order->current_step,

            'documents' => [
                'requiresCertificate' => (bool) $order->requires_certificate,
                'requiresDeliveryNote' => (bool) $order->requires_delivery_note,
                'requiresQmDocument' => (bool) $order->requires_qm_document,
            ],

            'status' => [
                'value' => $status,
                'label' => LoadingOrderStatus::label($status),
                'tone' => LoadingOrderStatus::tone($status),
            ],

            'blocking' => $order->blocked_at ? [
                'reason' => $order->blocking_reason,
                'reasonCode' => $order->blocking_reason_code,
                'blockedAt' => $order->blocked_at?->toIso8601String(),
                'blockedBy' => $order->blocked_by_user_id,
            ] : null,

            'cancellation' => $order->cancelled_at ? [
                'reason' => $order->cancellation_reason,
                'reasonCode' => $order->cancellation_reason_code,
                'cancelledAt' => $order->cancelled_at?->toIso8601String(),
                'cancelledBy' => $order->cancelled_by_user_id,
            ] : null,

            'isLockedByExecution' => (bool) $order->is_locked_by_execution,
            'activePlantVisit' => $order->active_plant_visit_id ? [
                'id' => $order->active_plant_visit_id,
                'visitNo' => $order->active_plant_visit_no,
            ] : null,
            'activeLoadingOperationId' => $order->active_loading_operation_id,

            'isSapOwned' => (bool) $order->is_sap_owned,

            'notes' => $order->notes,
            'createdAt' => $order->created_at?->toIso8601String(),
            'updatedAt' => $order->updated_at?->toIso8601String(),
        ];

        // lockedFields exposed only when the order is SAP-owned, so the FE
        // can proactively disable inputs without waiting for a 423.
        if ($order->is_sap_owned) {
            $payload['lockedFields'] = LoadingOrder::SAP_OWNED_FIELDS;
        }

        return $payload;
    }

    protected function stateObject(string $value): array
    {
        return [
            'value' => $value,
            'label' => AssignmentState::label($value),
            'tone' => AssignmentState::tone($value),
        ];
    }
}
