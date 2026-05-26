<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * analysis_element_results — one row per (attempt, gas component) (V1.4
 * §12 Six-element comparison table).
 *
 * 6 rows per attempt: H2, O2, N2, CH4, CO, CO2. The workbench renders
 * the full table; the main analyses list only carries a compact
 * `element_summary` string.
 *
 * `status` separates NOK (failed limit, valid value) from INVALID
 * (untrusted value) — V1.4 §13. The FE must visually distinguish them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_element_results', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('attempt_id', 36)->index();
            $table->char('analysis_id', 36)->index();                  // denormalised for fast cross-attempt queries

            $table->string('element', 10);                             // GasComponent
            $table->decimal('measured_value', 14, 6)->nullable();
            $table->string('unit', 20)->nullable();

            // Limit context (snapshot from product_gas_limits)
            $table->decimal('lower_limit', 14, 6)->nullable();
            $table->decimal('upper_limit', 14, 6)->nullable();
            $table->string('limit_label', 50)->nullable();             // e.g. "<= 1 ppm"
            $table->string('difference_label', 50)->nullable();        // e.g. "+3.2 ppm above max"

            $table->string('status', 20);                              // AnalysisElementStatus
            $table->string('validity_reason', 255)->nullable();        // populated when invalid/missing/untrusted
            $table->timestamp('measured_at')->nullable();

            $table->timestamps();

            $table->unique(['attempt_id', 'element'], 'analysis_element_results_attempt_element_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_element_results');
    }
};
