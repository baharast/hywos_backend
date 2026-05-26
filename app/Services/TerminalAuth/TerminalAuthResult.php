<?php

namespace App\Services\TerminalAuth;

use App\Models\Driver;
use App\Models\TerminalSession;
use App\Models\TerminalPanel;

/**
 * Outcome of a driver terminal authentication attempt.
 *
 * `code` is one of App\Enums\LoginResultCode and is the source of truth
 * for FE routing. `nextRoute` is a hint computed by the service per V3
 * §9.4 + V6 §6.14; FE may override based on its own router config.
 * `method` is one of App\Enums\LoginMethod and drives:
 *   - the V6 §10.3 flat `method` field on the response
 *   - the method-aware nextRoute (chip → /trailer-info, tan → /trailer-check)
 *   - LoginResultCode::v6Code() so failure codes get the right prefix
 */
class TerminalAuthResult
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly ?TerminalSession $session,
        public readonly ?Driver $driver,
        public readonly ?TerminalPanel $terminal,
        public readonly ?string $nextRoute,
        public readonly string $method = ''
    ) {}

    public function isIdentified(): bool
    {
        return \App\Enums\LoginResultCode::isIdentified($this->code);
    }
}
