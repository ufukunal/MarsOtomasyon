<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cad_viewer_policies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('provider', 64);
            $table->boolean('cloud_upload_enabled')->default(false);
            $table->unsignedBigInteger('max_file_size_bytes')->nullable();
            $table->unsignedInteger('timeout_seconds')->nullable();
            $table->unsignedInteger('retention_days')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'provider'], 'cad_viewer_policy_company_provider_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('cad_derivative_jobs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('attachment_id');
            $table->unsignedBigInteger('file_asset_id');
            $table->char('source_sha256', 64);
            $table->string('source_extension', 32);
            $table->string('provider', 64);
            $table->string('provider_version', 64);
            $table->string('status', 32)->default('pending');
            $table->string('preview_kind', 32)->nullable();
            $table->string('provider_job_id', 191)->nullable();
            $table->json('manifest')->nullable();
            $table->char('derivative_sha256', 64)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestampTz('generated_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'attachment_id', 'source_sha256', 'provider', 'provider_version'], 'cad_derivative_job_identity_unique');
            $table->index(['company_id', 'status', 'created_at'], 'cad_derivative_job_status_index');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('attachment_id')->references('id')->on('attachments')->cascadeOnDelete();
            $table->foreign('file_asset_id')->references('id')->on('file_assets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cad_derivative_jobs');
        Schema::dropIfExists('cad_viewer_policies');
    }
};
