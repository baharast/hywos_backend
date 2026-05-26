<?php

namespace App\Http\Resources;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\DocumentPrintStatus;
use App\Enums\DocumentType;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Operational document resource — used for both the list page (V1.2 §16
 * DocumentListItem) and the detail page (DocumentDetail). When constructed
 * via `OperationalDocumentResource::detail($model)`, the detail-only fields
 * (snapshot, file metadata, printAttempts, related summaries) are merged
 * into the same shape — frontend reads one type and ignores fields not
 * present on the list response.
 */
class OperationalDocumentResource extends JsonResource
{
    protected bool $asDetail = false;

    public static function detail($resource): self
    {
        $r = new self($resource);
        $r->asDetail = true;
        return $r;
    }

    public function toArray($request): array
    {
        $lifecycle = $this->lifecycle_status ?? DocumentLifecycleStatus::PENDING;
        $print = $this->print_status ?? DocumentPrintStatus::NOT_REQUESTED;
        $type = $this->document_type;

        $base = [
            'id' => $this->id,
            'documentNo' => $this->document_no,
            'documentType' => $type === null ? null : [
                'value' => $type,
                'label' => DocumentType::label($type),
            ],
            'lifecycleStatus' => [
                'value' => $lifecycle,
                'label' => DocumentLifecycleStatus::label($lifecycle),
                'tone' => DocumentLifecycleStatus::tone($lifecycle),
            ],
            'printStatus' => [
                'value' => $print,
                'label' => DocumentPrintStatus::label($print),
                'tone' => DocumentPrintStatus::tone($print),
            ],
            'isExitBlocking' => (bool) $this->is_exit_blocking,
            'blockingReason' => $this->blocking_reason,
            'blockerType' => $this->blocker_type,

            'orderId' => $this->order_id,
            'orderNo' => $this->order_no,
            'sapOrderNo' => $this->sap_order_no,
            'plantVisitId' => $this->plant_visit_id,
            'visitNo' => $this->visit_no,
            'driverId' => $this->driver_id,
            'driverName' => $this->driver_name,
            'trailerId' => $this->trailer_id,
            'trailerLabel' => $this->trailer_label,
            'customerName' => $this->customer_name,
            'carrierName' => $this->carrier_name,

            'generatedAt' => $this->generated_at?->toIso8601String(),
            'queuedAt' => $this->queued_at?->toIso8601String(),
            'printedAt' => $this->printed_at?->toIso8601String(),
            'handedOverAt' => $this->handed_over_at?->toIso8601String(),
            'blockedAt' => $this->blocked_at?->toIso8601String(),

            'printerId' => $this->printer_id,
            'printerName' => $this->printer_name,
            'printJobId' => $this->print_job_id,
            'reprintCount' => (int) ($this->reprint_count ?? 0),
            'version' => $this->version,

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];

        if (! $this->asDetail) {
            return $base;
        }

        return array_merge($base, [
            'fileUrl' => $this->file_url,
            'templateName' => $this->template_name,
            'templateVersion' => $this->template_version,

            'productQuality' => $this->product_quality,
            'actualLoadedQuantity' => $this->actual_loaded_quantity === null
                ? null
                : (float) $this->actual_loaded_quantity,
            'unit' => $this->unit,
            'analysisId' => $this->analysis_id,
            'analysisStatus' => $this->analysis_status,

            'tractorId' => $this->tractor_id,
            'tractorLabel' => $this->tractor_label,
            'customerId' => $this->customer_id,
            'carrierId' => $this->carrier_id,

            'lastFailureReason' => $this->last_failure_reason,

            'handedOverByUserId' => $this->handed_over_by_user_id,
            'handoverNote' => $this->handover_note,
            'invalidatedAt' => $this->invalidated_at?->toIso8601String(),
            'invalidatedByUserId' => $this->invalidated_by_user_id,
            'invalidationReason' => $this->invalidation_reason,
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'cancelledByUserId' => $this->cancelled_by_user_id,
            'cancellationReason' => $this->cancellation_reason,

            'generatedBySource' => $this->generated_by_source,
            'generatedByUserId' => $this->generated_by_user_id,
            'snapshotPayload' => $this->snapshot_payload,
            'correlationId' => $this->correlation_id,

            'printAttempts' => DocumentPrintAttemptResource::collection(
                $this->whenLoaded('printAttempts')
            ),
        ]);
    }
}
