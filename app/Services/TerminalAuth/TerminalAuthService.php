<?php

namespace App\Services\TerminalAuth;

use App\Enums\AuditAction;
use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\ChipCardAssignmentState;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\GateTerminalCurrentScreen;
use App\Enums\GateTerminalSessionState;
use App\Enums\GateTerminalTouchpoint;
use App\Enums\LoginMethod;
use App\Enums\LoginResultCode;
use App\Enums\TanUsageState;
use App\Enums\TrainingStatus;
use App\Models\AuthMedium;
use App\Models\Driver;
use App\Models\TerminalSession;
use App\Models\TerminalPanel;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single source of truth for the driver terminal login flow.
 *
 * Implements FillTrack Driver Login Page UX Frontend Spec-EN-V3:
 *   §9.1   TAN login
 *   §9.2   Chip card login
 *   §9.4   Authentication success routing (identity → training check)
 *   §10    Side panel actions (language change, safety, support)
 *   §11    Manager / Operator logon (Sanctum token)
 *   §17    Validation rules → LoginResultCode mapping
 *   §21    Backend / API expectations
 *
 * Every authentication attempt — success or failure — produces:
 *   • one terminal_sessions row (session_state encodes success vs denial)
 *   • one audit row (TERMINAL_LOGIN_* / TERMINAL_LOGIN_FAILED)
 *   • one event row (severity info / warning / critical)
 *
 * Failed attempts get session_state = DENIED with issue_reason captured so
 * Gate & Terminal Monitor surfaces them on the touchpoint card.
 */
class TerminalAuthService
{
    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events
    ) {}

    /* ============================================================
     * TAN login (V3 §9.1, §9.4)
     * ============================================================ */

    public function authenticateWithTan(string $terminalId, string $tanValue, ?string $language = null): TerminalAuthResult
    {
        $terminal = $this->resolveTerminal($terminalId);
        if (! $terminal) {
            return $this->failNoSession(LoginMethod::TAN, LoginResultCode::TERMINAL_OFFLINE, 'Unknown terminal.', $terminalId);
        }
        if ($terminal->status === 'service_mode') {
            return $this->failNoSession(LoginMethod::TAN, LoginResultCode::TERMINAL_SERVICE_MODE, 'Terminal is in service mode.', $terminalId);
        }
        if (! $terminal->is_active) {
            return $this->failNoSession(LoginMethod::TAN, LoginResultCode::TERMINAL_OFFLINE, 'Terminal not active.', $terminalId);
        }

        // V1.3 §5.1 TAN lookup. We accept either `tan_reference` (the
        // human-readable id) or the raw `identifier_value` (single-use
        // secret). The raw value is hidden from API; lookup-by-hash would
        // be safer but the existing schema doesn't store one for TANs.
        $tan = AuthMedium::query()
            ->tans()
            ->where(function ($q) use ($tanValue) {
                $q->where('tan_reference', $tanValue)
                    ->orWhere('identifier_value', $tanValue);
            })
            ->first();

        return DB::transaction(function () use ($terminal, $tan, $tanValue, $language) {
            if (! $tan) {
                return $this->logFailure(
                    $terminal, LoginMethod::TAN, LoginResultCode::INVALID,
                    "Unknown TAN reference.", $language, null,
                    ['tan_lookup_value' => $this->maskTan($tanValue)]
                );
            }

            // Lifecycle gates per V1.3 §5.1 + V3 §17
            $code = $this->evaluateTanLifecycle($tan);
            if ($code !== LoginResultCode::SUCCESS) {
                return $this->logFailure(
                    $terminal, LoginMethod::TAN, $code,
                    LoginResultCode::message($code), $language,
                    $tan->driver_id,
                    ['tan_id' => $tan->id, 'tan_reference' => $tan->tan_reference]
                );
            }

            $driver = $tan->driver_id ? Driver::find($tan->driver_id) : null;
            if (! $driver) {
                return $this->logFailure(
                    $terminal, LoginMethod::TAN, LoginResultCode::INVALID,
                    "TAN is not bound to a driver.", $language, null,
                    ['tan_id' => $tan->id]
                );
            }

            $driverCheck = $this->evaluateDriverEligibility($driver);
            if ($driverCheck !== LoginResultCode::SUCCESS && $driverCheck !== LoginResultCode::TRAINING_REQUIRED) {
                return $this->logFailure(
                    $terminal, LoginMethod::TAN, $driverCheck,
                    LoginResultCode::message($driverCheck), $language, $driver->id,
                    ['tan_id' => $tan->id]
                );
            }

            // Identification succeeded — open a session.
            $session = $this->openSession($terminal, $driver, $language, LoginMethod::TAN, $driverCheck);

            // Mark TAN consumed only when the driver is actually allowed
            // onto operational flow. Training-required still consumes —
            // the TAN identified the driver successfully (§5.1 "first
            // successful terminal/backend validation").
            $tan->status = AuthMediumStatus::USED;
            $tan->usage_state = TanUsageState::CONSUMED;
            $tan->consumed_at = now();
            $tan->consumption_count = (int) $tan->consumption_count + 1;
            $tan->used_at = now();
            $tan->related_terminal_session_id = $session->id;
            $tan->save();

            // Audit + event
            $this->audit->record(
                $session,
                AuditAction::TERMINAL_LOGIN_TAN,
                null,
                $this->audit->snapshotModel($session),
                null,
                null
            );

            $this->events->record(
                $driverCheck === LoginResultCode::TRAINING_REQUIRED ? 'terminal.login_training_redirect' : 'terminal.login_tan',
                $session,
                $driverCheck === LoginResultCode::TRAINING_REQUIRED
                    ? "Driver {$driver->driver_code} identified via TAN but training required."
                    : "Driver {$driver->driver_code} signed in via TAN at {$terminal->code}.",
                [
                    'tan_id' => $tan->id,
                    'tan_reference' => $tan->tan_reference,
                    'driver_id' => $driver->id,
                    'terminal_id' => $terminal->id,
                    'result_code' => $driverCheck,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            if ($driverCheck === LoginResultCode::TRAINING_REQUIRED) {
                $this->audit->record(
                    $session,
                    AuditAction::TERMINAL_TRAINING_REDIRECT,
                    null,
                    ['driver_id' => $driver->id, 'training_status' => $driver->training_status],
                    null,
                    null
                );
            }

            return new TerminalAuthResult(
                code: $driverCheck,
                message: LoginResultCode::message($driverCheck),
                session: $session,
                driver: $driver,
                terminal: $terminal,
                nextRoute: $this->resolveNextRoute($driverCheck)
            );
        });
    }

    /* ============================================================
     * Chip card login (V3 §9.2)
     * ============================================================ */

    public function authenticateWithChipCard(
        string $terminalId,
        ?string $cardCode,
        ?string $maskedUid,
        ?string $language = null,
        bool $isSimulated = false
    ): TerminalAuthResult {
        $terminal = $this->resolveTerminal($terminalId);
        if (! $terminal) {
            return $this->failNoSession(LoginMethod::CHIP_CARD, LoginResultCode::TERMINAL_OFFLINE, 'Unknown terminal.', $terminalId);
        }
        if ($terminal->status === 'service_mode') {
            return $this->failNoSession(LoginMethod::CHIP_CARD, LoginResultCode::TERMINAL_SERVICE_MODE, 'Terminal is in service mode.', $terminalId);
        }
        if (! $terminal->is_active) {
            return $this->failNoSession(LoginMethod::CHIP_CARD, LoginResultCode::TERMINAL_OFFLINE, 'Terminal not active.', $terminalId);
        }

        $chip = $this->lookupChipCard($cardCode, $maskedUid);

        return DB::transaction(function () use ($terminal, $chip, $cardCode, $maskedUid, $language, $isSimulated) {
            if (! $chip) {
                return $this->logFailure(
                    $terminal, LoginMethod::CHIP_CARD, LoginResultCode::CHIP_UNASSIGNED,
                    "Chip card not recognized.", $language, null,
                    ['card_code' => $cardCode, 'masked_uid' => $maskedUid, 'is_simulated' => $isSimulated]
                );
            }

            $code = $this->evaluateChipLifecycle($chip);
            if ($code !== LoginResultCode::SUCCESS) {
                return $this->logFailure(
                    $terminal, LoginMethod::CHIP_CARD, $code,
                    LoginResultCode::message($code), $language, $chip->driver_id,
                    ['chip_id' => $chip->id, 'is_simulated' => $isSimulated]
                );
            }

            $driver = $chip->driver_id ? Driver::find($chip->driver_id) : null;
            if (! $driver) {
                return $this->logFailure(
                    $terminal, LoginMethod::CHIP_CARD, LoginResultCode::CHIP_UNASSIGNED,
                    "Chip card is not assigned to a driver.", $language, null,
                    ['chip_id' => $chip->id]
                );
            }

            $driverCheck = $this->evaluateDriverEligibility($driver);
            if ($driverCheck !== LoginResultCode::SUCCESS && $driverCheck !== LoginResultCode::TRAINING_REQUIRED) {
                return $this->logFailure(
                    $terminal, LoginMethod::CHIP_CARD, $driverCheck,
                    LoginResultCode::message($driverCheck), $language, $driver->id,
                    ['chip_id' => $chip->id]
                );
            }

            $session = $this->openSession($terminal, $driver, $language, LoginMethod::CHIP_CARD, $driverCheck);

            // Stamp chip last_used context
            $chip->last_used_at = now();
            $chip->last_used_context = "terminal:{$terminal->code}";
            $chip->last_used_source = $isSimulated ? 'demo' : 'reader';
            $chip->last_usage_result = $driverCheck === LoginResultCode::TRAINING_REQUIRED ? 'training_required' : 'success';
            $chip->save();

            $this->audit->record(
                $session,
                AuditAction::TERMINAL_LOGIN_CHIP,
                null,
                $this->audit->snapshotModel($session),
                null,
                null
            );
            $this->events->record(
                $driverCheck === LoginResultCode::TRAINING_REQUIRED ? 'terminal.login_training_redirect' : 'terminal.login_chip',
                $session,
                $driverCheck === LoginResultCode::TRAINING_REQUIRED
                    ? "Driver {$driver->driver_code} identified via chip but training required."
                    : "Driver {$driver->driver_code} signed in via chip at {$terminal->code}.",
                [
                    'chip_id' => $chip->id,
                    'card_code' => $chip->card_code,
                    'driver_id' => $driver->id,
                    'terminal_id' => $terminal->id,
                    'is_simulated' => $isSimulated,
                    'result_code' => $driverCheck,
                ],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            if ($driverCheck === LoginResultCode::TRAINING_REQUIRED) {
                $this->audit->record(
                    $session,
                    AuditAction::TERMINAL_TRAINING_REDIRECT,
                    null,
                    ['driver_id' => $driver->id, 'training_status' => $driver->training_status],
                    null,
                    null
                );
            }

            return new TerminalAuthResult(
                code: $driverCheck,
                message: LoginResultCode::message($driverCheck),
                session: $session,
                driver: $driver,
                terminal: $terminal,
                nextRoute: $this->resolveNextRoute($driverCheck)
            );
        });
    }

    /* ============================================================
     * Manager / Operator logon (V3 §11)
     * ============================================================ */

    /**
     * Validate a dashboard user via username + password and issue a
     * Sanctum personal access token bound to that user.
     *
     * @return array{success: bool, code: string, message: string, token?: string, user?: array, nextRoute?: string}
     */
    public function managerLogon(string $username, string $password, ?string $terminalId = null): array
    {
        $user = \App\Models\User::query()->where('username', $username)->first();

        // Generic invalid-credential message for both unknown-user and
        // wrong-password (V3 §11.2 "Do not reveal whether username or
        // password alone is wrong"). Failed attempt logged either way.
        if (! $user || ! Hash::check($password, $user->password)) {
            $this->audit->record(
                'user',
                AuditAction::TERMINAL_MANAGER_LOGON_FAILED,
                null,
                ['username' => $username, 'terminal_id' => $terminalId],
                'Invalid credentials',
                null,
                $user?->getKey() ? (string) $user->getKey() : ''
            );
            $this->events->record(
                'terminal.manager_logon_failed',
                $user,
                "Manager logon failed for '{$username}'",
                ['username' => $username, 'terminal_id' => $terminalId],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );
            return [
                'success' => false,
                'code' => 'invalid_credentials',
                'message' => 'Login failed. Check username and password.',
            ];
        }

        if (! $user->is_active || ! is_null($user->disabled_at)) {
            $this->events->record(
                'terminal.manager_logon_denied',
                $user,
                "Manager logon denied (account disabled): {$user->username}",
                ['terminal_id' => $terminalId, 'reason' => 'disabled'],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );
            return [
                'success' => false,
                'code' => 'account_disabled',
                'message' => 'Access not permitted. Contact admin.',
            ];
        }

        if ($user->is_locked) {
            $this->events->record(
                'terminal.manager_logon_denied',
                $user,
                "Manager logon denied (account locked): {$user->username}",
                ['terminal_id' => $terminalId, 'reason' => 'locked'],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );
            return [
                'success' => false,
                'code' => 'account_locked',
                'message' => 'Account locked. Contact admin.',
            ];
        }

        // Issue Sanctum token (24h TTL). FE stores this and sends as
        // Authorization: Bearer for dashboard API calls — when those
        // endpoints get auth middleware added later, this token is the
        // session.
        $token = $user->createToken("terminal:{$terminalId}", ['*'], now()->addHours(24))->plainTextToken;

        $user->last_login_at = now();
        $user->save();

        $this->audit->record(
            $user,
            AuditAction::TERMINAL_MANAGER_LOGON,
            null,
            ['username' => $user->username, 'terminal_id' => $terminalId],
            null,
            null
        );
        $this->events->record(
            'terminal.manager_logon',
            $user,
            "Manager {$user->username} logged on at terminal " . ($terminalId ?? '(unknown)'),
            ['terminal_id' => $terminalId],
            EventCategory::OPERATIONS,
            EventSeverity::INFO
        );

        return [
            'success' => true,
            'code' => 'success',
            'message' => 'Welcome.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles?->pluck('name')->toArray() ?? [],
                'preferredLanguage' => $user->preferred_language,
            ],
            'nextRoute' => '/dashboard',
        ];
    }

    /* ============================================================
     * Session lifecycle (language change, logout)
     * ============================================================ */

    public function changeLanguage(TerminalSession $session, string $cultureCode): TerminalSession
    {
        $old = $this->audit->snapshotModel($session);
        $previous = $session->notes; // free-text; we don't keep a dedicated column
        $stamp = now()->toIso8601String();
        $session->notes = ($previous ? rtrim($previous) . "\n" : '')
            . "[{$stamp}] language_changed -> {$cultureCode}";
        $session->last_activity_at = now();
        $session->save();

        $this->audit->record(
            $session,
            AuditAction::TERMINAL_LANGUAGE_CHANGED,
            $old,
            $this->audit->snapshotModel($session),
            "Language changed to {$cultureCode}",
            null
        );
        $this->events->record(
            'terminal.language_changed',
            $session,
            "Terminal session {$session->session_no} language → {$cultureCode}",
            ['culture_code' => $cultureCode],
            EventCategory::OPERATIONS,
            EventSeverity::INFO
        );

        return $session->fresh();
    }

    public function logoutSession(TerminalSession $session, string $reason = 'manual'): TerminalSession
    {
        $old = $this->audit->snapshotModel($session);
        $session->session_state = GateTerminalSessionState::IDLE;
        $session->current_screen = null;
        $session->last_activity_at = now();
        $session->save();

        $this->audit->record(
            $session,
            $reason === 'timeout' ? AuditAction::TERMINAL_SESSION_TIMEOUT : AuditAction::TERMINAL_SESSION_LOGOUT,
            $old,
            $this->audit->snapshotModel($session),
            $reason === 'timeout' ? 'Inactivity timeout' : 'Manual logout',
            null
        );
        $this->events->record(
            $reason === 'timeout' ? 'terminal.session_timeout' : 'terminal.session_logout',
            $session,
            "Terminal session {$session->session_no} ended ({$reason}).",
            ['reason' => $reason],
            EventCategory::OPERATIONS,
            EventSeverity::INFO
        );

        return $session->fresh();
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    protected function resolveTerminal(string $terminalId): ?TerminalPanel
    {
        // Accept either UUID id or the `code` for FE convenience.
        return TerminalPanel::query()
            ->where(function ($q) use ($terminalId) {
                $q->where('id', $terminalId)->orWhere('code', $terminalId);
            })
            ->first();
    }

    protected function lookupChipCard(?string $cardCode, ?string $maskedUid): ?AuthMedium
    {
        $q = AuthMedium::query()->chipCards();
        if (! empty($cardCode)) {
            $row = (clone $q)->where('card_code', $cardCode)->first();
            if ($row) {
                return $row;
            }
        }
        if (! empty($maskedUid)) {
            return $q->where('masked_uid', $maskedUid)->first();
        }
        return null;
    }

    protected function evaluateTanLifecycle(AuthMedium $tan): string
    {
        // V1.3 §5.1 — TAN must be active, single-use, in validity window
        if ($tan->status === AuthMediumStatus::BLOCKED) {
            return LoginResultCode::REVOKED;
        }
        if (! is_null($tan->revoked_at)) {
            return LoginResultCode::REVOKED;
        }
        if ($tan->usage_state === TanUsageState::CONSUMED || ! is_null($tan->consumed_at)) {
            return LoginResultCode::USED;
        }
        if ($tan->usage_state === TanUsageState::BLOCKED) {
            return LoginResultCode::REVOKED;
        }
        if ($tan->expires_at && Carbon::now()->greaterThan($tan->expires_at)) {
            return LoginResultCode::EXPIRED;
        }
        if ($tan->valid_from && Carbon::now()->lessThan($tan->valid_from)) {
            return LoginResultCode::PENDING;
        }
        if ($tan->status !== AuthMediumStatus::ACTIVE) {
            return LoginResultCode::INVALID;
        }
        return LoginResultCode::SUCCESS;
    }

    protected function evaluateChipLifecycle(AuthMedium $chip): string
    {
        // V3 §17 chip rules + ChipCards spec lifecycle.
        if ($chip->status === AuthMediumStatus::BLOCKED) {
            return LoginResultCode::CHIP_BLOCKED;
        }
        if (in_array($chip->status, [
            AuthMediumStatus::LOST,
            AuthMediumStatus::DEFECTIVE,
            AuthMediumStatus::REPLACED,
            AuthMediumStatus::ARCHIVED,
            AuthMediumStatus::EXPIRED,
        ], true)) {
            return LoginResultCode::CHIP_BLOCKED;
        }
        if ($chip->assignment_state !== ChipCardAssignmentState::ASSIGNED) {
            return LoginResultCode::CHIP_UNASSIGNED;
        }
        if ($chip->expires_at && Carbon::now()->greaterThan($chip->expires_at)) {
            return LoginResultCode::EXPIRED;
        }
        if (empty($chip->driver_id)) {
            return LoginResultCode::CHIP_UNASSIGNED;
        }
        return LoginResultCode::SUCCESS;
    }

    /**
     * Driver eligibility check per V3 §9.4.
     *
     * Returns SUCCESS when driver may continue immediately,
     * TRAINING_REQUIRED when identified but blocked from operational flow,
     * or one of DRIVER_BLOCKED / DRIVER_INACTIVE when the driver is hard-stopped.
     */
    protected function evaluateDriverEligibility(Driver $driver): string
    {
        if (! $driver->is_active) {
            return LoginResultCode::DRIVER_INACTIVE;
        }
        if (! in_array($driver->block_status, ['clear', null], true)) {
            return LoginResultCode::DRIVER_BLOCKED;
        }

        // Training check (V3 §9.4 step 3 + 4)
        $status = $driver->training_status ?? TrainingStatus::UNKNOWN;
        if ($status === TrainingStatus::NOT_REQUIRED) {
            return LoginResultCode::SUCCESS;
        }
        if ($status === TrainingStatus::VALID) {
            if ($driver->training_valid_until && Carbon::now()->greaterThan(Carbon::parse($driver->training_valid_until))) {
                return LoginResultCode::TRAINING_REQUIRED;
            }
            return LoginResultCode::SUCCESS;
        }
        // missing / expired / unknown all route to safety training
        return LoginResultCode::TRAINING_REQUIRED;
    }

    protected function openSession(
        TerminalPanel $terminal,
        Driver $driver,
        ?string $language,
        string $method,
        string $resultCode
    ): TerminalSession {
        $session = TerminalSession::create([
            'id' => (string) Str::uuid(),
            'touchpoint' => GateTerminalTouchpoint::DRIVER_TERMINAL,
            'device_id' => $terminal->id,
            'device_label' => $terminal->name,
            'driver_id' => $driver->id,
            'driver_name' => trim("{$driver->first_name} {$driver->last_name}"),
            'driver_code' => $driver->driver_code,
            'current_screen' => $resultCode === LoginResultCode::TRAINING_REQUIRED
                ? GateTerminalCurrentScreen::DRIVER_LOGIN  // stays on login, FE routes to training
                : GateTerminalCurrentScreen::TRAILER_IDENTIFICATION,
            'session_state' => GateTerminalSessionState::ACTIVE,
            'issue_reason' => $resultCode === LoginResultCode::TRAINING_REQUIRED
                ? 'Safety training required before operational access.'
                : null,
            'action_needed' => $resultCode === LoginResultCode::TRAINING_REQUIRED
                ? 'Open Safety Training.'
                : null,
            'last_activity_at' => now(),
            'correlation_id' => request()?->header('X-Correlation-Id'),
        ]);

        // Persist preferred language onto session notes (no dedicated col).
        $resolvedLang = $this->resolveLanguage($terminal, $language ?? $driver->preferred_culture_code);
        $stamp = now()->toIso8601String();
        $session->notes = "[{$stamp}] login_method={$method} language={$resolvedLang}";
        $session->save();

        return $session;
    }

    /**
     * Log a failed identification attempt as a DENIED terminal session so
     * Gate & Terminal Monitor surfaces it on the touchpoint card (V2.3 §6.3
     * priority 2). Driver is unknown for invalid TAN/chip; populate where
     * available.
     */
    protected function logFailure(
        TerminalPanel $terminal,
        string $method,
        string $code,
        string $message,
        ?string $language,
        ?string $driverId,
        array $details = []
    ): TerminalAuthResult {
        $driver = $driverId ? Driver::find($driverId) : null;
        $session = TerminalSession::create([
            'id' => (string) Str::uuid(),
            'touchpoint' => GateTerminalTouchpoint::DRIVER_TERMINAL,
            'device_id' => $terminal->id,
            'device_label' => $terminal->name,
            'driver_id' => $driverId,
            'driver_name' => $driver ? trim("{$driver->first_name} {$driver->last_name}") : null,
            'driver_code' => $driver?->driver_code,
            'current_screen' => GateTerminalCurrentScreen::DRIVER_LOGIN,
            'session_state' => GateTerminalSessionState::DENIED,
            'issue_reason' => $message,
            'action_needed' => 'Retry login or contact terminal support.',
            'last_activity_at' => now(),
            'correlation_id' => request()?->header('X-Correlation-Id'),
            'notes' => "[" . now()->toIso8601String() . "] failed_login method={$method} code={$code}",
        ]);

        $this->audit->record(
            $session,
            AuditAction::TERMINAL_LOGIN_FAILED,
            null,
            array_merge($this->audit->snapshotModel($session), $details),
            $message,
            $code
        );
        $this->events->record(
            'terminal.login_failed',
            $session,
            "Terminal login failed at {$terminal->code} ({$method} → {$code})",
            array_merge($details, [
                'method' => $method,
                'result_code' => $code,
                'driver_id' => $driverId,
                'terminal_id' => $terminal->id,
            ]),
            EventCategory::OPERATIONS,
            in_array($code, [LoginResultCode::DRIVER_BLOCKED, LoginResultCode::CHIP_BLOCKED, LoginResultCode::TOO_MANY_ATTEMPTS], true)
                ? EventSeverity::WARNING
                : EventSeverity::INFO
        );

        return new TerminalAuthResult(
            code: $code,
            message: $message,
            session: $session,
            driver: $driver,
            terminal: $terminal,
            nextRoute: null
        );
    }

    /**
     * Failure path when no terminal was resolvable — no session row is
     * created (we have no device to attach it to). The audit/event still
     * land for traceability.
     */
    protected function failNoSession(string $method, string $code, string $message, string $terminalIdAttempt): TerminalAuthResult
    {
        $this->audit->record(
            'terminal',
            AuditAction::TERMINAL_LOGIN_FAILED,
            null,
            ['method' => $method, 'code' => $code, 'terminal_id_attempt' => $terminalIdAttempt],
            $message,
            $code,
            $terminalIdAttempt
        );
        $this->events->record(
            'terminal.login_failed',
            'terminal',
            $message,
            ['method' => $method, 'result_code' => $code, 'terminal_id_attempt' => $terminalIdAttempt],
            EventCategory::OPERATIONS,
            EventSeverity::WARNING,
            $terminalIdAttempt
        );
        return new TerminalAuthResult(
            code: $code,
            message: $message,
            session: null,
            driver: null,
            terminal: null,
            nextRoute: null
        );
    }

    protected function resolveLanguage(TerminalPanel $terminal, ?string $requested): string
    {
        $supported = is_array($terminal->language_support) ? $terminal->language_support : ['de'];
        if (! empty($requested) && in_array($requested, $supported, true)) {
            return $requested;
        }
        return in_array('de', $supported, true) ? 'de' : ($supported[0] ?? 'de');
    }

    protected function resolveNextRoute(string $code): ?string
    {
        return match ($code) {
            LoginResultCode::SUCCESS => '/terminal/trailer-identification',
            LoginResultCode::TRAINING_REQUIRED => '/terminal/safety-training',
            default => null,
        };
    }

    protected function maskTan(string $raw): string
    {
        $len = strlen($raw);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return substr($raw, 0, 2) . str_repeat('*', $len - 4) . substr($raw, -2);
    }
}
