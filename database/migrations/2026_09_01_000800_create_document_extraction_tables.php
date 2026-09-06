<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_extraction_jobs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('attachment_id');
            $table->char('source_sha256', 64);
            $table->string('provider', 64);
            $table->string('model', 128);
            $table->string('provider_version', 64);
            $table->string('document_type', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->decimal('confidence_threshold', 5, 4)->default(0.8500);
            $table->timestampTz('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->json('reviewed_payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'attachment_id', 'source_sha256', 'provider', 'model', 'provider_version'], 'document_extraction_job_identity_unique');
            $table->index(['company_id', 'status', 'created_at'], 'document_extraction_job_status_index');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('attachment_id')->references('id')->on('attachments')->cascadeOnDelete();
            $table->foreign('reviewed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('document_extracted_fields', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('extraction_job_id');
            $table->string('field_key', 128);
            $table->json('extracted_value')->nullable();
            $table->decimal('confidence', 5, 4);
            $table->boolean('requires_review')->default(false);
            $table->timestampsTz();

            $table->unique(['extraction_job_id', 'field_key'], 'document_extracted_field_key_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('extraction_job_id')->references('id')->on('document_extraction_jobs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extracted_fields');
        Schema::dropIfExists('document_extraction_jobs');
    }
};
