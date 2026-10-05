<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('template_key', 120);
            $table->string('name', 160);
            $table->unsignedInteger('revision_no');
            $table->string('render_type', 20);
            $table->string('paper_code', 40)->nullable();
            $table->decimal('width_mm', 10, 2)->nullable();
            $table->decimal('height_mm', 10, 2)->nullable();
            $table->jsonb('definition');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['company_id', 'template_key', 'revision_no'], 'document_templates_revision_unique');
            $table->index(['company_id', 'template_key', 'is_active']);
        });

        DB::connection('master')->statement(
            'CREATE UNIQUE INDEX document_templates_default_active_unique
             ON document_templates (company_id, template_key)
             WHERE is_default = true AND is_active = true'
        );

        DB::connection('master')->statement(
            "ALTER TABLE document_templates
             ADD CONSTRAINT document_templates_render_type_check
             CHECK (render_type IN ('html_pdf', 'zpl', 'text'))"
        );
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('document_templates');
    }
};
