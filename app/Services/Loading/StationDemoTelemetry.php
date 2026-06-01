<?php

namespace App\Services\Loading;

use App\Enums\BayLineStatus;
use App\Models\BayLine;
use App\Models\LoadingOperation;

/**
 * Deterministic fake-but-realistic telemetry / safety / maintenance for the
 * Station-View card extensions (V3.2 §3.7).
 *
 * The fields listed below have NO upstream source in MVP — no PLC gateway,
 * no maintenance table, no interlock feed. To keep the FE looking alive
 * during demos, this helper synthesises plausible values from the bay id
 * so:
 *
 *   - the same bay always produces the same numbers (so reseeding /
 *     refreshing doesn't make the value flicker)
 *   - bay-to-bay values differ (so the dashboard looks varied rather than
 *     showing identical numbers everywhere)
 *
 * Gated by `config('loading_control.demo_telemetry')` (default `true` in
 * dev, `false` in production). When the real upstream lands, flip the flag
 * off and the resource emits the actual values / null instead.
 *
 * Each helper accepts the value the resource already has and returns it
 * unchanged when non-null, so once a real source lands the synthesiser
 * naturally yields.
 */
class StationDemoTelemetry
{
    /** Tiny bias used to vary numbers per bay without going off-spec. */
    protected static function seed(BayLine $bay): int
    {
        // crc32 of the UUID gives a stable, well-distributed integer.
        // Modulo arithmetic keeps numbers in the demo ranges below.
        return crc32((string) $bay->id);
    }

    public static function enabled(): bool
    {
        return (bool) config('loading_control.demo_telemetry', false);
    }

    /* ---------- numeric telemetry ---------- */

    /** Returns a current-pressure value in bar that is plausibly within bay capability. */
    public static function livePressure(BayLine $bay, ?LoadingOperation $active, ?float $capabilityBar): ?float
    {
        if (! self::enabled()) {
            return null;
        }
        if (! $active) {
            // Free bays show no PLC pressure (line vented).
            return 0.0;
        }
        $capability = $capabilityBar ?? 200.0;
        // Ride between 60% and 95% of capability, biased per bay.
        $jitter = (self::seed($bay) % 35) / 100.0; // 0.00 .. 0.34
        $pct = 0.60 + $jitter;
        return round($capability * $pct, 1);
    }

    public static function temperature(BayLine $bay, ?LoadingOperation $active): ?float
    {
        if (! self::enabled()) {
            return null;
        }
        // 18 .. 28 °C range, deterministic per bay.
        $base = 18.0 + ((self::seed($bay) % 100) / 10.0); // 18.0 .. 27.9
        if ($active) {
            // Active loading runs a couple of degrees warmer.
            $base += 1.5;
        }
        return round($base, 1);
    }

    public static function targetPressure(BayLine $bay, ?LoadingOperation $active, ?float $capabilityBar): ?float
    {
        if (! self::enabled() || ! $active) {
            return null;
        }
        $capability = $capabilityBar ?? 200.0;
        // Target lands at 90% of capability — typical for an H2 fill.
        return round($capability * 0.90, 0);
    }

    /* ---------- booleans / labels ---------- */

    public static function analysisRequired(BayLine $bay, ?LoadingOperation $active): ?bool
    {
        if (! self::enabled()) {
            return null;
        }
        // Bays 1 + 3 require pre-analysis; bays 2 + 4 don't. Stable per bay.
        return (self::seed($bay) % 2) === 0;
    }

    public static function processStep(?LoadingOperation $active, ?array $loadingState): ?string
    {
        // Resource already passes loadingState; we mirror the label so the FE
        // doesn't have to fall back. Demo-only behaviour — when the real
        // source lands, controller emits the richer human-readable text.
        if (! self::enabled()) {
            return null;
        }
        return $loadingState['label'] ?? null;
    }

    /* ---------- safety array ---------- */

    /**
     * @return array<int, array{label: string, state: string}>|null
     */
    public static function safety(BayLine $bay, string $bayStatus): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        // Stable base — every healthy bay reports these three interlocks OK.
        $base = [
            ['label' => 'Emergency stop', 'state' => 'ok'],
            ['label' => 'Earth bond',     'state' => 'ok'],
            ['label' => 'Door interlock', 'state' => 'ok'],
        ];

        // Faulted / blocked bays surface one downgraded interlock so the
        // card has a visible reason for the danger chip.
        if ($bayStatus === BayLineStatus::FAULT_BLOCKED) {
            $base[2]['state'] = 'danger';
        } elseif ($bayStatus === BayLineStatus::MAINTENANCE_OFFLINE) {
            $base[2]['state'] = 'pending';
        } elseif ($bayStatus === BayLineStatus::WAITING_ANALYSIS) {
            $base[1]['state'] = 'warning';
        }

        return $base;
    }

    /* ---------- maintenance object ---------- */

    /**
     * @return array{status: string|null, last_service_at: string|null, active_issues: int}|null
     */
    public static function maintenance(BayLine $bay, string $bayStatus): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        // Pick one of {ok, due, overdue} per bay so the dashboard shows
        // variety. Deterministic per bay (so the same bay always reports
        // the same maintenance state across refreshes).
        $pick = self::seed($bay) % 6;
        if ($bayStatus === BayLineStatus::MAINTENANCE_OFFLINE) {
            $status = 'overdue';
            $issues = 2;
            $days = 18;
        } elseif ($pick === 0) {
            $status = 'overdue';
            $issues = 1;
            $days = 8;
        } elseif ($pick === 1 || $pick === 2) {
            $status = 'due';
            $issues = 0;
            $days = 35;
        } else {
            $status = 'ok';
            $issues = 0;
            $days = 14;
        }

        return [
            'status' => $status,
            // Deterministic-ish timestamp — N days back from app boot.
            // (We don't have a real maintenance table yet.)
            'last_service_at' => now()->subDays($days)->startOfDay()->toIso8601String(),
            'active_issues' => $issues,
        ];
    }
}
