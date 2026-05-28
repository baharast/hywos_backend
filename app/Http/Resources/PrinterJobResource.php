<?php

namespace App\Http\Resources;

use App\Enums\PrinterJobStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * V1.4 §6 — wire shape for one `document_print_attempts` row in the
 * printer job feed. Uses `PrinterJobStatus` (V1.4 superset that adds
 * `rerouted` and `cancelled` to the D1 baseline) so the FE sees a
 * consistent status palette across the tab.
 */
class PrinterJobResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'documentId' => $this->document_id,
            'attemptNo' => (int) $this->attempt_no,
            'status' => [
                'value' => $this->status,
                'label' => PrinterJobStatus::label($this->status),
                'tone' => PrinterJobStatus::tone($this->status),
            ],
            'printerId' => $this->printer_id,
            'printerHardwareId' => $this->printer_hardware_id,
            'printerName' => $this->printer_name,
            'printJobId' => $this->print_job_id,
            'requestedAt' => $this->requested_at?->toIso8601String(),
            'requestedByUserId' => $this->requested_by_user_id,
            'requestedByLabel' => $this->requested_by_label,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'failureReason' => $this->failure_reason,
            'isReprint' => (bool) $this->is_reprint,
            'reprintReason' => $this->reprint_reason,
            'retryOfAttemptId' => $this->retry_of_attempt_id,
            'replacementOfAttemptId' => $this->replacement_of_attempt_id,
            'isRetryable' => PrinterJobStatus::isRetryable($this->status),
            'isReroutable' => PrinterJobStatus::isReroutable($this->status),
            'correlationId' => $this->correlation_id,
        ];
    }
}
