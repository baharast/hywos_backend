<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a soft assignment of a bay line to a loading order so the order
 * list / detail can render the planned bay alongside the driver +
 * trailer assignments.
 *
 * Columns are nullable + soft-FK (no constraint) so the order can
 * outlive a bay-line config change without cascading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loading_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('loading_orders', 'assigned_bay_line_id')) {
                $table->char('assigned_bay_line_id', 36)
                    ->nullable()
                    ->after('assigned_trailer_plate');
            }
            if (! Schema::hasColumn('loading_orders', 'assigned_bay_line_code')) {
                $table->string('assigned_bay_line_code', 50)
                    ->nullable()
                    ->after('assigned_bay_line_id');
            }
            if (! Schema::hasColumn('loading_orders', 'assigned_bay_line_name')) {
                $table->string('assigned_bay_line_name', 100)
                    ->nullable()
                    ->after('assigned_bay_line_code');
            }
        });

        try {
            Schema::table('loading_orders', function (Blueprint $table) {
                $table->index('assigned_bay_line_id', 'loading_orders_bay_line_idx');
            });
        } catch (\Throwable $ignore) {
            // index already exists — safe during dev re-runs
        }
    }

    public function down(): void
    {
        Schema::table('loading_orders', function (Blueprint $table) {
            try { $table->dropIndex('loading_orders_bay_line_idx'); } catch (\Throwable) {}
            $cols = ['assigned_bay_line_id', 'assigned_bay_line_code', 'assigned_bay_line_name'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('loading_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
