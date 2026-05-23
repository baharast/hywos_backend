<?php

namespace App\Http\Resources;

use App\Enums\AnalysisStatus;
use App\Enums\LoadingStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class ActiveLoadingListItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $bay = $this->whenLoaded('bayLine');
        $driver = $this->whenLoaded('driver');

        $station = null;
        if ($this->relationLoaded('bayLine') && $this->bayLine) {
            $station = [
                'id' => $this->bayLine->id,
                'name' => $this->bayLine->name,
                'code' => $this->bayLine->code,
            ];
        } elseif ($this->bay_line_id) {
            $station = ['id' => $this->bay_line_id, 'name' => null, 'code' => null];
        }

        $driverPayload = null;
        if ($this->relationLoaded('driver') && $this->driver) {
            $driverPayload = [
                'id' => $this->driver->id,
                'fullName' => trim(($this->driver->first_name ?? '') . ' ' . ($this->driver->last_name ?? '')),
            ];
        } elseif ($this->driver_id) {
            $driverPayload = ['id' => $this->driver_id, 'fullName' => null];
        }

        return [
            'id' => $this->id,
            'displayNo' => $this->display_no,
            'station' => $station,
            'order' => [
                'id' => $this->order_id,
                'orderNo' => $this->order_no,
                'sapOrderNo' => $this->sap_order_no,
            ],
            'driver' => $driverPayload,
            'trailer' => [
                'id' => $this->trailer_id,
                'label' => $this->trailer_label,
            ],
            'tractorPlate' => $this->tractor_plate,
            'targetQuantity' => $this->target_quantity !== null ? (float) $this->target_quantity : null,
            'actualQuantity' => $this->actual_quantity !== null ? (float) $this->actual_quantity : null,
            'unit' => $this->unit,
            'progressPercent' => $this->progress,
            'loadingStatus' => [
                'value' => $this->loading_status,
                'label' => LoadingStatus::label($this->loading_status),
                'tone' => LoadingStatus::tone($this->loading_status),
            ],
            'analysisStatus' => [
                'value' => $this->analysis_status,
                'label' => AnalysisStatus::label($this->analysis_status),
                'tone' => AnalysisStatus::tone($this->analysis_status),
            ],
            'alarmCount' => (int) $this->alarm_count,
            'criticalAlarmCount' => (int) $this->critical_alarm_count,
            'hasClarification' => (bool) $this->has_clarification,
            'startedAt' => $this->started_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
