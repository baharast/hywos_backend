<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * clarification_cases — the cross-cutting "Open Clarification" foundation.
 *
 * IMPORTANT: entity_id and related_*_id are SOFT FKs. They may point to rows
 * in tables that do not exist yet (sap_sync_records, plant_visits). No
 * foreign-key constraints are defined here on purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clarification_cases', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('case_no', 50)->unique();

            $table->string('status', 30)->default('open')->index();
            $table->string('severity', 20)->default('normal')->index();
            $table->string('category', 40)->nullable()->index();

            $table->string('title', 255)->nullable();
            $table->text('description');
            $table->string('reason_code', 100)->nullable();

            // Entity binding — soft FK, no constraint.
            $table->string('entity_type', 50)->nullable()->index();
            $table->char('entity_id', 36)->nullable()->index();
            $table->string('entity_label', 255)->nullable();

            // Related context — also soft FKs.
            $table->char('related_plant_visit_id', 36)->nullable()->index();
            $table->char('related_order_id', 36)->nullable()->index();
            $table->char('related_driver_id', 36)->nullable();
            $table->char('related_trailer_id', 36)->nullable();

            // Workflow
            $table->string('owner_role', 50)->nullable();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();

            $table->timestamp('opened_at');
            $table->unsignedBigInteger('opened_by_user_id')->nullable();

            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by_user_id')->nullable();

            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->boolean('is_blocking')->default(true)->index();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            // Composite indexes
            $table->index(['status', 'severity'], 'clarification_cases_status_severity_index');
            $table->index(['entity_type', 'entity_id'], 'clarification_cases_entity_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clarification_cases');
    }
};
