<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * calibration_profiles — configure analyser calibration reference values
 * and lockout behaviour. Spec: V2.1 §5 (Calibration Settings tab).
 *
 * `device_id` is a SOFT FK to analysis_devices (which may not yet exist
 * when this migration runs — Track A may land separately). The seeded
 * value matches Track A's OrthoSmart BMK so the FK resolves once that
 * table is present. `device_bmk` and `device_name` are SNAPSHOT columns
 * captured at create time so the profile remains readable if the device
 * row is later renamed.
 *
 * `calibration_status` is the OPERATIONAL health (valid / due_soon /
 * overdue / failed / not_configured), distinct from the lifecycle
 * `status` column (draft / active / inactive / retired). See V2.1 §6.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_profiles', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // Device identity (soft FK + snapshot)
            $table->char('device_id', 36)->nullable()->index();
            $table->string('device_bmk', 50);                          // e.g. AN-OS-01
            $table->string('device_name', 255)->nullable();

            // Calibration medium + certificate provenance
            $table->string('calibration_medium', 100);                 // e.g. "Cal Gas Mix 6-component"
            $table->string('certificate_ref', 100)->nullable();

            // Versioning
            $table->string('profile_version', 20);                     // v1 / v2

            // Lifecycle vs operational status — KEEP SEPARATE.
            $table->string('status', 20)->default('draft')->index();   // CalibrationProfileStatus
            $table->string('calibration_status', 30)
                ->default('not_configured')->index();                  // valid | due_soon | overdue | failed | not_configured

            // Operational consequence on invalid calibration
            $table->string('lockout_behavior', 40);                    // CalibrationLockoutBehavior

            // Time-anchored fields
            $table->timestamp('medium_expiry_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->timestamp('last_run_at')->nullable();

            $table->text('notes')->nullable();

            // Activate/retire metadata
            $table->timestamp('activated_at')->nullable();
            $table->unsignedBigInteger('activated_by_user_id')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->unsignedBigInteger('retired_by_user_id')->nullable();

            // Standard audit metadata
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_profiles');
    }
};
