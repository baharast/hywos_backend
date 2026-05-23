<?php

namespace App\Http\Resources;

use App\Enums\AnalysisStatus;
use App\Enums\LoadingStatus;
use App\Enums\StationStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Station-View card. Wraps a BayLine + its currently active LoadingOperation
 * (or null when the bay is free / not occupied).
 *
 * Expected shape of $this->resource:
 *   [
 *     'bay'    => BayLine,
 *     'active' => LoadingOperation|null,
 *   ]
 */
class StationViewItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $bay = $this->resource['bay'];
        $active = $this->resource['active'] ?? null;

        $stationStatusValue = StationStatus::derive($bay, $active);

        $currentLoading = null;
        if ($active) {
            $driverName = null;
            if ($active->relationLoaded('driver') && $active->driver) {
                $driverName = trim(($active->driver->first_name ?? '') . ' ' . ($active->driver->last_name ?? ''));
            }

            $currentLoading = [
                'id' => $active->id,
                'displayNo' => $active->display_no,
                'orderNo' => $active->order_no,
                'sapOrderNo' => $active->sap_order_no,
                'driverName' => $driverName,
                'trailerLabel' => $active->trailer_label,
                'tractorPlate' => $active->tractor_plate,
                'targetQuantity' => $active->target_quantity !== null ? (float) $active->target_quantity : null,
                'actualQuantity' => $active->actual_quantity !== null ? (float) $active->actual_quantity : null,
                'unit' => $active->unit,
                'progressPercent' => $active->progress,
                'loadingStatus' => [
                    'value' => $active->loading_status,
                    'label' => LoadingStatus::label($active->loading_status),
                    'tone' => LoadingStatus::tone($active->loading_status),
                ],
                'analysisStatus' => [
                    'value' => $active->analysis_status,
                    'label' => AnalysisStatus::label($active->analysis_status),
                    'tone' => AnalysisStatus::tone($active->analysis_status),
                ],
            ];
        }

        return [
            'id' => $bay->id,
            'name' => $bay->name,
            'code' => $bay->code,
            'status' => [
                'value' => $stationStatusValue,
                'label' => StationStatus::label($stationStatusValue),
                'tone' => StationStatus::tone($stationStatusValue),
            ],
            'plcStatus' => $active?->plc_status,
            'currentLoading' => $currentLoading,
            'alarmCount' => (int) ($active?->alarm_count ?? 0),
            'criticalAlarmCount' => (int) ($active?->critical_alarm_count ?? 0),
            'hasClarification' => (bool) ($active?->has_clarification ?? false),
            'lastUpdatedAt' => optional($active?->updated_at ?? $bay->updated_at)?->toIso8601String(),
        ];
    }
}
