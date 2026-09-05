<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_families', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 191);
            $table->jsonb('shared_content')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'product_families_company_id_unique');
            $table->index(['company_id', 'name'], 'product_families_company_name_idx');
        });
        DB::statement('CREATE UNIQUE INDEX product_families_company_code_lower_unique ON product_families (company_id, lower(code))');
        DB::statement('ALTER TABLE product_families ADD CONSTRAINT product_families_code_not_blank_check CHECK (char_length(btrim(code)) > 0 AND code = btrim(code))');
        DB::statement('ALTER TABLE product_families ADD CONSTRAINT product_families_name_not_blank_check CHECK (char_length(btrim(name)) > 0 AND name = btrim(name))');

        Schema::create('variant_dimensions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_family_id');
            $table->string('code', 64);
            $table->string('name', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'variant_dimensions_company_id_unique');
            $table->unique(['company_id', 'product_family_id', 'id'], 'variant_dimensions_family_id_unique');
            $table->foreign(['company_id', 'product_family_id'], 'variant_dimensions_family_company_fk')
                ->references(['company_id', 'id'])->on('product_families')->cascadeOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX variant_dimensions_family_code_lower_unique ON variant_dimensions (company_id, product_family_id, lower(code))');
        DB::statement('ALTER TABLE variant_dimensions ADD CONSTRAINT variant_dimensions_code_not_blank_check CHECK (char_length(btrim(code)) > 0 AND code = btrim(code))');
        DB::statement('ALTER TABLE variant_dimensions ADD CONSTRAINT variant_dimensions_name_not_blank_check CHECK (char_length(btrim(name)) > 0 AND name = btrim(name))');

        Schema::create('variant_values', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_family_id');
            $table->unsignedBigInteger('variant_dimension_id');
            $table->string('code', 64);
            $table->string('label', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'variant_values_company_id_unique');
            $table->unique(['company_id', 'product_family_id', 'variant_dimension_id', 'id'], 'variant_values_dimension_id_unique');
            $table->foreign(['company_id', 'product_family_id', 'variant_dimension_id'], 'variant_values_dimension_family_fk')
                ->references(['company_id', 'product_family_id', 'id'])->on('variant_dimensions')->cascadeOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX variant_values_dimension_code_lower_unique ON variant_values (company_id, variant_dimension_id, lower(code))');
        DB::statement('ALTER TABLE variant_values ADD CONSTRAINT variant_values_code_not_blank_check CHECK (char_length(btrim(code)) > 0 AND code = btrim(code))');
        DB::statement('ALTER TABLE variant_values ADD CONSTRAINT variant_values_label_not_blank_check CHECK (char_length(btrim(label)) > 0 AND label = btrim(label))');

        Schema::create('product_variant_relations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_family_id');
            $table->unsignedBigInteger('product_id');
            $table->char('variant_signature', 64);
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'product_variant_relations_company_id_unique');
            $table->unique(['company_id', 'product_family_id', 'id'], 'product_variant_relations_family_id_unique');
            $table->unique(['company_id', 'product_id'], 'product_variant_relations_product_unique');
            $table->unique(['company_id', 'product_family_id', 'variant_signature'], 'product_variant_relations_signature_unique');
            $table->foreign(['company_id', 'product_family_id'], 'product_variant_relations_family_company_fk')
                ->references(['company_id', 'id'])->on('product_families')->cascadeOnDelete();
            $table->foreign(['company_id', 'product_id'], 'product_variant_relations_product_company_fk')
                ->references(['company_id', 'id'])->on('products')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE product_variant_relations ADD CONSTRAINT product_variant_relations_signature_check CHECK (variant_signature ~ '^[0-9a-f]{64}$')");

        Schema::create('product_variant_value_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_family_id');
            $table->unsignedBigInteger('product_variant_relation_id');
            $table->unsignedBigInteger('variant_dimension_id');
            $table->unsignedBigInteger('variant_value_id');
            $table->timestampsTz();

            $table->unique(['company_id', 'product_variant_relation_id', 'variant_dimension_id'], 'product_variant_assignment_dimension_unique');
            $table->foreign(['company_id', 'product_family_id', 'product_variant_relation_id'], 'product_variant_assignment_relation_family_fk')
                ->references(['company_id', 'product_family_id', 'id'])->on('product_variant_relations')->cascadeOnDelete();
            $table->foreign(['company_id', 'product_family_id', 'variant_dimension_id'], 'product_variant_assignment_dimension_family_fk')
                ->references(['company_id', 'product_family_id', 'id'])->on('variant_dimensions')->restrictOnDelete();
            $table->foreign(['company_id', 'product_family_id', 'variant_dimension_id', 'variant_value_id'], 'product_variant_assignment_value_dimension_fk')
                ->references(['company_id', 'product_family_id', 'variant_dimension_id', 'id'])->on('variant_values')->restrictOnDelete();
        });

        Schema::create('product_family_channel_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->unsignedBigInteger('product_family_id');
            $table->string('provider', 64);
            $table->string('external_parent_id', 192);
            $table->string('status', 16)->default('active');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'product_family_channel_mappings_company_id_unique');
            $table->unique(['company_id', 'connection_id', 'product_family_id'], 'product_family_channel_mapping_local_unique');
            $table->unique(['company_id', 'connection_id', 'provider', 'external_parent_id'], 'product_family_channel_mapping_external_unique');
            $table->foreign(['company_id', 'connection_id'], 'product_family_channel_mapping_connection_fk')
                ->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->foreign(['company_id', 'product_family_id'], 'product_family_channel_mapping_family_fk')
                ->references(['company_id', 'id'])->on('product_families')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE product_family_channel_mappings ADD CONSTRAINT product_family_channel_mappings_status_check CHECK (status IN ('active', 'inactive'))");
        DB::statement('ALTER TABLE product_family_channel_mappings ADD CONSTRAINT product_family_channel_mappings_provider_check CHECK (char_length(btrim(provider)) > 0 AND provider = btrim(provider))');
        DB::statement('ALTER TABLE product_family_channel_mappings ADD CONSTRAINT product_family_channel_mappings_external_check CHECK (char_length(btrim(external_parent_id)) > 0 AND external_parent_id = btrim(external_parent_id))');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_family_channel_mappings');
        Schema::dropIfExists('product_variant_value_assignments');
        Schema::dropIfExists('product_variant_relations');
        Schema::dropIfExists('variant_values');
        Schema::dropIfExists('variant_dimensions');
        Schema::dropIfExists('product_families');
    }
};
