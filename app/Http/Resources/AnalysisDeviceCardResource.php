<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * V1 §8 — passes through the pre-assembled card array from
 * AnalysisDeviceService::buildCardForDevice() with no further shaping.
 *
 * Going through a Resource keeps the project pattern consistent (every
 * other module exposes its data via a Resource) and gives the FE a
 * stable wire shape if/when the service adds derived fields.
 */
class AnalysisDeviceCardResource extends JsonResource
{
    public function __construct(array $card)
    {
        parent::__construct($card);
    }

    public function toArray($request): array
    {
        /** @var array $c */
        $c = $this->resource;
        return $c;
    }
}
