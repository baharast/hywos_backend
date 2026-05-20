<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('legal_name', 150)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);

            $table->index('code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
