<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('company_id', 36)->nullable()->index();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('plant_type', 50)->nullable();
            $table->string('default_language', 10)->default('de');
            $table->string('time_zone', 50)->default('Europe/Berlin');
            $table->string('address_line', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sites');
    }
};
