<?php

namespace App\Http\Resources;

use App\Enums\InterfaceBlockingLevel;
use App\Enums\InterfaceDirection;
use App\Enums\InterfaceFamily;
use App\Enums\InterfaceProtocol;
use App\Enums\InterfaceStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * V1.4 §9 list-row shape. status / family / blocking_level / protocol
 * carry {value, label, tone} so the FE can render badges without a
 * client-side enum lookup.
 */
class InterfaceHealthListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'exactInterfaceId' => $this->exact_interface_id,
            'name' => $this->name,
            'family' => [
                'value' => $this->family,
                'label' => InterfaceFamily::label($this->family),
                'tone' => InterfaceFamily::tone($this->family),
            ],
            'protocol' => [
                'value' => $this->protocol,
                'label' => InterfaceProtocol::label($this->protocol),
            ],
            'direction' => [
                'value' => $this->direction,
                'label' => InterfaceDirection::label($this->direction),
            ],
            'sourceLabel' => $this->source_label,
            'targetLabel' => $this->target_label,
            'status' => [
                'value' => $this->status,
                'label' => InterfaceStatus::label($this->status),
                'tone' => InterfaceStatus::tone($this->status),
            ],
            'blockingLevel' => [
                'value' => $this->blocking_level,
                'label' => InterfaceBlockingLevel::label($this->blocking_level),
                'tone' => InterfaceBlockingLevel::tone($this->blocking_level),
            ],
            'lastSuccessAt' => $this->last_success_at?->toIso8601String(),
            'lastFailureAt' => $this->last_failure_at?->toIso8601String(),
            'queueCount' => (int) $this->queue_count,
            'failedToday' => (int) $this->failed_today,
            'lastErrorText' => $this->last_error_text,
            'lastRetryAt' => $this->last_retry_at?->toIso8601String(),
            'localOperationAllowed' => (bool) $this->local_operation_allowed,
            'affectedProcessLabel' => $this->affected_process_label,
            'dataStatus' => $this->data_status,
            'sourceBasis' => $this->source_basis,
        ];
    }
}
