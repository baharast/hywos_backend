<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_gas_limits — one row per (spec, gas component) carrying the
 * acceptance limits used by Active Analyses to decide pass/fail.
 *
 * Spec: V2.1 §4.2 (gas limit row fields) + §9 (validation rules: at
 * least one of lower/upper must be set; appliesToAnalysisTypes must be
 * a controlled multi-select).
 *
 * One row per (spec_id, component) — V2.1 explicitly forbids editing
 * multiple component rows at once. Adding the same component twice for
 * the same spec is rejected by the unique key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_gas_limits', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('spec_id', 36)->index();                      // soft FK to product_specifications

            // Component (enum GasComponent: H2 | O2 | N2 | CH4 | CO | CO2)
            $table->string('component', 10)->index();
            $table->string('unit', 20);                                // %, ppm, ppb, mg/m3

            // Limits — V2.1 §9: at least one of lower/upper is required.
            // H2 typically uses lower_limit; impurities use upper_limit.
            $table->decimal('lower_limit', 12, 6)->nullable();
            $table->decimal('upper_limit', 12, 6)->nullable();
            $table->decimal('warning_limit', 12, 6)->nullable();
            $table->decimal('critical_limit', 12, 6)->nullable();

            // Display / rounding
            $table->unsignedTinyInteger('precision_decimals')->nullable();
            $table->string('rounding_rule', 20)->nullable();           // round | truncate | banker

            // V2.1 §4.2 — applies_to_analysis_types is a controlled
            // multi-select (must be JSON array of AnalysisTypeApplicable
            // values; never free text).
            $table->json('applies_to_analysis_types');

            $table->boolean('required_for_release')->default(true);
            $table->string('certificate_mapping', 30);                 // CertificateMapping enum
            $table->unsignedSmallInteger('display_order')->default(0);

            // Audit on edit
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->text('last_change_reason')->nullable();
            $table->timestamps();

            // One row per (spec, component) — V2.1 §9.
            $table->unique(['spec_id', 'component'], 'product_gas_limits_spec_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_gas_limits');
    }
};
