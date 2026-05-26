<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-driver, per-module training progress for V6 §7.
 *
 * One row per driver+module combination. Append-only — no `updated_at`. The
 * Safety Training service performs an upsert into the unique key so repeated
 * "I confirm" clicks remain idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_training_progress', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            $table->char('driver_id', 36)->index();
            // hydrogen | site-rules | ppe | emergency
            $table->string('module_id', 40)->index();
            $table->timestamp('completed_at')->useCurrent();

            // Which visit completed this module — soft FK, nullable so we
            // can still record progress from contexts outside an active
            // terminal session (e.g. office-issued credit).
            $table->char('terminal_session_id', 36)->nullable();

            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['driver_id', 'module_id'], 'uq_training_progress_driver_module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_training_progress');
    }
};
