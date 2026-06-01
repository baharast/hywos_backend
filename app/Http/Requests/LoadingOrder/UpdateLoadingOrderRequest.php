<?php

namespace App\Http\Requests\LoadingOrder;

use App\Enums\DriverTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT /api/loading-orders/{id}.
 *
 * SAP-owned field protection is enforced by SapFieldGuard inside the
 * controller (not here) because the rule depends on the model's
 * `is_sap_owned` flag — a request-level rule would re-fetch the model.
 *
 * Execution-lock protection is likewise enforced inside the controller.
 */
class UpdateLoadingOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'customer_id' => 'sometimes|nullable|string|size:36|exists:customers,id',
            'carrier_id' => 'sometimes|nullable|string|size:36|exists:freight_forwarders,id',

            'product_quality' => 'sometimes|nullable|string|max:100',
            'target_quantity' => 'sometimes|nullable|numeric|gt:0',
            'unit' => ['sometimes', 'nullable', 'string', Rule::in(['kg', 't', 'nm3'])],

            'planned_window_start' => 'sometimes|nullable|date',
            'planned_window_end' => 'sometimes|nullable|date|after_or_equal:planned_window_start',

            'task_flow' => ['sometimes', 'nullable', 'string', Rule::in(DriverTask::all())],

            'requires_certificate' => 'sometimes|boolean',
            'requires_delivery_note' => 'sometimes|boolean',
            'requires_qm_document' => 'sometimes|boolean',

            // Bayline reassignment via PUT — same validation as create,
            // controller-side guard still applies. Not in
            // FORBIDDEN_UPDATE_FIELDS because the dispatcher needs to
            // reroute mid-flow when an operator changes the plan.
            // Pass `null` to clear the assignment.
            'assigned_bay_line_id' => 'sometimes|nullable|string|size:36|exists:baylines,id',

            'external_reference' => 'sometimes|nullable|string|max:100',
            'notes' => 'sometimes|nullable|string|max:5000',
        ];
    }

    /**
     * Fields that may NEVER be set via PUT — driver/trailer go through the
     * assign-* endpoints, status anchors go through block/unblock/cancel, and
     * sap_reference / is_sap_owned are SAP-import-only.
     *
     * The controller strips these defensively before persisting.
     */
    public const FORBIDDEN_UPDATE_FIELDS = [
        'order_no',
        'sap_reference',
        'is_sap_owned',
        'status',
        'current_step',
        'blocked_at',
        'cancelled_at',
        'active_plant_visit_id',
        'active_plant_visit_no',
        'active_loading_operation_id',
        'is_locked_by_execution',
        'assigned_driver_id',
        'assigned_driver_name',
        'assigned_driver_code',
        'assigned_trailer_id',
        'assigned_trailer_label',
        'assigned_trailer_plate',
        'blocking_reason',
        'blocking_reason_code',
        'cancellation_reason',
        'cancellation_reason_code',
    ];
}
