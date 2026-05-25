<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_visits', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('visit_no', 50)->unique();

            // Driver — soft FK + denormalized snapshot for list page reads
            $table->char('driver_id', 36)->nullable()->index();
            $table->string('driver_name', 255)->nullable();
            $table->string('driver_code', 50)->nullable();

            // Tractor — soft FK. tractor_plate is the SNAPSHOT and the authoritative
            // source for the plate during the visit (per APV V1.6 §11/§15).
            $table->char('tractor_vehicle_id', 36)->nullable()->index();
            $table->string('tractor_plate', 50)->nullable()->index();

            // Trailer — soft FK + denormalized snapshot
            $table->char('trailer_id', 36)->nullable()->index();
            $table->string('trailer_label', 100)->nullable();
            $table->string('trailer_plate', 50)->nullable();
            $table->string('trailer_chip', 64)->nullable(); // masked / display value only

            // Order context — soft FK to loading_orders + denormalized snapshot
            $table->char('order_id', 36)->nullable()->index();
            $table->string('order_no', 50)->nullable();
            $table->string('sap_reference', 100)->nullable();

            // Workflow concepts (kept as separate fields per V1.6 §2 — never merge)
            $table->string('task_flow', 40)->nullable()->index();        // DriverTask enum
            $table->string('current_step', 40)->nullable()->index();     // PlantVisitStep enum
            $table->string('visit_status', 30)->default('normal')->index(); // PlantVisitStatus enum
            $table->string('current_location', 40)->nullable()->index(); // PlantVisitLocation enum

            // Next action shown to the operator (V1.6 §2 + §10)
            $table->string('next_action_label', 255)->nullable();
            $table->string('next_action_target', 255)->nullable();

            // Visible-reason fields (V1.6 §7 + §10)
            $table->string('waiting_reason', 255)->nullable();
            $table->text('blocker_reason')->nullable();
            $table->string('owner_role', 50)->nullable();

            // Cross-module link
            $table->char('clarification_case_id', 36)->nullable()->index();

            // Timing
            $table->timestamp('entry_time'); // mandatory; defaulted by model on create
            $table->timestamp('exit_time')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();
            $table->text('closure_reason')->nullable();

            // Snapshot json columns — captured at visit start; immutable thereafter
            // (V1.6 §15 "Snapshot protection" rule)
            $table->json('driver_snapshot')->nullable();
            $table->json('tractor_snapshot')->nullable();
            $table->json('trailer_snapshot')->nullable();
            $table->json('order_snapshot')->nullable();

            $table->string('correlation_id', 64)->nullable()->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            // Index combos useful for the list page (V1.6 §9.2 default sort + filters)
            $table->index(['visit_status', 'entry_time']);
            $table->index(['task_flow', 'current_step']);
            $table->index(['order_id', 'visit_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_visits');
    }
};
