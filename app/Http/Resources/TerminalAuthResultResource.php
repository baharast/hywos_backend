<?php

namespace App\Http\Resources;

use App\Enums\LoginMethod;
use App\Enums\LoginResultCode;
use App\Enums\TrainingStatus;
use App\Services\TerminalAuth\TerminalAuthResult;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Driver terminal authentication outcome.
 *
 * Carries BOTH shapes so FE V3 and FE V6 can call the same endpoint:
 *   - V6 §10.3 flat fields: `ok`, `method`, `driverId`, `driverName`,
 *     `trainingValid`, plus a method-prefixed `v6Code` (`tan_invalid`,
 *     `chip_unknown`, etc.) alongside the richer internal `code`.
 *   - V3 §21.2 nested fields: `session`, `driver`, `terminal` objects.
 *
 * The flat `method` translates LoginMethod::CHIP_CARD ('chip_card') →
 * V6's 'chip' for the JSON wire format; everywhere internal we keep
 * 'chip_card'. Never exposes raw TAN values or password fragments.
 */
class TerminalAuthResultResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TerminalAuthResult $r */
        $r = $this->resource;

        $flatMethod = $this->flatMethod($r->method);
        $driverName = $r->driver
            ? trim("{$r->driver->first_name} {$r->driver->last_name}")
            : null;

        return [
            // ---- V6 §10.3 flat fields ------------------------------------
            'ok' => $r->isIdentified(),
            'method' => $flatMethod,
            'driverId' => $r->driver?->id,
            'driverName' => $driverName !== '' ? $driverName : null,
            'trainingValid' => $this->trainingValid($r),
            'v6Code' => LoginResultCode::v6Code($r->code, $r->method ?: ''),

            // ---- V3 §21.2 richer fields ----------------------------------
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

    /**
     * Map internal LoginMethod values to V6's flat wire format.
     * V6 §10.3 uses 'tan' | 'chip'; our LoginMethod::CHIP_CARD = 'chip_card'.
     */
    protected function flatMethod(?string $method): ?string
    {
        if (! $method) {
            return null;
        }
        return $method === LoginMethod::CHIP_CARD ? 'chip' : $method;
    }

    /**
     * V6 §6.14 gating: trainingValid drives whether the FE routes to the
     * trailer screen or to /safety-training?required=1. True when the
     * driver's training_status is `valid` AND training_valid_until is
     * either null (no expiry) or strictly in the future. False otherwise
     * (or when there's no driver at all).
     */
    protected function trainingValid(TerminalAuthResult $r): bool
    {
        if (! $r->driver) {
            return false;
        }
        if (($r->driver->training_status ?? null) !== TrainingStatus::VALID) {
            return false;
        }
        $until = $r->driver->training_valid_until;
        if ($until === null) {
            return true;
        }
        return CarbonImmutable::parse($until)->isFuture();
    }
}
