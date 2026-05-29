<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * logbook_entry_corrections — append-only history of corrections to
 * V1 §7.5 logbook entries.
 *
 * Per V1 §7.5 forbidden actions: "Do not silently overwrite logbook
 * text." This table stores the old + new text snapshot plus the
 * mandatory reason every time a logbook entry is corrected. The
 * corresponding LOGBOOK_ENTRY_CORRECTED audit row carries the same
 * before/after payload so the audit trail and this history table
 * stay aligned.
 *
 * Append-only: no UPDATE, no DELETE. The model layer enforces this
 * with a saving hook (same pattern as document_print_attempts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbook_entry_corrections', function (Blueprint $t) {
            $t->char('id', 36)->primary();
            $t->char('logbook_entry_id', 36)->index();

            $t->timestamp('corrected_at')->useCurrent();
            $t->unsignedBigInteger('corrected_by_user_id')->nullable();
            $t->string('corrected_by_name', 255)->nullable();

            // Snapshot of the previous values.
            $t->string('old_title', 200)->nullable();
            $t->text('old_description')->nullable();
            $t->text('old_action_taken')->nullable();

            // New values applied with the correction.
            $t->string('new_title', 200)->nullable();
            $t->text('new_description')->nullable();
            $t->text('new_action_taken')->nullable();

            // Mandatory per spec.
            $t->text('reason');

            $t->string('correlation_id', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_entry_corrections');
    }
};
