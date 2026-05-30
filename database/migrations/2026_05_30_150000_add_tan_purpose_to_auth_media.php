<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `tan_purpose` to `auth_media` so we can distinguish between TANs
 * issued for different operational purposes against the same order /
 * driver:
 *   - gate_entry  → identity credential at the gate / driver terminal
 *   - filling     → bayline filling-station credential issued AFTER
 *                   the driver confirms an order at the terminal
 *   - general     → master-data TANs not tied to a specific flow
 *
 * Defaults to `gate_entry` so existing rows (order-issued and
 * master-data) keep their behavior. Idempotent guards mirror the
 * sibling `add_tan_fields` migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_media', function (Blueprint $table) {
            if (! Schema::hasColumn('auth_media', 'tan_purpose')) {
                $table->string('tan_purpose', 30)
                    ->default('gate_entry')
                    ->after('usage_state');
            }
        });

        try {
            Schema::table('auth_media', function (Blueprint $table) {
                $table->index('tan_purpose', 'auth_media_tan_purpose_index');
            });
        } catch (\Throwable $ignore) {
            // index already exists — safe during dev re-runs
        }
    }

    public function down(): void
    {
        Schema::table('auth_media', function (Blueprint $table) {
            try { $table->dropIndex('auth_media_tan_purpose_index'); } catch (\Throwable) {}
            if (Schema::hasColumn('auth_media', 'tan_purpose')) {
                $table->dropColumn('tan_purpose');
            }
        });
    }
};
