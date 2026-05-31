<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot data normalisation for `auth_media.card_type`.
 *
 * Older seeder runs (pre-2026-05) wrote `card_type='driver_chip'` on
 * CC-0001..0005 for the TerminalAuthDemoSeeder demo rows. That string
 * was never a valid value of App\Enums\ChipCardType (which only
 * recognises driver_card / trailer_chip_tag / service_test), so the
 * FE chip-card list rendered those rows as un-assignable.
 *
 * The seeders themselves were corrected at source — current code
 * writes `driver_card` — but deployed databases that were seeded
 * before the fix kept the legacy values. This migration patches them
 * in place so the FE assignment surface comes back to life without
 * needing a manual re-seed on production.
 *
 * Idempotent + reversible:
 *   - up()   converts any `driver_chip` row to `driver_card`. Re-runs
 *            are no-ops because no rows match the WHERE clause.
 *   - down() is intentionally empty. We never want to RE-introduce the
 *            invalid value, even on rollback — the original mapping is
 *            lost (driver_chip and driver_card collapse to a single
 *            bucket per the enum contract).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auth_media')) {
            return;
        }
        if (! Schema::hasColumn('auth_media', 'card_type')) {
            return;
        }

        DB::table('auth_media')
            ->where('card_type', 'driver_chip')
            ->update(['card_type' => 'driver_card']);
    }

    public function down(): void
    {
        // No-op: see class doc. The invalid value is not reinstated.
    }
};
