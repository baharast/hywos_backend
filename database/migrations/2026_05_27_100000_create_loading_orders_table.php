<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loading_orders', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // Identity
            $table->string('order_no', 50)->unique();
            $table->string('source', 20)->default('manual')->index();   // sap | manual
            $table->string('sap_reference', 100)->nullable()->unique();
            $table->string('external_reference', 100)->nullable();

            // Commercial context
            $table->char('customer_id', 36)->nullable()->index();
            $table->string('customer_name', 255)->nullable();           // denormalized for list/search
            $table->char('carrier_id', 36)->nullable()->index();
            $table->string('carrier_name', 255)->nullable();            // denormalized for list/search

            // Product / quantity
            $table->string('product_quality', 100)->nullable()->index();
            $table->decimal('target_quantity', 12, 3)->nullable();
            $table->string('unit', 10)->default('kg');

            // Planning
            $table->timestamp('planned_window_start')->nullable();
            $table->timestamp('planned_window_end')->nullable();

            // Assignment (separate states per V2.2 §4.2 / §4.3)
            $table->char('assigned_driver_id', 36)->nullable()->index();
            $table->string('assigned_driver_name', 255)->nullable();    // denormalized
            $table->string('assigned_driver_code', 50)->nullable();     // denormalized
            $table->char('assigned_trailer_id', 36)->nullable()->index();
            $table->string('assigned_trailer_label', 100)->nullable();  // denormalized
            $table->string('assigned_trailer_plate', 50)->nullable();   // denormalized

            // Driver flow / task
            $table->string('task_flow', 40)->nullable()->index();       // DriverTask enum value

            // Document requirements (per V2.2 §8.5)
            $table->boolean('requires_certificate')->default(true);
            $table->boolean('requires_delivery_note')->default(true);
            $table->boolean('requires_qm_document')->default(false);

            // Lifecycle — mostly derived (see LoadingOrderReadinessService).
            // Persisted exceptions: BLOCKED, CANCELLED (audit-anchored states).
            // `status` is the LAST RESOLVED derived value, refreshed by the service
            // before each save and on read; treat it as a cache, never as truth.
            $table->string('status', 30)->default('draft')->index();
            $table->string('current_step', 80)->nullable();
            $table->text('blocking_reason')->nullable();
            $table->string('blocking_reason_code', 100)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->unsignedBigInteger('blocked_by_user_id')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('cancellation_reason_code', 100)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->boolean('is_locked_by_execution')->default(false);  // becomes true when active plant_visit exists

            // Cross-module links (soft FKs; resolved when TSK-002 / loading_operations align)
            $table->char('active_plant_visit_id', 36)->nullable()->index();
            $table->string('active_plant_visit_no', 50)->nullable();
            $table->char('active_loading_operation_id', 36)->nullable()->index();

            // SAP protection
            $table->boolean('is_sap_owned')->default(false)->index();

            // Notes
            $table->text('notes')->nullable();

            // Audit metadata
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            // Index combos useful for the list page filters
            $table->index(['status', 'source']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loading_orders');
    }
};
