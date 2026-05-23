<?php

namespace App\Services\Drivers;

use App\Models\Driver;

class DriverEligibilityService
{
    /**
     * Evaluate whether a driver may pass the entry gate.
     *
     * Returns a structured result so the controller / future gate workflow can map
     * blocking reasons to error codes per docs/technical/06-audit-event-error-conventions.md.
     */
    public function evaluateForGateEntry(Driver $driver, ?string $orderId = null): array
    {
        $reasons = $this->commonDriverBlockers($driver);

        return $this->result($reasons, 'GATE_ENTRY_ALLOWED', 'GATE_ENTRY_DENIED');
    }

    /**
     * Evaluate whether a driver may be assigned to an order.
     */
    public function evaluateForAssignment(Driver $driver): array
    {
        $reasons = $this->commonDriverBlockers($driver);

        return $this->result($reasons, 'DRIVER_ASSIGNABLE', 'DRIVER_NOT_ASSIGNABLE');
    }

    /**
     * Stub: depends on PlantVisit model which does not exist yet.
     * Returns a "not available" result so callers can degrade gracefully.
     */
    public function evaluateForTerminalLogin(Driver $driver, /* PlantVisit */ $visit): array
    {
        return [
            'allowed' => false,
            'blockingReasons' => ['MODULE_NOT_AVAILABLE'],
            'warnings' => [],
            'messageCode' => 'TERMINAL_LOGIN_MODULE_NOT_AVAILABLE',
        ];
    }

    /**
     * Stub: depends on OrderOperation model which does not exist yet.
     */
    public function evaluateForLoadingRelease(Driver $driver, /* OrderOperation */ $op): array
    {
        return [
            'allowed' => false,
            'blockingReasons' => ['MODULE_NOT_AVAILABLE'],
            'warnings' => [],
            'messageCode' => 'LOADING_RELEASE_MODULE_NOT_AVAILABLE',
        ];
    }

    /**
     * Shared blocker checks that apply across most workflows.
     *
     * @return string[]
     */
    protected function commonDriverBlockers(Driver $driver): array
    {
        $reasons = [];

        if (! $driver->is_active) {
            $reasons[] = 'DRIVER_INACTIVE';
        }
        if ($driver->block_status === 'blocked') {
            $reasons[] = 'DRIVER_BLOCKED';
        }
        if ($driver->license_expiry_date && now()->gt($driver->license_expiry_date)) {
            $reasons[] = 'LICENSE_EXPIRED';
        }
        if (in_array($driver->training_status, ['expired', 'missing'], true)) {
            $reasons[] = 'TRAINING_INVALID';
        }

        return $reasons;
    }

    /**
     * @param string[] $reasons
     */
    protected function result(array $reasons, string $allowedCode, string $deniedCode): array
    {
        $allowed = empty($reasons);

        return [
            'allowed' => $allowed,
            'blockingReasons' => $reasons,
            'warnings' => [],
            'messageCode' => $allowed ? $allowedCode : $deniedCode,
        ];
    }
}
