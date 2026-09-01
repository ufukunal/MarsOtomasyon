<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('key', 80);
            $table->string('name', 160);
            $table->string('target_type', 40);
            $table->string('output_format', 16);
            $table->unsignedSmallInteger('width_mm')->default(100);
            $table->unsignedSmallInteger('height_mm')->default(50);
            $table->text('body_template');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'key'], 'label_templates_company_key_unique');
            $table->unique(['company_id', 'id'], 'label_templates_company_id_id_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('label_render_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('label_template_id');
            $table->string('target_type', 40);
            $table->unsignedBigInteger('target_id');
            $table->unsignedBigInteger('barcode_id')->nullable();
            $table->string('output_format', 16);
            $table->json('payload');
            $table->char('output_sha256', 64);
            $table->unsignedBigInteger('rendered_by_user_id')->nullable();
            $table->unsignedBigInteger('reprint_of_id')->nullable();
            $table->timestampTz('rendered_at');
            $table->timestampsTz();

            $table->index(['company_id', 'target_type', 'target_id', 'rendered_at'], 'label_render_target_index');
            $table->foreign(['company_id', 'label_template_id'], 'label_render_template_company_fk')
                ->references(['company_id', 'id'])->on('label_templates')->restrictOnDelete();
            $table->foreign('barcode_id')->references('id')->on('barcodes')->nullOnDelete();
            $table->foreign('rendered_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reprint_of_id')->references('id')->on('label_render_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_render_requests');
        Schema::dropIfExists('label_templates');
    }
};
