<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_type', 100)->index();
            $table->string('event_category', 50)->nullable()->index();
            $table->string('severity', 20)->default('info')->index();
            $table->string('entity_type', 100)->nullable()->index();
            $table->string('entity_id', 100)->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('actor_name', 255)->nullable();
            $table->string('message', 500)->nullable();
            $table->json('details')->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
};
