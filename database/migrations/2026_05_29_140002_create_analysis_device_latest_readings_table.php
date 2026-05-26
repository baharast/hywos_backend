<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Latest per-component analyser readings per V1 §8.1 / §15. One row per
 * (device_id, component) — the table is an upsert target on the read
 * surface, not an event log. Use Active Analyses / Results & Quality
 * Decisions for historical runs.
 *
 * `validity` distinguishes a value that was *measured but invalid* from
 * a *stale* or *missing* one — important for the card's "result trust"
 * pill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_device_latest_readings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('device_id', 36)->index();

            // GasComponent enum value: H2 | O2 | N2 | CH4 | CO | CO2
            $table->string('component', 10);

            $table->decimal('value', 12, 4)->nullable();
            $table->string('unit', 20)->nullable();

            // valid | stale | invalid | missing
            $table->string('validity', 20);

            $table->timestamp('measured_at')->nullable();

            $table->timestamps();

            $table->unique(['device_id', 'component'], 'uq_analysis_reading_per_device_component');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_device_latest_readings');
    }
};
