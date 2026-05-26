<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exam attempt history for V6 §7.6. Append-only — every submission is kept
 * for audit. The `submitted_at` and `created_at` columns are identical at
 * insert time; `submitted_at` is the meaningful business timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_training_exam_results', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            $table->char('driver_id', 36)->index();
            $table->unsignedTinyInteger('score');              // 0..5
            $table->unsignedTinyInteger('total')->default(5);
            $table->boolean('passed')->index();

            // The submitted answers verbatim, so an auditor can replay the
            // grading. Shape: [{questionId, choice}, ...].
            $table->json('answers');

            $table->char('terminal_session_id', 36)->nullable();
            $table->string('correlation_id', 64)->nullable();

            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_training_exam_results');
    }
};
