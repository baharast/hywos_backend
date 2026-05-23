<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminals_panels', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('plant_configuration_id', 36)->nullable()->index();
            $table->char('site_id', 36)->index();
            $table->char('plant_area_id', 36)->nullable()->index();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('terminal_type', 50)->index();
            $table->char('related_device_id', 36)->nullable();
            $table->json('language_support')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['site_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminals_panels');
    }
};
