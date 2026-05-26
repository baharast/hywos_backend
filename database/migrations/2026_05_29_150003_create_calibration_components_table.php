<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * calibration_components — one row per (profile, gas component) carrying
 * the exact reference value + acceptance tolerance the calibration run
 * is compared against. Spec: V2.1 §5.3, §5.4, §5.6.
 *
 * Edit semantics (V2.1 §5.6 + §7):
 *   - First-time row → reason optional
 *   - Edit existing reference / tolerance → reason MANDATORY
 *   - last_measured_value / last_deviation / last_result are READ-ONLY
 *     for the user (V2.1 §5.7) — set by the calibration run / device
 *     interface, not by the configuration form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_components', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('profile_id', 36)->index();                   // soft FK

            $table->string('component', 10)->index();                  // GasComponent
            $table->string('unit', 20);

            // User-configured (the editable reference + tolerance)
            $table->decimal('exact_value', 12, 6);
            $table->decimal('tolerance_abs', 12, 6)->nullable();
            $table->decimal('tolerance_percent', 5, 2)->nullable();
            $table->unsignedTinyInteger('precision_decimals')->nullable();
            $table->string('rounding_rule', 20)->nullable();

            // Read-only — set by the calibration run, not by the user
            $table->decimal('last_measured_value', 12, 6)->nullable();
            $table->decimal('last_deviation', 12, 6)->nullable();
            $table->decimal('last_deviation_percent', 8, 3)->nullable();
            $table->string('last_result', 20)->nullable();             // CalibrationRunResult
            $table->timestamp('last_run_at')->nullable();

            // Audit on edit
            $table->text('last_change_reason')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'component'], 'calibration_components_profile_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_components');
    }
};
