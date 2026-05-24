<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit table for chip-card assignment lifecycle.
 *
 * Captures who assigned/unassigned/replaced a chip and when, so the chip-card
 * detail page can show a full assignment history independently of the
 * generic audit_logs table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chip_card_assignments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('auth_medium_id', 36)->index();
            $table->string('action', 20); // assigned | reassigned | unassigned | replaced
            $table->string('entity_type', 20); // driver | trailer
            $table->char('entity_id', 36)->nullable();
            $table->string('entity_label', 255)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('reason')->nullable();
            $table->string('reason_code', 100)->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auth_medium_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chip_card_assignments');
    }
};
