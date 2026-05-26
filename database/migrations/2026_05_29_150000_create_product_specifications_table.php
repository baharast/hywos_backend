<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_specifications — configure analysis acceptance limits per product
 * + quality class. Spec: FillTrack Products & Quality Specifications V2.1
 * §4 (Product Specifications tab) + §7 (versioning / audit rules).
 *
 * Each row is one (product_code, spec_version) header. The 6-component
 * gas-limit rows live in `product_gas_limits` and reference back via
 * soft FK `spec_id`.
 *
 * Lifecycle status (draft → active → inactive → retired) is enforced by
 * ProductSpecificationService — see ProductSpecStatus enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // Identity
            $table->string('product_code', 50)->index();              // e.g. H2-5.0
            $table->string('quality_class', 50);                       // e.g. 5.0 / 3.5
            $table->string('display_name', 255);                       // e.g. "Hydrogen 5.0"
            $table->string('spec_version', 20);                        // v1 / v2 / v3 (per product_code)

            // Lifecycle
            $table->string('status', 20)->default('draft')->index();   // ProductSpecStatus
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->text('notes')->nullable();

            // Activate/retire metadata
            $table->timestamp('activated_at')->nullable();
            $table->unsignedBigInteger('activated_by_user_id')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->unsignedBigInteger('retired_by_user_id')->nullable();

            // Standard audit metadata
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();

            // V2.1 §7: each (product_code, spec_version) pair is unique.
            // Editing a used active spec creates a new version row rather
            // than overwriting the old one.
            $table->unique(['product_code', 'spec_version'], 'product_specs_code_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};
