<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds TAN-only columns to `auth_media`.
 *
 * Boundary rule: this migration only adds columns owned by the TANs module.
 * Columns owned by the ChipCards module (card_code, card_type, masked_uid,
 * assignment_state, replacement_*, lost_at, defective_at, archived_at,
 * last_used_*) are intentionally NOT touched here.
 *
 * Each column is wrapped in `Schema::hasColumn` so this migration is safe to
 * re-run alongside the ChipCards migration even if their orderings shuffle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_media', function (Blueprint $table) {
            if (! Schema::hasColumn('auth_media', 'tan_reference')) {
                $table->string('tan_reference', 50)->nullable()->after('order_id');
            }
            if (! Schema::hasColumn('auth_media', 'tan_masked')) {
                $table->string('tan_masked', 64)->nullable()->after('tan_reference');
            }
            if (! Schema::hasColumn('auth_media', 'valid_from')) {
                $table->timestamp('valid_from')->nullable()->after('tan_masked');
            }
            if (! Schema::hasColumn('auth_media', 'consumed_at')) {
                $table->timestamp('consumed_at')->nullable()->after('valid_from');
            }
            if (! Schema::hasColumn('auth_media', 'consumption_count')) {
                $table->unsignedInteger('consumption_count')->default(0)->after('consumed_at');
            }
            if (! Schema::hasColumn('auth_media', 'usage_state')) {
                $table->string('usage_state', 30)->default('unused')->after('consumption_count');
            }
            if (! Schema::hasColumn('auth_media', 'related_plant_visit_id')) {
                $table->char('related_plant_visit_id', 36)->nullable()->after('usage_state');
            }
            if (! Schema::hasColumn('auth_media', 'related_terminal_session_id')) {
                $table->char('related_terminal_session_id', 36)->nullable()->after('related_plant_visit_id');
            }
            if (! Schema::hasColumn('auth_media', 'reason')) {
                $table->text('reason')->nullable()->after('related_terminal_session_id');
            }
        });

        // Indexes added in a second pass so column existence is settled.
        // Wrapped in try/catch so re-runs do not fail on MySQL "duplicate index" errors.
        $indexes = [
            ['tan_reference', 'auth_media_tan_reference_index'],
            ['usage_state', 'auth_media_usage_state_index'],
            ['related_plant_visit_id', 'auth_media_related_plant_visit_id_index'],
            ['related_terminal_session_id', 'auth_media_related_terminal_session_id_index'],
        ];
        foreach ($indexes as [$col, $name]) {
            try {
                Schema::table('auth_media', function (Blueprint $table) use ($col, $name) {
                    $table->index($col, $name);
                });
            } catch (\Throwable $ignore) {
                // index already exists — safe during dev re-runs
            }
        }
    }

    public function down(): void
    {
        Schema::table('auth_media', function (Blueprint $table) {
            $cols = [
                'tan_reference', 'tan_masked', 'valid_from',
                'consumed_at', 'consumption_count', 'usage_state',
                'related_plant_visit_id', 'related_terminal_session_id', 'reason',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('auth_media', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
