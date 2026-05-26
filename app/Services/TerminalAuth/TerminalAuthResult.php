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
 * §9.4; FE may override based on its own router config.
 */
class TerminalAuthResult
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly ?TerminalSession $session,
        public readonly ?Driver $driver,
        public readonly ?TerminalPanel $terminal,
        public readonly ?string $nextRoute
    ) {}

    public function isIdentified(): bool
    {
        return \App\Enums\LoginResultCode::isIdentified($this->code);
    }
}
