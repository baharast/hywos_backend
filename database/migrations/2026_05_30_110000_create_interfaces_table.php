<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.4 §9 — the 11 exact interfaces are seeded rows; the dashboard is a
 * read-mostly monitor with one safe write surface (manual retry request).
 *
 * The model class is `App\Models\InterfaceHealth` (NOT `Interface` —
 * PHP reserves that identifier). The table name stays plural-canonical.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interfaces', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // Stable site-side code, e.g. IF-SAP-ORDER-IN. Unique so the
            // seeder can upsert by it.
            $table->string('exact_interface_id', 40)->unique();
            $table->string('name', 255);

            $table->string('family', 40)->index();          // InterfaceFamily
            $table->string('protocol', 40)->index();        // InterfaceProtocol
            $table->string('direction', 20);                // InterfaceDirection

            $table->string('source_label', 150)->nullable();
            $table->string('target_label', 150)->nullable();

            $table->string('status', 20)->index();          // InterfaceStatus
            $table->string('blocking_level', 20)->index();  // InterfaceBlockingLevel

            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();

            $table->unsignedInteger('queue_count')->default(0);
            $table->unsignedInteger('failed_today')->default(0);

            $table->string('last_error_text', 500)->nullable();

            // Demo retry tracker — stamp only; no real orchestration.
            $table->timestamp('last_retry_at')->nullable();
            $table->unsignedBigInteger('last_retry_by_user_id')->nullable();

            $table->text('fallback_behavior')->nullable();
            $table->boolean('local_operation_allowed')->default(true);
            $table->string('affected_process_label', 200)->nullable();

            // 'tbc_engineering' surfaces V1.4 §11 "TBC values" rule — keep
            // them inside data_status; do not let them become filter values.
            $table->string('data_status', 40)->nullable();
            $table->string('source_basis', 80)->nullable();

            $table->string('correlation_id', 64)->nullable();

            $table->timestamps();

            $table->index(['status', 'blocking_level'], 'interfaces_status_blocking_idx');
            $table->index(['family', 'status'], 'interfaces_family_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interfaces');
    }
};
