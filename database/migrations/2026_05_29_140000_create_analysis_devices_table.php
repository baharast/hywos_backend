<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1 §15 — the 3 MVP analysis devices live in one table. Type-specific
 * columns are nullable so each device only populates the fields that
 * apply to it (analyser has run_state + calibration; regard has
 * safety_state + channels; sam has routing_state + selected_stream).
 *
 * No write surface in MVP; this is a read-only monitor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_devices', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('device_type', 40)->index();
            $table->string('health_status', 30)->index();

            // Analyser only — null on regard/sam.
            $table->string('run_state', 30)->nullable();
            $table->string('calibration_status', 30)->nullable();
            $table->string('active_method', 100)->nullable();
            $table->string('selected_sample_point', 50)->nullable();

            // Regard only — null on analyser/sam.
            $table->string('safety_state', 30)->nullable();

            // Sam only — null on analyser/regard.
            $table->string('routing_state', 40)->nullable();
            $table->string('selected_stream', 20)->nullable();
            $table->string('mode', 20)->nullable();

            // Shared lifecycle flags + last-known-good telemetry. These
            // are denormalised snapshots from whatever device feed wrote
            // them; the card resource consumes them verbatim.
            $table->boolean('inhibit_active')->default(false);
            $table->string('last_message', 500)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->timestamp('last_value_at')->nullable();
            $table->timestamp('next_calibration_due_at')->nullable();

            $table->char('site_id', 36)->nullable();
            $table->char('plant_area_id', 36)->nullable();
            $table->string('correlation_id', 64)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_devices');
    }
};
