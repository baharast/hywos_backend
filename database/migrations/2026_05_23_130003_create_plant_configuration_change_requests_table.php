<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_configuration_change_requests', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('plant_configuration_id', 36)->index();
            $table->string('affected_object_type', 50)->index();
            $table->char('affected_object_id', 36)->index();
            $table->string('affected_object_label', 255)->nullable();
            $table->string('change_type', 30)->index();
            $table->json('current_values')->nullable();
            $table->json('proposed_values')->nullable();
            $table->text('reason');
            $table->string('reason_code', 100)->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('rejected_by_user_id')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('applied_by_user_id')->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_configuration_change_requests');
    }
};
