<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * document_print_attempts — immutable per-attempt print history.
 *
 * Per FillTrack Operational Documents UX Frontend Spec-EN-V1.2 §11.3
 * (Print History tab) and §16 (DocumentPrintAttempt). Each row is a single
 * print/reprint attempt. Reprints append new rows; they never overwrite
 * earlier history (§18 "Reprint must not overwrite original generation/
 * print history"). The model enforces immutability after creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_print_attempts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('document_id', 36)->index();

            $table->unsignedInteger('attempt_no');
            // DocumentPrintStatus enum: queued / printing / printed / failed / reprinted
            $table->string('status', 30)->index();

            $table->char('printer_id', 36)->nullable();
            $table->string('printer_name', 100)->nullable();
            $table->string('print_job_id', 100)->nullable();

            $table->timestamp('requested_at');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('requested_by_label', 100)->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();

            // Set when this attempt is a reprint (replaces a previous one).
            $table->boolean('is_reprint')->default(false);
            $table->text('reprint_reason')->nullable();

            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'attempt_no'], 'doc_print_attempts_doc_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_print_attempts');
    }
};
