<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trailer Parking — MVP two-slot board.
 *
 * Per FillTrack Trailer Parking UX Spec V2.1 §0.2 the MVP site has exactly
 * two trailer parking slots (PARKING-1, PARKING-2). The earlier generic
 * "site map" shape (capacity / occupied_count / current_vehicle_id) has been
 * dropped in favour of a slot-board schema that carries denormalized trailer
 * / order / visit snapshots so the board can be rendered without joins.
 *
 * Dev phase: this migration is rewritten in place (no data preservation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parkings', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // Slot identity — fixed at PARKING-1 / PARKING-2 in MVP
            $table->string('code', 50)->unique();
            $table->string('name', 100);

            // Plant config link (kept for activation-lock consistency with bay_lines / gates)
            $table->char('plant_configuration_id', 36)->nullable()->index();
            $table->char('site_id', 36)->nullable()->index();
            $table->char('plant_area_id', 36)->nullable()->index();

            // Slot operational state (V2.1 §6.2)
            // free | reserved | occupied | blocked | out_of_service
            $table->string('slot_status', 20)->default('free')->index();

            // Currently parked / reserved trailer (snapshot — denormalized for fast reads)
            $table->char('current_trailer_id', 36)->nullable()->index();
            $table->string('current_trailer_label', 100)->nullable();
            $table->string('current_trailer_plate', 50)->nullable();
            $table->string('current_trailer_chip', 64)->nullable();
            // empty | loaded | unknown | null
            $table->string('current_load_state', 20)->nullable();

            // Linked order / visit / driver (soft FKs; resolved post-TSK-002)
            $table->char('linked_order_id', 36)->nullable()->index();
            $table->string('linked_order_no', 50)->nullable();
            $table->char('active_visit_id', 36)->nullable()->index();
            $table->string('active_visit_no', 50)->nullable();
            $table->char('driver_id', 36)->nullable();
            $table->string('driver_name', 255)->nullable();

            $table->string('tractor_plate', 50)->nullable();

            // Timing
            $table->timestamp('parked_since')->nullable();
            $table->timestamp('reserved_for')->nullable();

            // Blocker / clarification
            $table->text('blocker_reason')->nullable();
            $table->char('clarification_case_id', 36)->nullable()->index();

            // Next-action hint for the UI (V2.1 §16.1 ParkingNextAction enum)
            // none | wait_for_arrival | wait_for_pickup | open_visit | open_order
            //      | open_documents | open_clarification
            $table->string('next_action', 30)->default('none');

            // Optional document readiness summary for loaded trailers (V2.1 §6.4)
            $table->json('document_summary')->nullable();

            // Status / audit metadata
            $table->boolean('is_active')->default(true)->index();
            $table->char('created_by_user_id', 36)->nullable();
            $table->char('updated_by_user_id', 36)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parkings');
    }
};
