<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('display_name', 255);

            $table->json('categories');                           // string array of category slugs
            $table->string('record_scope', 40)->index();          // all_records | created_or_updated_in_range
            $table->timestamp('date_from')->nullable();
            $table->timestamp('date_to')->nullable();
            $table->string('status_scope', 40);                   // active_current_only | include_inactive_blocked_archived | all_statuses
            $table->string('field_set', 30);                      // default_fields | all_exportable_fields
            $table->string('format', 10);                         // csv | xlsx (MVP supports csv only)

            $table->string('status', 30)->default('queued')->index();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('requested_by_name', 255)->nullable();

            $table->string('file_path', 500)->nullable();         // storage path relative to disk
            $table->string('file_disk', 30)->nullable();          // storage disk name
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('record_count_estimate')->nullable();
            $table->unsignedInteger('record_count_actual')->nullable();

            $table->text('error_message')->nullable();
            $table->json('warnings')->nullable();                 // non-fatal per-category warnings

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();

            $table->string('correlation_id', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
