<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * analysis_attempts — per-attempt history for an analysis job (V1.4 §11).
 *
 * Each row is one attempt run. The repeat / retest flows create new
 * rows; earlier rows are never overwritten so the timeline in the
 * workbench remains intact. Element-level results live in
 * `analysis_element_results` keyed by `attempt_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_attempts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('analysis_id', 36)->index();
            $table->unsignedTinyInteger('attempt_no');

            // Per-attempt status snapshot
            $table->string('status', 30);                              // ActiveAnalysisStatus
            $table->string('latest_message', 500)->nullable();

            // Trigger source for this specific attempt
            $table->string('triggered_by', 30)->nullable();            // system | user_repeat | user_retest

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // Whether this attempt is a user-requested repeat / retest
            $table->boolean('is_repeat')->default(false);
            $table->text('request_reason')->nullable();                // populated when is_repeat=true

            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['analysis_id', 'attempt_no'], 'analysis_attempts_analysis_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_attempts');
    }
};
