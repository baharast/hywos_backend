<?php

namespace App\Services\HardwareDevice;

use App\Enums\AuditAction;
use App\Enums\EventCategory;
use App\Enums\EventSeverity;
use App\Enums\HardwareConnectionTestResult;
use App\Enums\HardwareDeviceCriticality;
use App\Enums\HardwareDeviceHealth;
use App\Enums\HardwareDeviceType;
use App\Models\HardwareDevice;
use App\Services\Audit\AuditLogger;
use App\Services\Events\EventLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the Hardware Devices write path + the
 * Operational Summary Bar (V1.4 §4.2) and "available filter values"
 * computation (V1.4 §3 + §11).
 *
 * The controller is a thin shell; all 3 safe writes + the summary math
 * live here.
 */
class HardwareDeviceService
{
    public function __construct(
        protected AuditLogger $audit,
        protected EventLogger $events,
    ) {}

    /* ============================================================
     * Writes (V1.4 §10 — service mode + connection test)
     * ============================================================ */

    /**
     * Set a device into service mode.
     *
     * @throws \DomainException 409 ALREADY_IN_SERVICE_MODE
     */
    public function setServiceMode(HardwareDevice $d, string $reason, ?Carbon $endsAt = null): HardwareDevice
    {
        if ($d->service_mode) {
            throw new \DomainException('ALREADY_IN_SERVICE_MODE');
        }

        return DB::transaction(function () use ($d, $reason, $endsAt) {
            $old = $this->audit->snapshotModel($d);
            $previousHealth = $d->health;

            $d->service_mode = true;
            $d->service_mode_reason = $reason;
            $d->service_mode_set_at = now();
            $d->service_mode_expected_end_at = $endsAt;
            // Health flips to service_mode so the summary bar counts it
            // correctly and the badge tone matches V1.4 §3.
            $d->health = HardwareDeviceHealth::SERVICE_MODE;
            $d->last_event_at = now();
            $d->last_message = "Service mode set: {$reason}";
            $d->save();

            $fresh = $d->fresh();
            $this->audit->record(
                $d,
                AuditAction::HARDWARE_DEVICE_SERVICE_MODE_SET,
                $old,
                $this->audit->snapshotModel($fresh),
                $reason,
                null
            );
            $this->events->record(
                'hardware_device.service_mode_set',
                $d,
                "Device {$d->asset_tag} placed in service mode",
                [
                    'previous_health' => $previousHealth,
                    'reason' => $reason,
                    'expected_end_at' => $endsAt?->toIso8601String(),
                ],
                EventCategory::OPERATIONS,
                EventSeverity::WARNING
            );

            return $fresh;
        });
    }

    /**
     * Restore a device from service mode.
     *
     * @throws \DomainException 409 NOT_IN_SERVICE_MODE
     */
    public function clearServiceMode(HardwareDevice $d, string $reason): HardwareDevice
    {
        if (! $d->service_mode) {
            throw new \DomainException('NOT_IN_SERVICE_MODE');
        }

        return DB::transaction(function () use ($d, $reason) {
            $old = $this->audit->snapshotModel($d);

            $d->service_mode = false;
            $d->service_mode_reason = null;
            $d->service_mode_set_at = null;
            $d->service_mode_set_by_user_id = null;
            $d->service_mode_expected_end_at = null;
            // We don't know the real device health post-restore — the
            // backend would normally observe it via PLC heartbeats. For
            // this demo we mark it `unknown` so the operator can confirm
            // recovery from the live source rather than the dashboard
            // pretending the device is online again.
            $d->health = HardwareDeviceHealth::UNKNOWN;
            $d->last_event_at = now();
            $d->last_message = "Restored from service mode: {$reason}";
            $d->save();

            $fresh = $d->fresh();
            $this->audit->record(
                $d,
                AuditAction::HARDWARE_DEVICE_SERVICE_MODE_CLEARED,
                $old,
                $this->audit->snapshotModel($fresh),
                $reason,
                null
            );
            $this->events->record(
                'hardware_device.service_mode_cleared',
                $d,
                "Device {$d->asset_tag} restored from service mode",
                ['reason' => $reason],
                EventCategory::OPERATIONS,
                EventSeverity::INFO
            );

            return $fresh;
        });
    }

    /**
     * Safe non-invasive connection test (V1.4 §10).
     *
     * For demo MVP this does NOT actually probe the device — it stamps
     * `connection_test_last_run_at` and maps the result from current
     * health. Safety devices reject the test (FE shouldn't even surface
     * the button for them, but we double-check server-side).
     *
     * @throws \DomainException 409 CONNECTION_TEST_NOT_SUPPORTED
     */
    public function runConnectionTest(HardwareDevice $d): HardwareDevice
    {
        if ($d->device_type === HardwareDeviceType::SAFETY_DEVICE) {
            throw new \DomainException('CONNECTION_TEST_NOT_SUPPORTED');
        }

        return DB::transaction(function () use ($d) {
            $old = $this->audit->snapshotModel($d);

            $result = $this->mapHealthToTestResult($d->health);

            $d->connection_test_last_run_at = now();
            $d->connection_test_last_result = $result;
            $d->save();

            $fresh = $d->fresh();
            $this->audit->record(
                $d,
                AuditAction::HARDWARE_DEVICE_CONNECTION_TEST_RUN,
                $old,
                $this->audit->snapshotModel($fresh),
                null,
                null
            );
            $this->events->record(
                'hardware_device.connection_test_run',
                $d,
                "Connection test on {$d->asset_tag} → {$result}",
                [
                    'result' => $result,
                    'observed_health' => $d->health,
                ],
                EventCategory::OPERATIONS,
                // Failed/timeout tests should be visible in the journal
                // even when health was already abnormal.
                in_array($result, [
                    HardwareConnectionTestResult::FAILED,
                    HardwareConnectionTestResult::TIMEOUT,
                ], true) ? EventSeverity::WARNING : EventSeverity::INFO
            );

            return $fresh;
        });
    }

    protected function mapHealthToTestResult(?string $health): string
    {
        return match ($health) {
            HardwareDeviceHealth::ONLINE,
            HardwareDeviceHealth::WARNING => HardwareConnectionTestResult::PASSED,
            HardwareDeviceHealth::ALARM,
            HardwareDeviceHealth::FAULT => HardwareConnectionTestResult::FAILED,
            HardwareDeviceHealth::OFFLINE,
            HardwareDeviceHealth::UNKNOWN => HardwareConnectionTestResult::TIMEOUT,
            HardwareDeviceHealth::SERVICE_MODE => HardwareConnectionTestResult::NOT_SUPPORTED,
            default => HardwareConnectionTestResult::TIMEOUT,
        };
    }

    /* ============================================================
     * Summary bar + available filter values (V1.4 §4.2 + §3/§11)
     * ============================================================ */

    /**
     * Build the V1.4 §4.2 5-counter summary bar + the
     * `availableFilterValues` block used to render dropdown options
     * from the current dataset only.
     *
     * @return array{
     *   criticalDeviceBlockers:int,
     *   offlineCriticalDevices:int,
     *   fillingStationsAffected:int,
     *   readerPrinterImpact:int,
     *   serviceModeActive:int,
     *   availableFilterValues:array{healths:array<int,string>,deviceTypes:array<int,string>,subsystems:array<int,string>,locations:array<int,string>}
     * }
     */
    public function buildSummaryBar(): array
    {
        // §11 TBC rule: exclude tbc_engineering rows from operational
        // counters. They still appear in the list itself.
        $operational = HardwareDevice::query()->where(function (Builder $q) {
            $q->whereNull('data_status')->orWhere('data_status', '!=', 'tbc_engineering');
        });

        $criticalDeviceBlockers = (clone $operational)
            ->where('is_blocking_critical_process', true)
            ->count();

        $offlineCriticalDevices = (clone $operational)
            ->where('criticality', HardwareDeviceCriticality::CRITICAL)
            ->where('health', HardwareDeviceHealth::OFFLINE)
            ->count();

        $abnormalHealths = [
            HardwareDeviceHealth::WARNING,
            HardwareDeviceHealth::ALARM,
            HardwareDeviceHealth::FAULT,
            HardwareDeviceHealth::OFFLINE,
        ];

        $fillingStationsAffected = (clone $operational)
            ->where('subsystem', 'filling')
            ->whereIn('health', $abnormalHealths)
            ->count();

        $readerPrinterImpact = (clone $operational)
            ->whereIn('device_type', [
                HardwareDeviceType::PRINTER,
                HardwareDeviceType::RFID_READER,
                HardwareDeviceType::TRAILER_CHIP_READER,
            ])
            ->whereIn('health', $abnormalHealths)
            ->count();

        $serviceModeActive = (clone $operational)
            ->where('service_mode', true)
            ->count();

        return [
            'criticalDeviceBlockers' => $criticalDeviceBlockers,
            'offlineCriticalDevices' => $offlineCriticalDevices,
            'fillingStationsAffected' => $fillingStationsAffected,
            'readerPrinterImpact' => $readerPrinterImpact,
            'serviceModeActive' => $serviceModeActive,
            'availableFilterValues' => $this->availableFilterValues(),
        ];
    }

    /**
     * §3 + §11 rule: filter dropdown options come from the CURRENT TAB
     * DATASET (here = whole registry minus tbc_engineering rows). We
     * DISTINCT each categorical column so the FE never renders a
     * dropdown value with zero rows behind it.
     *
     * @return array{healths:array<int,string>,deviceTypes:array<int,string>,subsystems:array<int,string>,locations:array<int,string>}
     */
    public function availableFilterValues(): array
    {
        $base = HardwareDevice::query()->where(function (Builder $q) {
            $q->whereNull('data_status')->orWhere('data_status', '!=', 'tbc_engineering');
        });

        return [
            'healths' => (clone $base)->distinct()->pluck('health')->filter()->values()->all(),
            'deviceTypes' => (clone $base)->distinct()->pluck('device_type')->filter()->values()->all(),
            'subsystems' => (clone $base)->distinct()->pluck('subsystem')->filter()->values()->all(),
            'locations' => (clone $base)->distinct()->pluck('physical_location')->filter()->values()->all(),
        ];
    }

    /**
     * V1.4 §9 default sort SQL expression. Returns a string the caller
     * can drop into `orderByRaw()` so callers don't need to know the
     * priority ladder.
     */
    public function defaultSortExpression(): string
    {
        $cases = [];
        foreach (HardwareDeviceHealth::all() as $h) {
            $priority = HardwareDeviceHealth::displayPriority($h);
            // Health values come from a fixed enum we control, so this
            // CASE input is safe to inline without bindings.
            $cases[] = "WHEN '{$h}' THEN {$priority}";
        }
        $caseSql = 'CASE health ' . implode(' ', $cases) . ' ELSE 99 END';
        return "is_blocking_critical_process DESC, {$caseSql} ASC, last_event_at DESC";
    }
}
