<?php

namespace App\Enums;

/**
 * The 6 MVP gas components measured + evaluated by Analysis & Quality.
 *
 * Shared between:
 *   - Products & Quality Specifications (per-component min/max limits)
 *   - Calibration Settings (per-component reference values + tolerances)
 *   - Analysis Devices (latest readings per component on the analyser)
 *   - Active Analyses + Results & Quality Decisions (when those land)
 *
 * Order is canonical: H2 first (purity component), then the 5 impurity
 * gases in the order used on the FillTrack certificate / V2.1 §4.3.
 */
class GasComponent
{
    public const H2 = 'H2';
    public const O2 = 'O2';
    public const N2 = 'N2';
    public const CH4 = 'CH4';
    public const CO = 'CO';
    public const CO2 = 'CO2';

    public static function all(): array
    {
        return [self::H2, self::O2, self::N2, self::CH4, self::CO, self::CO2];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::H2 => 'Hydrogen',
            self::O2 => 'Oxygen',
            self::N2 => 'Nitrogen',
            self::CH4 => 'Methane',
            self::CO => 'Carbon monoxide',
            self::CO2 => 'Carbon dioxide',
            default => $v,
        };
    }

    /**
     * Default limit direction per V2.1 §4.3:
     *   - H2 (purity)  → `min` (typical lower-limit acceptance)
     *   - O2/N2/CH4/CO/CO2 (impurities) → `max`
     *
     * The FE uses this as the default field-highlight when adding a row;
     * the actual stored limits are explicit `lower_limit` / `upper_limit`.
     */
    public static function defaultLimitDirection(string $v): string
    {
        return $v === self::H2 ? 'min' : 'max';
    }

    /**
     * Display order for table rendering / certificate row order. H2 is
     * always first; the impurities follow in the canonical order above.
     */
    public static function displayOrder(string $v): int
    {
        return match ($v) {
            self::H2 => 1,
            self::O2 => 2,
            self::N2 => 3,
            self::CH4 => 4,
            self::CO => 5,
            self::CO2 => 6,
            default => 99,
        };
    }
}
