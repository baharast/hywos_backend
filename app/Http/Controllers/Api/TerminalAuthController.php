<?php

namespace App\Http\Controllers\Api;

use App\Enums\LoginResultCode;
use App\Http\Requests\TerminalAuth\AuthenticateChipCardRequest;
use App\Http\Requests\TerminalAuth\AuthenticateTanRequest;
use App\Http\Requests\TerminalAuth\ChangeLanguageRequest;
use App\Http\Requests\TerminalAuth\ManagerLogonRequest;
use App\Http\Resources\TerminalAuthResultResource;
use App\Http\Resources\TerminalConfigResource;
use App\Models\TerminalPanel;
use App\Models\TerminalSession;
use App\Services\TerminalAuth\TerminalAuthService;

/**
 * Driver Terminal Login (V3).
 *
 * Public endpoints — there is no Laravel auth on these routes; identity is
 * established BY them. Manager Logon issues a Sanctum personal access
 * token that downstream dashboard routes will require once their middleware
 * is re-enabled.
 *
 * Each method delegates the business logic to TerminalAuthService — the
 * controller is a thin HTTP layer.
 */
class TerminalAuthController extends ApiController
{
    public function __construct(protected TerminalAuthService $authService) {}

    /* ============================================================
     * READ
     * ============================================================ */

    /**
     * Terminal configuration for the login page (V3 §21.3).
     *
     * Accepts either UUID or `code` so the FE can pin a terminal via
     * either kiosk-config bootstrap or URL parameter.
     */
    public function config(string $terminal)
    {
        $row = TerminalPanel::query()
            ->where(function ($q) use ($terminal) {
                $q->where('id', $terminal)->orWhere('code', $terminal);
            })
            ->first();

        if (! $row) {
            return $this->error(__('terminal.messages.terminal_not_found'), 'TERMINAL_NOT_FOUND', 404);
        }

        return $this->success(new TerminalConfigResource($row), __('terminal.messages.terminal_config'));
    }

    /**
     * Driver-facing safety information snippet (V3 §10.2). Plain JSON,
     * driver-readable copy only — no learning content (that belongs to the
     * Safety Training module).
     */
    public function safetyInfo()
    {
        return $this->success([
            'title' => __('terminal.messages.safety_info_title'),
            'items' => [
                __('terminal.messages.safety_rule_1'),
                __('terminal.messages.safety_rule_2'),
                __('terminal.messages.safety_rule_3'),
                __('terminal.messages.safety_rule_4'),
                __('terminal.messages.safety_rule_5'),
            ],
        ], __('terminal.messages.safety_info_retrieved'));
    }

    public function session(string $id)
    {
        $session = TerminalSession::find($id);
        if (! $session) {
            return $this->error(__('terminal.messages.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        return $this->success([
            'id' => $session->id,
            'sessionNo' => $session->session_no,
            'touchpoint' => $session->touchpoint,
            'state' => $session->session_state,
            'currentScreen' => $session->current_screen,
            'driverId' => $session->driver_id,
            'driverName' => $session->driver_name,
            'driverCode' => $session->driver_code,
            'issueReason' => $session->issue_reason,
            'actionNeeded' => $session->action_needed,
            'lastActivityAt' => $session->last_activity_at?->toIso8601String(),
            'notes' => $session->notes,
        ], __('terminal.messages.session_retrieved'));
    }

    /* ============================================================
     * WRITE — driver identification
     * ============================================================ */

    public function authenticateTan(AuthenticateTanRequest $request)
    {
        $data = $request->validated();
        $result = $this->authService->authenticateWithTan(
            $data['terminal_id'],
            $data['tan'],
            $data['language'] ?? null
        );

        return $this->respondAuthResult($result);
    }

    public function authenticateChip(AuthenticateChipCardRequest $request)
    {
        $data = $request->validated();
        $result = $this->authService->authenticateWithChipCard(
            $data['terminal_id'],
            $data['card_code'] ?? null,
            $data['masked_uid'] ?? null,
            $data['language'] ?? null,
            (bool) ($data['is_simulated'] ?? false)
        );

        return $this->respondAuthResult($result);
    }

    /* ============================================================
     * WRITE — manager / operator logon (V3 §11)
     * ============================================================ */

    public function managerLogon(ManagerLogonRequest $request)
    {
        $data = $request->validated();
        $out = $this->authService->managerLogon(
            $data['username'],
            $data['password'],
            $data['terminal_id'] ?? null
        );

        if (! $out['success']) {
            // 401 keeps the popup open; FE shows the generic copy.
            return $this->error($out['message'], strtoupper($out['code']), 401);
        }

        return $this->success([
            'token' => $out['token'],
            'tokenType' => 'Bearer',
            'user' => $out['user'],
            'nextRoute' => $out['nextRoute'],
        ], $out['message']);
    }

    /* ============================================================
     * WRITE — session lifecycle
     * ============================================================ */

    public function changeLanguage(ChangeLanguageRequest $request, string $sessionId)
    {
        $session = TerminalSession::find($sessionId);
        if (! $session) {
            return $this->error(__('terminal.messages.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        $fresh = $this->authService->changeLanguage($session, $request->validated()['culture_code']);

        return $this->success([
            'id' => $fresh->id,
            'sessionNo' => $fresh->session_no,
            'notes' => $fresh->notes,
            'lastActivityAt' => $fresh->last_activity_at?->toIso8601String(),
        ], __('terminal.messages.language_updated'));
    }

    public function logout(string $sessionId)
    {
        $session = TerminalSession::find($sessionId);
        if (! $session) {
            return $this->error(__('terminal.messages.session_not_found'), 'SESSION_NOT_FOUND', 404);
        }

        $fresh = $this->authService->logoutSession($session, 'manual');

        return $this->success([
            'id' => $fresh->id,
            'sessionNo' => $fresh->session_no,
            'state' => $fresh->session_state,
        ], __('terminal.messages.session_ended'));
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    protected function respondAuthResult($result)
    {
        $resource = new TerminalAuthResultResource($result);

        if ($result->isIdentified()) {
            // success + training_required both return 200; the FE branches
            // on `code` / `nextRoute`.
            return $this->success(
                $resource,
                $result->message
            );
        }

        // Failed identification — terminal stays open, FE retries. The
        // response shape is identical so FE can render error inline.
        return response()->json([
            'data' => $resource->toArray(request()),
            'message' => $result->message,
            'error' => [
                'code' => strtoupper($result->code),
                'message' => $result->message,
            ],
        ], $this->statusForCode($result->code));
    }

    protected function statusForCode(string $code): int
    {
        return match ($code) {
            LoginResultCode::TERMINAL_OFFLINE,
            LoginResultCode::TERMINAL_SERVICE_MODE,
            LoginResultCode::READER_OFFLINE,
            LoginResultCode::BACKEND_UNAVAILABLE => 503,

            LoginResultCode::TOO_MANY_ATTEMPTS => 429,

            // Everything else is an auth/credential failure
            default => 401,
        };
    }
}
