<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_configurations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('site_id', 36)->index();
            $table->char('company_id', 36)->nullable()->index();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('company_name', 255)->nullable();
            $table->string('company_code', 50)->nullable();
            $table->string('site_name', 255)->nullable();
            $table->string('site_code', 50)->nullable();
            $table->string('plant_type', 50)->nullable();
            $table->string('default_language', 10)->default('de');
            $table->string('time_zone', 50)->default('Europe/Berlin');
            $table->json('validation_summary')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->unsignedBigInteger('activated_by_user_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_configurations');
    }
};
