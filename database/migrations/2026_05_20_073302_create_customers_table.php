<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('legal_name', 255)->nullable();
            $table->string('sap_customer_no', 50)->nullable()->unique();
            $table->string('external_reference', 100)->nullable();

            $table->string('street', 255)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('country', 100)->nullable()->index();

            $table->string('primary_contact_name', 255)->nullable();
            $table->string('email', 255)->nullable()->index();
            $table->string('phone', 50)->nullable()->index();

            $table->json('document_requirements')->nullable();
            $table->string('default_document_language', 10)->nullable();

            $table->string('status', 30)->default('active')->index();
            $table->string('block_reason', 255)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->char('blocked_by_user_id', 36)->nullable();

            $table->text('notes')->nullable();

            // When true, the record originated from SAP and SAP-owned fields are
            // locked from local edits. Changes go through a controlled correction flow.
            $table->boolean('is_sap_owned')->default(false)->index();

            $table->boolean('is_active')->default(true)->index();
            $table->char('site_id', 36)->nullable()->index();
            $table->char('created_by_user_id', 36)->nullable()->index();
            $table->char('updated_by_user_id', 36)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }
};
