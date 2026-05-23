<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_media', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            $table->string('medium_type', 20)->index(); // chip_card | tan | badge | other
            $table->string('identifier_value', 255)->nullable(); // raw — only for TANs; hidden from API
            $table->string('identifier_hash', 64)->nullable();   // chip-card hash; never raw
            $table->string('display_identifier', 100)->nullable();

            $table->char('driver_id', 36)->nullable()->index();

            $table->string('status', 20)->default('active')->index();
            $table->boolean('is_single_use')->default(false);

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by_user_id')->nullable();
            $table->text('revocation_reason')->nullable();

            $table->char('order_id', 36)->nullable()->index();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            // Composite indexes
            $table->index(['driver_id', 'status']);
            $table->index(['medium_type', 'identifier_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_media');
    }
};
