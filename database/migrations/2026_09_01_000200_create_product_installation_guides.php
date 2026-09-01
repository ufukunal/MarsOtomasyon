<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_installation_guides', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_id');
            $table->string('title');
            $table->timestampsTz();

            $table->unique(['company_id', 'product_id'], 'product_installation_guides_company_product_unique');
            $table->unique(['company_id', 'id'], 'product_installation_guides_company_id_unique');
            $table->foreign(['company_id', 'product_id'], 'product_installation_guides_product_company_fk')
                ->references(['company_id', 'id'])
                ->on('products')
                ->cascadeOnDelete();
        });

        Schema::create('product_installation_guide_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('guide_id');
            $table->unsignedInteger('version_no');
            $table->json('content');
            $table->unsignedBigInteger('pdf_attachment_id')->nullable();
            $table->unsignedBigInteger('generated_by_user_id');
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->unique(['guide_id', 'version_no'], 'installation_guide_versions_guide_version_unique');
            $table->index(['company_id', 'guide_id', 'version_no'], 'installation_guide_versions_company_guide_index');
            $table->foreign(['company_id', 'guide_id'], 'installation_guide_versions_guide_company_fk')
                ->references(['company_id', 'id'])
                ->on('product_installation_guides')
                ->cascadeOnDelete();
            $table->foreign(['company_id', 'pdf_attachment_id'], 'installation_guide_versions_attachment_company_fk')
                ->references(['company_id', 'id'])
                ->on('attachments')
                ->restrictOnDelete();
            $table->foreign('generated_by_user_id', 'installation_guide_versions_generated_by_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_installation_guide_versions');
        Schema::dropIfExists('product_installation_guides');
    }
};
