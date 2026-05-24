<?php

namespace App\Http\Resources;

use App\Enums\ExportCategory;
use App\Enums\ExportJobStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class ExportJobResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status ?? ExportJobStatus::QUEUED;

        $categoryLabels = array_map(
            fn($slug) => ['value' => $slug, 'label' => ExportCategory::label($slug)],
            (array) $this->categories
        );

        return [
            'id' => $this->id,
            'displayName' => $this->display_name,

            'categories' => $categoryLabels,
            'recordScope' => $this->record_scope,
            'dateFrom' => $this->date_from?->toIso8601String(),
            'dateTo'   => $this->date_to?->toIso8601String(),
            'statusScope' => $this->status_scope,
            'fieldSet' => $this->field_set,
            'format' => $this->format,

            'status' => [
                'value' => $status,
                'label' => ExportJobStatus::label($status),
                'tone'  => ExportJobStatus::tone($status),
            ],

            'recordCountEstimate' => $this->record_count_estimate,
            'recordCountActual'   => $this->record_count_actual,
            'fileSizeBytes'       => $this->file_size_bytes,

            'requestedByUserId' => $this->requested_by_user_id,
            'requestedByName'   => $this->requested_by_name,

            'errorMessage' => $this->error_message,
            'warnings'     => $this->warnings ?? [],

            // Only expose downloadUrl when the file is ready and not expired.
            'downloadUrl' => $this->buildDownloadUrl(),

            'startedAt' => $this->started_at?->toIso8601String(),
            'readyAt'   => $this->ready_at?->toIso8601String(),
            'failedAt'  => $this->failed_at?->toIso8601String(),
            'expiresAt' => $this->expires_at?->toIso8601String(),

            'correlationId' => $this->correlation_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function buildDownloadUrl(): ?string
    {
        if ($this->status !== ExportJobStatus::READY) {
            return null;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return null;
        }
        return url("/api/master-data-export/{$this->id}/download");
    }
}
