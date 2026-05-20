<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parkings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->char('site_id', 36)->nullable()->index();
            $table->char('area_id', 36)->nullable()->index();
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('occupied_count')->default(0);
            $table->string('status_code', 50)->nullable()->index();
            $table->char('current_vehicle_id', 36)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->char('created_by_user_id', 36)->nullable()->index();
            $table->char('updated_by_user_id', 36)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('parkings');
    }
};
