<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * analyses — the Active Analysis job header (V1.4).
 *
 * One row per analysis JOB. Each job has many `analysis_attempts` (the
 * historical record of attempt runs) and the latest attempt's per-
 * element results live in `analysis_element_results` keyed on that
 * attempt id.
 *
 * Soft FKs:
 *   - order_id            → loading_orders.id  (snapshot order_no kept here too)
 *   - plant_visit_id      → plant_visits.id
 *   - loading_operation_id → loading_operations.id
 *   - driver_id / trailer_id / tractor_id → master data
 *   - bay_line_id         → baylines.id
 *   - device_id           → analysis_devices.id (Track A)
 *   - product_spec_id     → product_specifications.id (Track B)
 *   - related_result_id   → results_quality_decisions.id (future)
 *
 * `required_action` and `allowed_actions` are computed by
 * ActiveAnalysisService when the row is read; they are NOT persisted as
 * truth. They are persisted (snapshot) for the demo so the FE can render
 * without recomputing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('display_no', 50)->unique();

            $table->string('analysis_type', 30)->index();              // ActiveAnalysisType
            $table->string('sampling_trigger', 30)->index();           // SamplingTrigger
            $table->string('status', 30)->default('queued')->index();  // ActiveAnalysisStatus

            // Operational context (snapshot + soft FK)
            $table->char('order_id', 36)->nullable()->index();
            $table->string('order_no', 50)->nullable();
            $table->string('sap_order_no', 50)->nullable();
            $table->char('plant_visit_id', 36)->nullable()->index();
            $table->string('visit_no', 50)->nullable();
            $table->char('loading_operation_id', 36)->nullable()->index();

            $table->char('driver_id', 36)->nullable();
            $table->string('driver_name', 255)->nullable();
            $table->char('trailer_id', 36)->nullable();
            $table->string('trailer_label', 100)->nullable();
            $table->char('tractor_id', 36)->nullable();
            $table->string('tractor_label', 100)->nullable();

            $table->char('bay_line_id', 36)->nullable();
            $table->string('station_name', 100)->nullable();

            $table->char('device_id', 36)->nullable()->index();
            $table->string('device_bmk', 50)->nullable();
            $table->string('device_name', 255)->nullable();

            $table->char('product_spec_id', 36)->nullable()->index();
            $table->string('product_code', 50)->nullable();
            $table->string('spec_version', 20)->nullable();

            // Attempt tracking — denormalised for fast list queries.
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);

            // Action snapshot (V1.4 §16 — computed by service, persisted
            // for the demo so the FE renders without a recompute).
            $table->string('required_action', 50)->nullable();         // AnalysisUserAction
            $table->string('required_action_reason', 500)->nullable();
            $table->json('allowed_actions')->nullable();               // array of AnalysisUserAction values

            // Latest message + element summary string (e.g. "6/6 valid"
            // or "O2 high, CO2 invalid"). Cached for the main table.
            $table->string('latest_message', 500)->nullable();
            $table->string('element_summary', 255)->nullable();

            // Outcome (set when the analysis closes into Results & QD)
            $table->char('related_result_id', 36)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();

            // Hold metadata
            $table->timestamp('held_at')->nullable();
            $table->unsignedBigInteger('held_by_user_id')->nullable();
            $table->text('hold_reason')->nullable();

            // Cancellation metadata
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->string('correlation_id', 64)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            // Composite indexes for the queue's default sort:
            // action-required first, then latest updated.
            $table->index(['status', 'updated_at'], 'analyses_status_updated_idx');
            $table->index(['analysis_type', 'status'], 'analyses_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
