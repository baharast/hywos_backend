<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('driver_code', 50)->unique();

            $table->string('first_name', 100);
            $table->string('last_name', 100);

            $table->string('national_id_last4', 4)->nullable();
            $table->string('national_id_hash', 255)->nullable();

            $table->string('license_no', 50)->nullable()->index();
            $table->date('license_expiry_date')->nullable();

            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();

            $table->string('preferred_culture_code', 10)->default('de')->index();

            $table->string('training_status', 30)->default('unknown')->index();
            $table->date('training_valid_until')->nullable();

            $table->string('block_status', 20)->default('clear')->index();
            $table->text('block_reason')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->unsignedBigInteger('blocked_by_user_id')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->char('employer_company_id', 36)->nullable()->index();
            $table->char('operator_company_id', 36)->nullable()->index();

            $table->char('avatar_file_id', 36)->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
