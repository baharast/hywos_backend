<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tractor_vehicles', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            $table->string('vehicle_code', 50)->nullable()->unique();
            $table->string('license_plate', 20)->unique();
            $table->string('plate_country', 5)->nullable()->index();
            $table->string('vehicle_type', 30)->default('tractor_unit')->index();

            $table->char('carrier_id', 36)->nullable()->index();
            $table->string('owner_name', 255)->nullable();
            $table->char('default_driver_id', 36)->nullable()->index();

            $table->string('vin', 50)->nullable();
            $table->date('registration_expiry')->nullable();
            $table->date('insurance_expiry')->nullable();

            $table->string('status', 20)->default('active');
            $table->text('block_reason')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->unsignedBigInteger('blocked_by_user_id')->nullable();
            $table->boolean('is_active')->default(true);

            $table->char('current_trailer_id', 36)->nullable();
            $table->string('current_trailer_label', 100)->nullable();
            $table->char('current_visit_id', 36)->nullable();
            $table->string('current_visit_no', 50)->nullable();
            $table->timestamp('last_visit_at')->nullable();
            $table->string('last_driver_name', 255)->nullable();
            $table->boolean('has_open_clarification')->default(false);

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tractor_vehicles');
    }
};
