<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('freight_forwarders', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            $table->string('carrier_code', 50)->unique();
            $table->string('carrier_name', 255);
            $table->string('legal_name', 255)->nullable();
            $table->string('sap_reference', 50)->nullable()->unique();
            $table->string('external_reference', 100)->nullable();

            $table->string('street', 255)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('country', 100)->nullable()->index();

            $table->string('primary_contact_name', 255)->nullable();
            $table->string('contact_email', 255)->nullable()->index();
            $table->string('contact_phone', 50)->nullable()->index();

            $table->string('approval_state', 30)->default('pending_review')->index();
            $table->boolean('approved_for_loading')->default(false)->index();

            $table->string('status', 30)->default('active')->index();
            $table->string('block_reason', 255)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->unsignedBigInteger('blocked_by_user_id')->nullable();

            $table->text('notes')->nullable();

            // When true, the record originated from SAP and SAP-owned fields are
            // locked from local edits. Changes go through a controlled correction flow.
            $table->boolean('is_sap_owned')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();

            // Optional link to companies (same legal entity acting in multiple roles).
            $table->char('company_id', 36)->nullable()->index();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('freight_forwarders');
    }
};
