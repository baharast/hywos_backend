<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a row from `event_logs` whose event_type belongs to the TAN usage family
 * (`tan.usage.*`, `tan.consumed`, `tan.usage_failed`) for the per-TAN history tab.
 */
class TanUsageEventResource extends JsonResource
{
    public function toArray($request): array
    {
        $details = $this->details ?? [];
        $eventType = $this->event_type;

        $result = $this->deriveResult($eventType, $details);

        return [
            'id' => $this->id,
            'timestamp' => $this->occurred_at?->toIso8601String(),
            'terminalLabel' => $details['terminal_label'] ?? null,
            'sourceType' => $details['source_type'] ?? null,
            'result' => $result,
            'message' => $this->message,
            'relatedPlantVisitId' => $details['related_plant_visit_id'] ?? null,
            'relatedTerminalSessionId' => $details['related_terminal_session_id'] ?? null,
        ];
    }

    protected function deriveResult(?string $eventType, array $details): array
    {
        $explicit = $details['result'] ?? null;
        if ($explicit) {
            return [
                'value' => $explicit,
                'label' => ucfirst(str_replace('_', ' ', $explicit)),
                'tone' => match ($explicit) {
                    'success', 'accepted' => 'success',
                    'failed', 'rejected', 'blocked' => 'danger',
                    default => 'neutral',
                },
            ];
        }

        return match ($eventType) {
            'tan.consumed' => ['value' => 'success', 'label' => 'Accepted', 'tone' => 'success'],
            'tan.usage_failed' => ['value' => 'failed', 'label' => 'Failed', 'tone' => 'danger'],
            default => ['value' => 'unknown', 'label' => 'Unknown', 'tone' => 'neutral'],
        };
    }
}
