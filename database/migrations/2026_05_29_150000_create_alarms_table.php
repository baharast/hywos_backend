<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * alarms — V1 §6 Active Alarms.
 *
 * The live operational queue for unresolved or just-resolved alarms
 * affecting safety, loading, gates, devices, analysis, documents or
 * interfaces. Per V1 §6.10 forbidden actions: no physical safety
 * reset, no E-stop reset, no PLC fault reset, no valve/interlock
 * bypass, no compressor/electrolyzer trip reset, no remote gate open,
 * no force release, no force success/match, no raw PLC write. Software
 * acknowledgement is only a workflow record and never a physical reset.
 *
 * Soft FKs to the affected entity allow linking without coupling to
 * other modules — when the linked entity is missing the alarm still
 * surfaces in the queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarms', function (Blueprint $t) {
            $t->char('id', 36)->primary();

            // Display identity.
            $t->string('alarm_no', 50)->unique();
            $t->string('title', 200);

            // Enum-backed columns (see App\Enums\Alarm*).
            $t->string('severity', 20)->index();         // AlarmSeverity
            $t->string('status', 30)->default('active')->index(); // AlarmStatus
            $t->string('category', 30)->index();         // AlarmCategory
            $t->string('blocking_impact', 40)->index();  // AlarmBlockingImpact
            $t->string('source_type', 40)->nullable()->index(); // AlarmSourceType
            $t->string('source_id', 100)->nullable();
            $t->string('source_label', 200)->nullable();
            $t->string('location', 40)->nullable()->index();    // HardwarePhysicalLocation reuse

            // Owner workflow.
            $t->string('owner_role', 50)->nullable()->index();  // AlarmOwnerRole
            $t->unsignedBigInteger('owner_user_id')->nullable()->index();
            $t->string('owner_user_name', 255)->nullable();

            // Affected entity (soft FK).
            $t->string('linked_entity_type', 100)->nullable()->index();
            $t->string('linked_entity_id', 100)->nullable()->index();
            $t->string('linked_entity_label', 200)->nullable();

            // Detail content.
            $t->text('message')->nullable();
            $t->string('recommended_action', 500)->nullable();
            $t->string('alarm_code', 100)->nullable();
            $t->string('current_value', 100)->nullable();
            $t->string('threshold_value', 100)->nullable();
            $t->string('unit', 30)->nullable();
            $t->json('technical_payload')->nullable();    // Restricted; not on list

            // Lifecycle timestamps.
            $t->timestamp('first_seen_at')->index();
            $t->timestamp('last_seen_at')->nullable();
            $t->unsignedInteger('occurrence_count')->default(1);

            // Acknowledgement.
            $t->timestamp('acknowledged_at')->nullable();
            $t->unsignedBigInteger('acknowledged_by_user_id')->nullable();
            $t->string('acknowledged_by_name', 255)->nullable();

            // In-progress.
            $t->timestamp('in_progress_at')->nullable();
            $t->unsignedBigInteger('in_progress_by_user_id')->nullable();

            // Resolution / close.
            $t->timestamp('resolved_at')->nullable();
            $t->unsignedBigInteger('resolved_by_user_id')->nullable();
            $t->string('resolved_by_name', 255)->nullable();
            $t->text('resolution_reason')->nullable();
            $t->text('corrective_action')->nullable();
            $t->timestamp('closed_at')->nullable();

            $t->string('correlation_id', 64)->nullable()->index();

            $t->timestamps();

            $t->index(['status', 'severity']);
            $t->index(['blocking_impact', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarms');
    }
};
