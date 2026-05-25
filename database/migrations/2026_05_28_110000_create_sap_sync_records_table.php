<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAP Sync / Order Import Status table (V1.5).
 *
 * Each row is ONE order-related SAP communication event — one direction
 * (import|export), one SAP reference, one local order context (V1.5 §4.3 /
 * §8.6.1). A SAP batch with N orders produces N rows. Import and export for
 * the same SAP reference are SEPARATE rows.
 *
 * Soft FKs only — `order_id`, `customer_id`, `carrier_id` reference rows in
 * tables that may be reseeded independently. The denormalized `*_name` /
 * `order_no` columns let the list page render without joins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sap_sync_records', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // Communication identity
            $table->string('direction', 20);                        // SapSyncDirection enum
            $table->string('sap_reference', 100);                   // SAP delivery / transport / order ref
            $table->char('order_id', 36)->nullable();               // soft FK -> loading_orders.id
            $table->string('order_no', 50)->nullable();             // denorm; null when import failed before order creation

            // Commercial context (denormalized)
            $table->char('customer_id', 36)->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->char('carrier_id', 36)->nullable();
            $table->string('carrier_name', 255)->nullable();
            $table->string('product_quality', 100)->nullable();

            // Result / handling state
            $table->string('result_status', 20);                    // SapSyncResultStatus enum
            $table->string('handling_status', 40)->nullable();      // SapHandlingStatus enum
            $table->string('feedback_type', 30)->nullable();        // SapFeedbackType enum (export only)

            // Plain-language narrative (backend-supplied; FE must NOT invent)
            $table->string('issue_reason', 500)->nullable();
            $table->string('what_happened', 500)->nullable();
            $table->string('next_action', 255)->nullable();

            // Restricted / IT-Support fields (V1.5 §2.1) — gate by role when auth lands
            $table->text('technical_message')->nullable();
            $table->text('connector_message')->nullable();
            $table->string('sync_run_id', 64)->nullable();          // batch reference
            $table->string('interface_id', 64)->nullable();         // SAP interface
            $table->unsignedInteger('retry_count')->default(0);
            $table->string('owner_role', 40)->nullable();           // IT_Support | SAP_Responsible | Dispatcher_Admin

            // Time anchors
            $table->timestamp('event_time')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();

            // Attempt history JSON — exposed only in the detail response (V1.5 §8.8)
            $table->json('attempts')->nullable();

            // Tracing
            $table->string('correlation_id', 64)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Single-column indexes
            $table->index('direction');
            $table->index('sap_reference');
            $table->index('order_id');
            $table->index('result_status');
            $table->index('handling_status');
            $table->index('sync_run_id');
            $table->index('event_time');
            $table->index('last_attempt_at');
            $table->index('correlation_id');

            // Composite indexes for the V1.5 filter combinations
            $table->index(['direction', 'result_status']);
            $table->index(['result_status', 'last_attempt_at']);
            $table->index(['sap_reference', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sap_sync_records');
    }
};
