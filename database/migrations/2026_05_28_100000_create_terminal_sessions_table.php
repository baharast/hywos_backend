<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * terminal_sessions — Gate & Terminal Monitor V2.3.
 *
 * Backs the touchpoint-first read-only monitor at
 * Operations → Gate & Terminal Monitor. Exactly 3 touchpoints exist
 * (entry_gate, driver_terminal, exit_gate) — no Bay Line rows live here.
 *
 * All entity references are SOFT FKs (no constraints) because:
 *   - device_id points at a System & Devices row that does not exist yet
 *   - plant_visit_id / order_id / trailer_id / clarification_case_id may be
 *     populated before/after the parent rows exist in other tables
 *
 * `needs_operator` is BACKEND-SET ONLY (V2.3 §11) — never inferred. The
 * controller never writes; the seeder and a future write controller are the
 * only sources of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_sessions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('session_no', 50)->unique();

            $table->string('touchpoint', 30)->index();           // enum
            $table->string('touchpoint_label', 100)->nullable();

            $table->char('device_id', 36)->nullable();           // soft FK
            $table->string('device_label', 100)->nullable();
            $table->string('device_health', 20)->nullable();     // online|warning|fault|offline|service_mode

            $table->char('driver_id', 36)->nullable()->index();  // soft FK
            $table->string('driver_name', 255)->nullable();
            $table->string('driver_code', 50)->nullable();

            $table->char('plant_visit_id', 36)->nullable()->index(); // soft FK
            $table->string('visit_no', 50)->nullable();

            $table->char('order_id', 36)->nullable()->index();
            $table->string('order_no', 50)->nullable();

            $table->char('trailer_id', 36)->nullable();
            $table->string('trailer_label', 100)->nullable();

            $table->string('current_screen', 40)->nullable()->index(); // enum
            $table->string('session_state', 30)->default('idle')->index(); // enum (V2.3 §9)

            $table->string('issue_reason', 500)->nullable();
            $table->string('action_needed', 255)->nullable();

            // V2.3 §11 — set by backend only, never inferred by the frontend.
            $table->boolean('needs_operator')->default(false)->index();
            $table->boolean('support_requested')->default(false);

            $table->char('clarification_case_id', 36)->nullable()->index(); // soft FK

            $table->timestamp('last_activity_at')->nullable()->index();

            $table->string('correlation_id', 64)->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            // Composite indexes for the touchpoint-board and "latest activity"
            // queries the controller makes on every read.
            $table->index(['touchpoint', 'session_state'], 'terminal_sessions_touchpoint_state_index');
            $table->index(['touchpoint', 'last_activity_at'], 'terminal_sessions_touchpoint_activity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_sessions');
    }
};
