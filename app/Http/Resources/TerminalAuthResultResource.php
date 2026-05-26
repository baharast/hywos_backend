<?php

namespace App\Http\Resources;

use App\Enums\LoginResultCode;
use App\Services\TerminalAuth\TerminalAuthResult;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Driver terminal authentication outcome (V3 §21.2 + §9.4).
 *
 * Shape matches the FE `lastResult` type in §21.2 plus the `nextRoute`
 * hint the FE uses to navigate to safety training, trailer identification
 * or the manager dashboard. Never exposes raw TAN values, password
 * fragments or password hashes.
 */
class TerminalAuthResultResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TerminalAuthResult $r */
        $r = $this->resource;

        return [
            'code' => $r->code,
            'message' => $r->message,
            'tone' => LoginResultCode::tone($r->code),
            'isIdentified' => $r->isIdentified(),
            'nextRoute' => $r->nextRoute,

            'session' => $r->session ? [
                'id' => $r->session->id,
                'sessionNo' => $r->session->session_no,
                'touchpoint' => $r->session->touchpoint,
                'state' => $r->session->session_state,
                'currentScreen' => $r->session->current_screen,
                'issueReason' => $r->session->issue_reason,
                'actionNeeded' => $r->session->action_needed,
                'lastActivityAt' => $r->session->last_activity_at?->toIso8601String(),
            ] : null,

            'driver' => $r->driver ? [
                'id' => $r->driver->id,
                'driverCode' => $r->driver->driver_code,
                'firstName' => $r->driver->first_name,
                'lastName' => $r->driver->last_name,
                'preferredCultureCode' => $r->driver->preferred_culture_code,
                'trainingStatus' => $r->driver->training_status,
                'trainingValidUntil' => $r->driver->training_valid_until,
            ] : null,

            'terminal' => $r->terminal ? [
                'id' => $r->terminal->id,
                'code' => $r->terminal->code,
                'name' => $r->terminal->name,
            ] : null,
        ];
    }
}
