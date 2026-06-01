<?php

namespace App\Http\Requests\LoadingOrder;

use App\Enums\DriverTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLoadingOrderRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        return [
            'customer_id' => 'required|string|size:36|exists:customers,id',
            'carrier_id' => 'nullable|string|size:36|exists:freight_forwarders,id',

            'product_quality' => 'required|string|max:100',
            'target_quantity' => 'required|numeric|gt:0',
            'unit' => ['nullable', 'string', Rule::in(['kg', 't', 'nm3'])],

            'planned_window_start' => 'nullable|date',
            'planned_window_end' => 'nullable|date|after_or_equal:planned_window_start',

            'task_flow' => ['nullable', 'string', Rule::in(DriverTask::all())],

            'requires_certificate' => 'nullable|boolean',
            'requires_delivery_note' => 'nullable|boolean',
            'requires_qm_document' => 'nullable|boolean',

            'assigned_driver_id' => 'nullable|string|size:36|exists:drivers,id',
            'assigned_trailer_id' => 'nullable|string|size:36|exists:trailers,id',
            // When provided, the controller validates the bayline is
            // active + free (or already this order's slot) before
            // accepting; auto-pick via OrderProvisioningService runs
            // only when the field is omitted. denormalized
            // assigned_bay_line_code / assigned_bay_line_name are
            // server-set from the bayline row, never accepted on input.
            'assigned_bay_line_id' => 'nullable|string|size:36|exists:baylines,id',

            'external_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:5000',
        ];
    }

    /**
     * Fields a manual create may never set. The controller rejects them at the
     * boundary; listed here so a future developer can see them next to the
     * validated set.
     *
     * Server-generated / SAP-only / lifecycle-only:
     *   order_no, sap_reference, is_sap_owned, status, current_step,
     *   blocked_at, cancelled_at, active_plant_visit_id,
     *   active_plant_visit_no, active_loading_operation_id,
     *   is_locked_by_execution
     */
    public const FORBIDDEN_CREATE_FIELDS = [
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
    ];
}
