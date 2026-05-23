<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loading_operations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('display_no', 50)->unique();

            $table->char('bay_line_id', 36)->nullable()->index();
            $table->char('driver_id', 36)->nullable()->index();

            $table->char('trailer_id', 36)->nullable()->index();
            $table->string('trailer_label', 100)->nullable();
            $table->string('tractor_plate', 50)->nullable();

            $table->char('order_id', 36)->nullable()->index();
            $table->string('order_no', 50)->nullable();
            $table->string('sap_order_no', 50)->nullable();

            $table->char('plant_visit_id', 36)->nullable()->index();
            $table->string('visit_no', 50)->nullable();

            $table->char('customer_id', 36)->nullable();
            $table->string('customer_name', 255)->nullable();

            $table->string('product_quality', 100)->nullable();
            $table->decimal('target_quantity', 12, 3)->nullable();
            $table->decimal('actual_quantity', 12, 3)->nullable();
            $table->string('unit', 10)->default('kg');
            $table->unsignedTinyInteger('progress_percent')->nullable();

            $table->string('loading_status', 40)->default('assigned')->index();
            $table->string('analysis_status', 40)->default('not_started');

            $table->string('release_source', 30)->nullable();
            $table->string('release_reason_code', 100)->nullable();
            $table->text('release_reason')->nullable();

            $table->boolean('has_clarification')->default(false);
            $table->unsignedInteger('alarm_count')->default(0);
            $table->unsignedInteger('critical_alarm_count')->default(0);

            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_event_at')->nullable();

            $table->string('plc_status', 20)->nullable();

            $table->string('correlation_id', 64)->nullable()->index();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['bay_line_id', 'loading_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loading_operations');
    }
};
