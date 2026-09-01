<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_families', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 64);
            $table->string('name', 191);
            $table->json('shared_content')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'code'], 'product_families_company_code_unique');
            $table->unique(['company_id', 'id'], 'product_families_company_id_id_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('variant_dimensions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_family_id');
            $table->string('code', 64);
            $table->string('name', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->unique(['company_id', 'product_family_id', 'code'], 'variant_dimensions_family_code_unique');
            $table->unique(['company_id', 'id'], 'variant_dimensions_company_id_id_unique');
            $table->foreign(['company_id', 'product_family_id'], 'variant_dimensions_family_company_fk')
                ->references(['company_id', 'id'])->on('product_families')->cascadeOnDelete();
        });

        Schema::create('variant_values', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('variant_dimension_id');
            $table->string('code', 64);
            $table->string('label', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->unique(['company_id', 'variant_dimension_id', 'code'], 'variant_values_dimension_code_unique');
            $table->unique(['company_id', 'id'], 'variant_values_company_id_id_unique');
            $table->foreign(['company_id', 'variant_dimension_id'], 'variant_values_dimension_company_fk')
                ->references(['company_id', 'id'])->on('variant_dimensions')->cascadeOnDelete();
        });

        Schema::create('product_variant_relations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_family_id');
            $table->unsignedBigInteger('product_id');
            $table->char('variant_signature', 64);
            $table->timestampsTz();

            $table->unique('product_id', 'product_variant_relations_product_unique');
            $table->unique(['company_id', 'product_family_id', 'variant_signature'], 'product_variant_relations_signature_unique');
            $table->unique(['company_id', 'id'], 'product_variant_relations_company_id_id_unique');
            $table->foreign(['company_id', 'product_family_id'], 'product_variant_relations_family_company_fk')
                ->references(['company_id', 'id'])->on('product_families')->cascadeOnDelete();
            $table->foreign(['company_id', 'product_id'], 'product_variant_relations_product_company_fk')
                ->references(['company_id', 'id'])->on('products')->cascadeOnDelete();
        });

        Schema::create('product_variant_value_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_variant_relation_id');
            $table->unsignedBigInteger('variant_dimension_id');
            $table->unsignedBigInteger('variant_value_id');
            $table->timestampsTz();

            $table->unique(['product_variant_relation_id', 'variant_dimension_id'], 'product_variant_assignment_dimension_unique');
            $table->foreign(['company_id', 'product_variant_relation_id'], 'product_variant_assignment_relation_company_fk')
                ->references(['company_id', 'id'])->on('product_variant_relations')->cascadeOnDelete();
            $table->foreign(['company_id', 'variant_dimension_id'], 'product_variant_assignment_dimension_company_fk')
                ->references(['company_id', 'id'])->on('variant_dimensions')->restrictOnDelete();
            $table->foreign(['company_id', 'variant_value_id'], 'product_variant_assignment_value_company_fk')
                ->references(['company_id', 'id'])->on('variant_values')->restrictOnDelete();
        });

        Schema::create('marketplace_variant_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_variant_relation_id');
            $table->string('provider', 64);
            $table->string('parent_external_id', 191)->nullable();
            $table->string('variant_external_id', 191);
            $table->timestampsTz();

            $table->unique(['company_id', 'provider', 'product_variant_relation_id'], 'marketplace_variant_relation_provider_unique');
            $table->unique(['company_id', 'provider', 'variant_external_id'], 'marketplace_variant_external_unique');
            $table->foreign(['company_id', 'product_variant_relation_id'], 'marketplace_variant_relation_company_fk')
                ->references(['company_id', 'id'])->on('product_variant_relations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_variant_mappings');
        Schema::dropIfExists('product_variant_value_assignments');
        Schema::dropIfExists('product_variant_relations');
        Schema::dropIfExists('variant_values');
        Schema::dropIfExists('variant_dimensions');
        Schema::dropIfExists('product_families');
    }
};
