<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds chip-card-only columns to `auth_media`.
 *
 * Boundary rule: this migration only adds columns owned by the Chip Cards module.
 * Columns owned by the TANs module (tan_reference, tan_masked, consumed_at, etc.)
 * are intentionally NOT touched here.
 *
 * Each column is wrapped in `Schema::hasColumn` so this migration is safe to
 * re-run alongside the parallel TAN migration even if their orderings shuffle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_media', function (Blueprint $table) {
            if (! Schema::hasColumn('auth_media', 'card_code')) {
                $table->string('card_code', 50)->nullable()->after('display_identifier');
            }
            if (! Schema::hasColumn('auth_media', 'serial_number')) {
                $table->string('serial_number', 100)->nullable()->after('card_code');
            }
            if (! Schema::hasColumn('auth_media', 'masked_uid')) {
                $table->string('masked_uid', 64)->nullable()->after('serial_number');
            }
            if (! Schema::hasColumn('auth_media', 'card_type')) {
                $table->string('card_type', 30)->nullable()->after('masked_uid');
            }
            if (! Schema::hasColumn('auth_media', 'assignment_state')) {
                $table->string('assignment_state', 20)->default('unassigned')->after('card_type');
            }
            if (! Schema::hasColumn('auth_media', 'replacement_of_card_id')) {
                $table->char('replacement_of_card_id', 36)->nullable()->after('assignment_state');
            }
            if (! Schema::hasColumn('auth_media', 'replaced_by_card_id')) {
                $table->char('replaced_by_card_id', 36)->nullable()->after('replacement_of_card_id');
            }
            if (! Schema::hasColumn('auth_media', 'replacement_reason')) {
                $table->text('replacement_reason')->nullable()->after('replaced_by_card_id');
            }
            if (! Schema::hasColumn('auth_media', 'lost_at')) {
                $table->timestamp('lost_at')->nullable()->after('replacement_reason');
            }
            if (! Schema::hasColumn('auth_media', 'defective_at')) {
                $table->timestamp('defective_at')->nullable()->after('lost_at');
            }
            if (! Schema::hasColumn('auth_media', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('defective_at');
            }
            if (! Schema::hasColumn('auth_media', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('archived_at');
            }
            if (! Schema::hasColumn('auth_media', 'last_used_context')) {
                $table->string('last_used_context', 255)->nullable()->after('last_used_at');
            }
            if (! Schema::hasColumn('auth_media', 'last_used_source')) {
                $table->string('last_used_source', 50)->nullable()->after('last_used_context');
            }
            if (! Schema::hasColumn('auth_media', 'last_usage_result')) {
                $table->string('last_usage_result', 30)->nullable()->after('last_used_source');
            }
        });

        // Indexes added in a second pass so column existence is settled.
        // Wrapped in try/catch so re-runs do not fail on MySQL "duplicate index" errors.
        $indexes = [
            ['card_code', 'auth_media_card_code_index'],
            ['card_type', 'auth_media_card_type_index'],
            ['assignment_state', 'auth_media_assignment_state_index'],
            ['replacement_of_card_id', 'auth_media_replacement_of_card_id_index'],
            ['replaced_by_card_id', 'auth_media_replaced_by_card_id_index'],
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
                'card_code', 'serial_number', 'masked_uid', 'card_type', 'assignment_state',
                'replacement_of_card_id', 'replaced_by_card_id', 'replacement_reason',
                'lost_at', 'defective_at', 'archived_at',
                'last_used_at', 'last_used_context', 'last_used_source', 'last_usage_result',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('auth_media', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

};
