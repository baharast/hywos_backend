<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * hardware_devices — master registry for System & Devices → Hardware
 * Devices page (V1.4).
 *
 * Per spec §4.3 (Device Registry / Overview Tab) + §11 (Implementation
 * Notes). Other hardware tabs (Terminals & Panels, Printers, Card
 * Readers, PLC / OPC UA Health) will soft-FK to `hardware_devices.id`
 * in later slices.
 *
 * Spec §11 TBC rule: rows with `data_status='tbc_engineering'` are kept
 * in the registry (so engineering can confirm them) but excluded from
 * the dropdown filter options shown to operators.
 *
 * `is_blocking_critical_process` is denormalised — the model's saving()
 * hook keeps it in sync from (criticality, health, service_mode). It
 * powers the V1.4 §9 default sort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hardware_devices', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // Identity
            $table->string('asset_tag', 50)->unique();      // FillTrack tag, e.g. HD-GATE-ENTRY-01
            $table->string('vendor_tag', 100)->nullable();  // engineering reference, never overwritten
            $table->string('name', 255);

            // Classification (§3 filter source mapping)
            $table->string('device_type', 40)->index();
            $table->string('subsystem', 40)->index();
            $table->string('physical_location', 80)->index();
            $table->string('health', 20)->index();
            $table->string('criticality', 20)->index();

            // Process impact
            $table->string('affected_process', 60)->nullable();
            $table->string('affected_process_label', 120)->nullable();

            // Connectivity metadata
            $table->string('protocol', 30)->nullable();

            // Heartbeat / message
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('last_event_at')->nullable();
            $table->string('last_message', 500)->nullable();

            // Service mode (V1.4 §10)
            $table->boolean('service_mode')->default(false)->index();
            $table->text('service_mode_reason')->nullable();
            $table->timestamp('service_mode_set_at')->nullable();
            $table->unsignedBigInteger('service_mode_set_by_user_id')->nullable();
            $table->timestamp('service_mode_expected_end_at')->nullable();

            // Safe connection-test trace (V1.4 §10)
            $table->timestamp('connection_test_last_run_at')->nullable();
            $table->string('connection_test_last_result', 20)->nullable();

            // Denormalised flag for V1.4 §9 default sort.
            $table->boolean('is_blocking_critical_process')->default(false)->index();

            // §11 TBC handling + source traceability
            $table->string('data_status', 40)->nullable();
            $table->string('source_basis', 80)->nullable();

            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();

            $table->index(['health', 'criticality'], 'hardware_devices_health_critical_idx');
            $table->index(['device_type', 'physical_location'], 'hardware_devices_type_location_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_devices');
    }
};
