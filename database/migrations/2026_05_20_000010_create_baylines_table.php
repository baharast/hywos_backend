<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baylines', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('plant_configuration_id', 36)->nullable()->index();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->char('site_id', 36)->nullable();
            $table->char('plant_area_id', 36)->nullable();
            $table->string('pressure_class', 50)->nullable();
            $table->string('allowed_product', 100)->nullable();
            $table->char('related_panel_id', 36)->nullable();
            $table->char('related_device_id', 36)->nullable();
            $table->string('status_code')->default('free');
            $table->char('current_trailer_id', 36)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps(3);
            $table->char('created_by_user_id', 36)->nullable();
            $table->char('updated_by_user_id', 36)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baylines');
    }
};
