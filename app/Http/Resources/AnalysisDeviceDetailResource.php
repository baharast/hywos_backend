<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * V1 §11 — the Selected Device Details payload. Builds on the card shape
 * and adds `channels[]`, `latestReadings[]`, and a `recentEvents[]` slice
 * straight from event_logs.
 *
 * Construct it with a structured array (not a model) — the controller
 * pre-assembles the card via AnalysisDeviceService and merges in the
 * tab data here.
 */
class AnalysisDeviceDetailResource extends JsonResource
{
    public function __construct(array $detail)
    {
        parent::__construct($detail);
    }

    public function toArray($request): array
    {
        /** @var array $d */
        $d = $this->resource;
        return $d;
    }
}
