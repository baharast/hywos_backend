<?php

namespace App\Http\Resources;

use App\Enums\GateTerminalCurrentScreen;
use App\Enums\GateTerminalSessionState;
use App\Enums\GateTerminalTouchpoint;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One card on the 3-card Touchpoint Status Board (V2.3 §6).
 *
 * The controller passes a pre-shaped associative array with the keys this
 * resource expects — there's no underlying Eloquent model. Using a JsonResource
 * still gives consistent envelope handling under ApiResponse::list().
 *
 * Expected payload shape (built by GateTerminalMonitorController::buildTouchpointCard):
 *   touchpoint           string  (enum value)
 *   state                string  (enum value derived per §6.3 priority)
 *   activeSessionCount   int
 *   primarySession       ?\App\Models\TerminalSession   (the highest-priority row, may be null)
 */
class GateTerminalTouchpointSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $touchpoint = $this['touchpoint'];
        $state = $this['state'];
        $primary = $this['primarySession'] ?? null;
        $screen = $primary?->current_screen;

        return [
            'touchpoint' => $touchpoint,
            'touchpointLabel' => GateTerminalTouchpoint::label($touchpoint),

            'state' => [
                'value' => $state,
                'label' => GateTerminalSessionState::label($state),
                'tone' => GateTerminalSessionState::tone($state),
            ],

            'activeSessionCount' => (int) ($this['activeSessionCount'] ?? 0),
            'primarySessionId' => $primary?->id,

            // Driver + visit only when a session is actually attached.
            // V2.3 §6.3 rule 1: a device fault may have no driver, in which
            // case both fields are null — frontend renders the fault alone.
            'driverName' => $primary?->driver_name,
            'visitNo' => $primary?->visit_no,

            'currentScreen' => $screen ? [
                'value' => $screen,
                'label' => GateTerminalCurrentScreen::label($screen),
            ] : null,

            'issueReason' => $primary?->issue_reason,
            'actionNeeded' => $primary?->action_needed,

            'lastActivityAt' => $primary?->last_activity_at?->toIso8601String(),
        ];
    }
}
