<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisDeviceChannelResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'channelCode' => $this->channel_code,
            'label' => $this->label,
            'gas' => $this->gas,
            'severity' => $this->severity,
            'measuredValue' => $this->measured_value,
            'unit' => $this->unit,
            'acknowledged' => (bool) $this->acknowledged,
            'inhibited' => (bool) $this->inhibited,
            'lastMessage' => $this->last_message,
            'lastValueAt' => $this->last_value_at?->toIso8601String(),
        ];
    }
}
