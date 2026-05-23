<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('actor_name', 255)->nullable();
            $table->string('entity_type', 100)->index();
            $table->string('entity_id', 100)->index();
            $table->string('action', 100)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason_code', 100)->nullable();
            $table->text('reason')->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id', 100)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
