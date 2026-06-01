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

    /**
     * Current PLC line pressure in bar. Active bays sit close to capability
     * (an in-flight H2 fill operates at 85–98% of the bay's rated pressure);
     * free / preparing bays are vented.
     */
    public static function livePressure(BayLine $bay, ?LoadingOperation $active, ?float $capabilityBar): ?float
    {
        if (! self::enabled()) {
            return null;
        }
        if (! $active) {
            return 0.0;
        }
        $capability = $capabilityBar ?? 200.0;
        // 85% .. 98% of capability, deterministic per bay.
        $jitter = (self::seed($bay) % 14) / 100.0; // 0.00 .. 0.13
        $pct = 0.85 + $jitter;
        return round($capability * $pct, 1);
    }

    /**
     * Current temperature in °C. Hydrogen fills at high pressure produce
     * sharp pre-cooling at the dispenser (real-world H2 stations cool the
     * gas to roughly -20 °C to -40 °C). Idle bays sit at ambient.
     */
    public static function temperature(BayLine $bay, ?LoadingOperation $active): ?float
    {
        if (! self::enabled()) {
            return null;
        }
        if ($active) {
            // Active fill — cold pre-cooling, -10 °C to -40 °C per bay.
            $cold = -10.0 - ((self::seed($bay) % 30)); // -10 .. -39
            return round($cold, 0);
        }
        // Ambient idle — 18 °C to 22 °C per bay.
        $ambient = 18.0 + ((self::seed($bay) % 50) / 10.0); // 18.0 .. 22.9
        return round($ambient, 0);
    }

    /**
     * Target pressure (bar) for the current loading — H2 fills are run at
     * the bay's rated capability, so we expose that as the target. `null`
     * when no loading is active.
     */
    public static function targetPressure(?LoadingOperation $active, ?float $capabilityBar): ?float
    {
        if (! self::enabled() || ! $active) {
            return null;
        }
        return $capabilityBar !== null ? round($capabilityBar, 0) : null;
    }

    /* ---------- booleans / labels ---------- */

    public static function analysisRequired(BayLine $bay): ?bool
    {
        if (! self::enabled()) {
            return null;
        }
        // Bays 1 + 3 require pre-analysis; bays 2 + 4 don't. Stable per bay.
        return (self::seed($bay) % 2) === 0;
    }

    public static function processStep(?array $loadingState): ?string
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

        // Four operational permission gates — match the FE bay-card spec
        // wording. Every healthy bay reports them all OK.
        $base = [
            ['label' => 'Driver Certified',   'state' => 'ok'],
            ['label' => 'Trailer Approved',   'state' => 'ok'],
            ['label' => 'Pressure Match',     'state' => 'ok'],
            ['label' => 'Certificates Valid', 'state' => 'ok'],
        ];

        // Faulted / blocked / waiting / maintenance bays surface one
        // downgraded gate so the card has a visible reason for the chip.
        if ($bayStatus === BayLineStatus::FAULT_BLOCKED) {
            $base[1]['state'] = 'danger';      // Trailer Approved → danger
        } elseif ($bayStatus === BayLineStatus::MAINTENANCE_OFFLINE) {
            // All gates "pending" while the bay is offline for service.
            foreach (array_keys($base) as $i) {
                $base[$i]['state'] = 'pending';
            }
        } elseif ($bayStatus === BayLineStatus::WAITING_ANALYSIS) {
            $base[2]['state'] = 'warning';     // Pressure Match → warning
        } else {
            // Healthy bay — add a fifth "Temp Deviation" gate occasionally
            // (every ~3rd bay deterministically) so a couple of cards show
            // an extra row, matching the reference design.
            if ((self::seed($bay) % 3) === 0) {
                $base[] = ['label' => 'Temp Deviation', 'state' => 'warning'];
            }
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
