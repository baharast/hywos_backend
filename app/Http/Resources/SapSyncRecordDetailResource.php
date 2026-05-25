<?php

namespace App\Http\Resources;

use App\Enums\SapFeedbackType;
use App\Enums\SapHandlingStatus;
use App\Enums\SapSyncDirection;
use App\Enums\SapSyncResultStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SAP Sync record — heavy shape for the Selected Sync Record Details panel
 * (V1.5 §8.8). Extends the list shape with:
 *  - `attempts` history
 *  - technical / connector messages (V1.5 §2.1 — restricted to IT/SAP roles
 *    when auth lands; emitted unconditionally in dev because auth is off)
 *  - sync run id / interface id / retry count
 *  - forward-contract events/audit deeplink URLs
 *
 * The detail resource is intentionally not nested under the list resource so
 * each can evolve independently — list is read by the table, detail by the
 * panel.
 */
class SapSyncRecordDetailResource extends JsonResource
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

            'issueReason' => $this->issue_reason,
            'whatHappened' => $this->what_happened,
            'nextAction' => $this->next_action,
            'ownerRole' => $this->owner_role,

            'eventTime' => $this->event_time?->toIso8601String(),
            'lastAttemptAt' => $this->last_attempt_at?->toIso8601String(),
            'lastSuccessAt' => $this->last_success_at?->toIso8601String(),

            // Restricted IT/SAP-support fields (V1.5 §2.1).
            // TODO: when auth lands, gate these behind permissions
            //   `sap_sync.view_technical` and only emit when the actor has
            //   IT_Support / SAP_Responsible / Admin roles. Today auth is
            //   disabled in MVP so they are emitted unconditionally.
            'technicalMessage' => $this->technical_message,
            'connectorMessage' => $this->connector_message,
            'syncRunId' => $this->sync_run_id,
            'interfaceId' => $this->interface_id,
            'retryCount' => (int) $this->retry_count,

            // V1.5 §8.8 — attempt history lives in the detail panel only.
            'attempts' => $this->normalisedAttempts(),

            // Forward-contract deeplinks (V1.5 §7) — events/audit sub-endpoints
            // are documented but not implemented in this slice. Emitting the
            // URLs lets the FE wire navigation now without a follow-up release.
            'eventJournalUrl' => "/api/sap-sync/{$this->id}/events",
            'auditTrailUrl' => "/api/sap-sync/{$this->id}/audit",

            'correlationId' => $this->correlation_id,
            'notes' => $this->notes,

            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Normalise the `attempts` JSON column to the V1.5 §12 frontend shape:
     * `{ attemptedAt, resultStatus, issueReason, handlingStatus }`.
     */
    protected function normalisedAttempts(): ?array
    {
        $raw = $this->attempts;
        if (! is_array($raw) || empty($raw)) {
            return null;
        }

        return array_values(array_map(function ($row) {
            return [
                'attemptedAt' => $row['attemptedAt'] ?? $row['attempted_at'] ?? null,
                'resultStatus' => $row['resultStatus'] ?? $row['result_status'] ?? null,
                'issueReason' => $row['issueReason'] ?? $row['issue_reason'] ?? null,
                'handlingStatus' => $row['handlingStatus'] ?? $row['handling_status'] ?? null,
            ];
        }, $raw));
    }
}
