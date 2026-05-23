<?php

namespace App\Http\Resources;

use App\Enums\AnalysisStatus;
use App\Enums\LoadingStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class LoadingDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $listShape = (new ActiveLoadingListItemResource($this->resource))->toArray($request);

        $driverPayload = null;
        if ($this->relationLoaded('driver') && $this->driver) {
            $driverPayload = [
                'id' => $this->driver->id,
                'fullName' => trim(($this->driver->first_name ?? '') . ' ' . ($this->driver->last_name ?? '')),
                'phone' => $this->driver->phone,
                'language' => $this->driver->preferred_culture_code,
            ];
        } elseif ($this->driver_id) {
            $driverPayload = ['id' => $this->driver_id, 'fullName' => null, 'phone' => null, 'language' => null];
        }

        $progress = [
            'target' => $this->target_quantity !== null ? (float) $this->target_quantity : null,
            'actual' => $this->actual_quantity !== null ? (float) $this->actual_quantity : null,
            'percent' => $this->progress,
            'unit' => $this->unit,
        ];

        $assignment = [
            'driver' => $driverPayload,
            'tractor' => ['plate' => $this->tractor_plate],
            'trailer' => ['id' => $this->trailer_id, 'label' => $this->trailer_label],
            'plantVisit' => ['id' => $this->plant_visit_id, 'visitNo' => $this->visit_no],
            'customer' => ['id' => $this->customer_id, 'name' => $this->customer_name],
        ];

        $nextStep = $this->deriveNextStep();

        return array_merge($listShape, [
            'progress' => $progress,
            'assignment' => $assignment,
            'release' => [
                'source' => $this->release_source,
                'reasonCode' => $this->release_reason_code,
                'reason' => $this->release_reason,
            ],
            'plcStatus' => $this->plc_status,
            'productQuality' => $this->product_quality,
            'lastEventAt' => $this->last_event_at?->toIso8601String(),
            'correlationId' => $this->correlation_id,
            'nextStep' => $nextStep,
            'notes' => $this->notes,
            'tabs' => [
                'events' => null, // populated by GET /loadings/{id}/events
                'audit' => null,  // populated by GET /loadings/{id}/audit
                'analysis' => [
                    'currentStatus' => [
                        'value' => $this->analysis_status,
                        'label' => AnalysisStatus::label($this->analysis_status),
                        'tone' => AnalysisStatus::tone($this->analysis_status),
                    ],
                    'note' => 'Analysis module not yet implemented; showing stored status only.',
                ],
                'alarms' => [],
                'documents' => [],
            ],
        ]);
    }

    protected function deriveNextStep(): string
    {
        return match ($this->loading_status) {
            LoadingStatus::ASSIGNED => 'Awaiting pre-analysis result.',
            LoadingStatus::WAITING_PRE_ANALYSIS => 'Pre-analysis in progress.',
            LoadingStatus::RELEASED => 'Awaiting loading start at panel.',
            LoadingStatus::LOADING => 'Loading in progress.',
            LoadingStatus::PAUSED => 'Loading paused — operator action required.',
            LoadingStatus::WAITING_MAIN_ANALYSIS => 'Awaiting main-analysis result.',
            LoadingStatus::QUALITY_CHECK_OPEN => 'Quality check awaiting decision.',
            LoadingStatus::QUALITY_BLOCKED => 'Quality block — escalate to Analysis Specialist.',
            LoadingStatus::CLARIFICATION_REQUIRED => 'Clarification case open — resolve before continuing.',
            LoadingStatus::COMPLETED => 'Loading completed; check document state.',
            LoadingStatus::FAILED => 'Loading failed — review and close.',
            LoadingStatus::CANCELLED => 'Loading cancelled.',
            default => 'Review state.',
        };
    }
}
