<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * clarification_cases — cross-cutting "Open Clarification" foundation.
 *
 * Schema follows FillTrack Clarification Cases UX Frontend Spec-EN-V1.3:
 *   - §4.1 Case Status      → `status` column (5 values, no `cancelled`)
 *   - §4.2 Blocking Impact  → `blocking_impact` column (7 values)
 *   - §5   Case Sources     → `source` column (8 values, required)
 *   - §8   Primary Action   → `primary_action` column (9 values, nullable)
 *   - §6   Action Needed    → `action_needed` short label column
 *
 * IMPORTANT: entity_id and related_*_id are SOFT FKs. They may point to rows
 * in tables that do not exist yet (sap_sync_records, plant_visits). No
 * foreign-key constraints are defined here on purpose.
 *
 * The `cancelled_at` / `cancelled_by_user_id` / `cancellation_reason` columns
 * are intentionally retained even though V1.3 has no cancellation status —
 * see [[clarification-cases-status-enum-mismatch]] for the rationale. They
 * are never written by the controller/seeder; a future "close without
 * resolution" flow may reuse them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clarification_cases', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('case_no', 50)->unique();

            // V1.3 §4.1 status (5 values: open / in_progress / waiting_for_owner / resolved / closed)
            $table->string('status', 30)->default('open')->index();
            $table->string('severity', 20)->default('normal')->index();
            $table->string('category', 40)->nullable()->index();

            // V1.3 §5 source — required at create time. 40 chars covers the
            // longest enum value ('analysis_quality') with generous margin.
            $table->string('source', 40)->index();

            // V1.3 §4.2 blocking impact — defaults to `none` so legacy
            // create paths that omit it land in the calm state.
            $table->string('blocking_impact', 30)->default('none')->index();

            // V1.3 §8 primary action — nullable because the value is derived
            // by the controller at read time on cases the user can act on;
            // creation does not have to commit to a specific action upfront.
            $table->string('primary_action', 50)->nullable();

            // V1.3 §6.5 + table column "Action Needed" — short, plain-language
            // next step shown in the list. Backend may compute this on the
            // fly; the column lets controllers persist a fixed wording set
            // at create time.
            $table->string('action_needed', 255)->nullable();

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

            // Retained for forward-compat — see file header. Never written
            // by current code paths.
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Legacy boolean kept in sync with blocking_impact (any non-`none`
            // value sets is_blocking=true). Frontend should prefer
            // `blocking_impact` for the badge and `is_blocking` only for the
            // boolean gating shortcut.
            $table->boolean('is_blocking')->default(true)->index();

            $table->string('correlation_id', 64)->nullable()->index();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            // Composite indexes
            $table->index(['status', 'severity'], 'clarification_cases_status_severity_index');
            $table->index(['entity_type', 'entity_id'], 'clarification_cases_entity_lookup_index');
            $table->index(['status', 'blocking_impact'], 'clarification_cases_status_impact_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clarification_cases');
    }
};
