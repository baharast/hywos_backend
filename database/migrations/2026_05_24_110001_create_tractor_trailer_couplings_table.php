<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tractor_trailer_couplings', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            $table->char('tractor_vehicle_id', 36)->index();

            // Soft FKs to trailers/drivers/plant_visits etc. that may not exist yet;
            // no DB-level constraints, only indexes.
            $table->char('trailer_id', 36)->nullable()->index();
            $table->string('trailer_label', 100)->nullable();

            $table->char('driver_id', 36)->nullable()->index();
            $table->string('driver_name', 255)->nullable();

            $table->char('plant_visit_id', 36)->nullable()->index();
            $table->string('visit_no', 50)->nullable();

            $table->char('order_id', 36)->nullable();
            $table->string('order_no', 50)->nullable();

            $table->string('source', 20)->nullable();   // terminal | gate | operator | reader | manual

            $table->timestamp('coupled_at')->nullable();
            $table->timestamp('uncoupled_at')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->timestamps();

            $table->index(['tractor_vehicle_id', 'is_active']);
            $table->index('coupled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tractor_trailer_couplings');
    }
};
