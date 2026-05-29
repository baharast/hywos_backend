<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * logbook_entries — V1 §7.5 Safety & Operations Logbook.
 *
 * Human-created shift / safety / operations / handover notes plus
 * optional follow-up workflow. Per V1 §7.5 forbidden actions: no
 * silent overwrite of logbook text — corrections land on the sibling
 * logbook_entry_corrections table (2026_05_29_140001) so the change
 * history is preserved.
 *
 * Soft FKs to alarm / event / clarification / entity allow linking
 * without coupling to other modules. The future alarms table will
 * fill in linked_alarm_id once it lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbook_entries', function (Blueprint $t) {
            $t->char('id', 36)->primary();

            $t->string('shift_label', 50)->nullable()->index();

            // Enum-backed columns.
            $t->string('category', 30)->index();   // LogbookCategory
            $t->string('severity', 20)->index();   // LogbookSeverity (reuses Critical/High/Medium/Low/Info)
            $t->string('area', 40)->nullable()->index(); // LogbookArea

            // Content.
            $t->string('title', 200);
            $t->text('description');
            $t->text('action_taken')->nullable();

            // Soft-FK link to a related operational object.
            $t->string('related_entity_type', 100)->nullable()->index();
            $t->string('related_entity_id', 100)->nullable()->index();

            // Optional explicit links to other Alarms & Events records.
            $t->char('linked_alarm_id', 36)->nullable();
            $t->unsignedBigInteger('linked_event_log_id')->nullable();
            $t->char('linked_clarification_case_id', 36)->nullable();

            // Follow-up workflow.
            $t->boolean('follow_up_required')->default(false)->index();
            $t->unsignedBigInteger('follow_up_owner_user_id')->nullable();
            $t->string('follow_up_owner_role', 50)->nullable();
            $t->timestamp('follow_up_due_at')->nullable()->index();
            $t->string('follow_up_status', 20)->default('open')->index(); // LogbookFollowUpStatus
            $t->timestamp('follow_up_completed_at')->nullable();
            $t->unsignedBigInteger('follow_up_completed_by_user_id')->nullable();
            $t->text('follow_up_completion_note')->nullable();

            // V1 §7.5 "handover flag" filter — surface in next shift handover.
            $t->boolean('handover_flag')->default(false)->index();

            // Author.
            $t->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $t->string('created_by_name', 255)->nullable();

            $t->string('correlation_id', 64)->nullable();

            $t->timestamps();

            $t->index(['category', 'follow_up_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_entries');
    }
};
