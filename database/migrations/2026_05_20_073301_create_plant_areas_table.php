<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plant_areas', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('site_id', 36)->index();
            $table->string('code', 50)->nullable();
            $table->string('name', 255);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plant_areas');
    }
};
