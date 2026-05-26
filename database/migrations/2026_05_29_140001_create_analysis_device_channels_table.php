<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REGARD3900 channel rows per V1 §8.2 / §9. One channel per gas / sensor
 * input. The channel severity drives the card's `channelSummary` and the
 * highest-severity channel surfaces as the safety state on the device
 * card itself.
 *
 * UNIQUE(device_id, channel_code) — the controller may re-seed devices
 * but channels stay addressable by their stable site-side code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_device_channels', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('device_id', 36)->index();

            $table->string('channel_code', 50);
            $table->string('label', 100);
            $table->string('gas', 20)->nullable();

            // normal | a1 | a2 | a3 | f1 | f2
            $table->string('severity', 20);

            $table->decimal('measured_value', 12, 3)->nullable();
            $table->string('unit', 20)->nullable();

            $table->boolean('acknowledged')->default(false);
            $table->boolean('inhibited')->default(false);

            $table->string('last_message', 500)->nullable();
            $table->timestamp('last_value_at')->nullable();

            $table->timestamps();

            $table->unique(['device_id', 'channel_code'], 'uq_analysis_channel_per_device');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_device_channels');
    }
};
