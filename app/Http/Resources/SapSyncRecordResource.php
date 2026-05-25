<?php

namespace App\Http\Resources;

use App\Enums\SapFeedbackType;
use App\Enums\SapHandlingStatus;
use App\Enums\SapSyncDirection;
use App\Enums\SapSyncResultStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SAP Sync record — light shape for the main list table (V1.5 §8.6 columns).
 *
 * Per V1.5 §8.6.1 / §13 "Attempt history" the list MUST show the latest
 * state only. `attempts`, technical messages, sync_run_id / interface_id,
 * retry_count and last_success_at are intentionally excluded here and live
 * only in SapSyncRecordDetailResource.
 */
class SapSyncRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        $direction = $this->direction ?: SapSyncDirection::IMPORT;
        $result = $this->result_status ?: SapSyncResultStatus::PENDING;
        $handling = $this->handling_status;
        $feedback = $this->feedback_type;

        return [
            'id' => $this->id,

            'direction' => [
                'value' => $direction,
                'label' => SapSyncDirection::label($direction),
            ],
            'sapReference' => $this->sap_reference,

            // Local Loading Order deeplink — present only when a local order
            // was created/linked. FE hides "Open Loading Order" when null.
            'order' => $this->order_id ? [
                'id' => $this->order_id,
                'orderNo' => $this->order_no,
            ] : null,

            'customer' => $this->customer_id ? [
                'id' => $this->customer_id,
                'name' => $this->customer_name,
            ] : null,

            'carrier' => $this->carrier_id ? [
                'id' => $this->carrier_id,
                'name' => $this->carrier_name,
            ] : null,

            'productQuality' => $this->product_quality,

            'resultStatus' => [
                'value' => $result,
                'label' => SapSyncResultStatus::label($result),
                'tone' => SapSyncResultStatus::tone($result),
            ],
            'handlingStatus' => $handling ? [
                'value' => $handling,
                'label' => SapHandlingStatus::label($handling),
                'tone' => SapHandlingStatus::tone($handling),
            ] : null,
            'feedbackType' => $feedback ? [
                'value' => $feedback,
                'label' => SapFeedbackType::label($feedback),
            ] : null,

            // Backend-supplied plain-language narrative (V1.5 §9.3 — FE must
            // not invent error text). Emit verbatim.
            'issueReason' => $this->issue_reason,
            'whatHappened' => $this->what_happened,
            'nextAction' => $this->next_action,

            'ownerRole' => $this->owner_role,

            'eventTime' => $this->event_time?->toIso8601String(),
            'lastAttemptAt' => $this->last_attempt_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
