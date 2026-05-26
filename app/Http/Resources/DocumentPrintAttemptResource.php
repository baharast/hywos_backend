<?php

namespace App\Http\Resources;

use App\Enums\DocumentPrintStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Single print/reprint attempt. Per V1.2 §16 DocumentPrintAttempt.
 */
class DocumentPrintAttemptResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status ?? DocumentPrintStatus::NOT_REQUESTED;

        return [
            'id' => $this->id,
            'attemptNo' => (int) $this->attempt_no,
            'status' => [
                'value' => $status,
                'label' => DocumentPrintStatus::label($status),
                'tone' => DocumentPrintStatus::tone($status),
            ],
            'printerId' => $this->printer_id,
            'printerName' => $this->printer_name,
            'printJobId' => $this->print_job_id,
            'requestedAt' => $this->requested_at?->toIso8601String(),
            'requestedByUserId' => $this->requested_by_user_id,
            'requestedByLabel' => $this->requested_by_label,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'failureReason' => $this->failure_reason,
            'isReprint' => (bool) $this->is_reprint,
            'reprintReason' => $this->reprint_reason,
            'correlationId' => $this->correlation_id,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
