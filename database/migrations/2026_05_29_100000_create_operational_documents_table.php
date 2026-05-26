<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * operational_documents — certificates, delivery notes and QM documents.
 *
 * Per FillTrack Operational Documents UX Frontend Spec-EN-V1.2:
 *   - §12 Lifecycle Statuses → `lifecycle_status` (10 values)
 *   - §15 Document Fields    → list/detail columns
 *   - §16 TypeScript shapes  → DocumentListItem + DocumentDetail
 *   - §18 Immutable versions → `snapshot_payload` is frozen at generation
 *
 * Soft FKs: order_id, plant_visit_id, driver_id, trailer_id, tractor_id,
 * customer_id, carrier_id, analysis_id, printer_id are all char(36) without
 * FK constraints — they may point to rows in tables that do not yet exist
 * in this MVP slice (analysis, printers). The `_name` / `_label` siblings
 * preserve human-readable context even when the upstream row is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_documents', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('document_no', 50)->unique();

            // §12 / §16 — type + lifecycle + print status
            $table->string('document_type', 30)->index();
            $table->string('lifecycle_status', 30)->default('pending')->index();
            $table->string('print_status', 30)->default('not_requested')->index();

            // §15 Exit Blocking — boolean + reason text + structured blocker_type
            // (one of: quality, prerequisite, print, handover, clarification).
            $table->boolean('is_exit_blocking')->default(false)->index();
            $table->string('blocking_reason', 500)->nullable();
            $table->string('blocker_type', 40)->nullable()->index();

            // §14 Snapshot / version metadata. `version` is the human-readable
            // snapshot identifier shown in the Document Detail header.
            $table->string('version', 50)->nullable();
            $table->string('template_name', 100)->nullable();
            $table->string('template_version', 50)->nullable();
            $table->string('file_url', 500)->nullable();

            // Soft FKs — Order context
            $table->char('order_id', 36)->nullable()->index();
            $table->string('order_no', 50)->nullable();
            $table->string('sap_order_no', 50)->nullable();

            // Soft FKs — Plant Visit
            $table->char('plant_visit_id', 36)->nullable()->index();
            $table->string('visit_no', 50)->nullable();

            // Soft FKs — Driver / Trailer / Tractor
            $table->char('driver_id', 36)->nullable()->index();
            $table->string('driver_name', 255)->nullable();
            $table->char('trailer_id', 36)->nullable();
            $table->string('trailer_label', 100)->nullable();
            $table->char('tractor_id', 36)->nullable();
            $table->string('tractor_label', 100)->nullable();

            // Soft FKs — Commercial
            $table->char('customer_id', 36)->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->char('carrier_id', 36)->nullable();
            $table->string('carrier_name', 255)->nullable();

            // Soft FKs — Analysis / Quality (no table yet)
            $table->char('analysis_id', 36)->nullable();
            $table->string('analysis_status', 40)->nullable();
            $table->string('product_quality', 100)->nullable();
            $table->decimal('actual_loaded_quantity', 12, 3)->nullable();
            $table->string('unit', 20)->nullable();

            // Soft FKs — Printer / Print Job (no printer table yet)
            $table->char('printer_id', 36)->nullable();
            $table->string('printer_name', 100)->nullable();
            $table->string('print_job_id', 100)->nullable();

            // Print summary — denormalised from latest attempt for fast list
            // queries. document_print_attempts is the source of truth.
            $table->unsignedInteger('reprint_count')->default(0);
            $table->text('last_failure_reason')->nullable();

            // Lifecycle timestamps
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('blocked_at')->nullable();

            $table->timestamp('handed_over_at')->nullable();
            $table->unsignedBigInteger('handed_over_by_user_id')->nullable();
            $table->text('handover_note')->nullable();

            $table->timestamp('invalidated_at')->nullable();
            $table->unsignedBigInteger('invalidated_by_user_id')->nullable();
            $table->text('invalidation_reason')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Generation provenance
            $table->unsignedBigInteger('generated_by_user_id')->nullable();
            $table->string('generated_by_source', 50)->nullable(); // 'system' | 'user' | 'sap'

            // §11.1 Snapshot Fields — frozen at generation time. JSON because
            // the snapshot schema differs per document type and may grow.
            $table->json('snapshot_payload')->nullable();

            $table->string('correlation_id', 64)->nullable()->index();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            // Composite indexes for the default sort (blocking/failed first
            // then latest updated, §9.5 Reset behavior) and detail lookups.
            $table->index(['is_exit_blocking', 'updated_at'], 'op_docs_blocking_updated_idx');
            $table->index(['lifecycle_status', 'updated_at'], 'op_docs_status_updated_idx');
            $table->index(['document_type', 'lifecycle_status'], 'op_docs_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_documents');
    }
};
