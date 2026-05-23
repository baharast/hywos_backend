<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trailers', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('trailer_code', 50)->unique();
            $table->string('trailer_label', 150)->nullable();
            $table->string('plate', 50)->nullable()->index();

            $table->string('trailer_type', 50)->nullable()->index();
            $table->string('pressure_class', 50)->nullable()->index();
            $table->decimal('volume', 12, 3)->nullable();
            $table->string('volume_unit', 10)->nullable();

            $table->json('approved_product_quality')->nullable();

            $table->date('inspection_expiry_date')->nullable()->index();
            $table->string('inspection_reference', 100)->nullable();

            $table->string('technical_suitability', 30)->default('incomplete')->index();

            $table->string('status', 30)->default('active')->index();
            $table->string('block_reason', 255)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->unsignedBigInteger('blocked_by_user_id')->nullable();

            $table->char('carrier_id', 36)->nullable()->index();
            $table->char('customer_id', 36)->nullable()->index();

            $table->char('current_parking_id', 36)->nullable();
            $table->string('current_context', 255)->nullable();

            $table->timestamp('last_visit_at')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trailers');
    }
};
