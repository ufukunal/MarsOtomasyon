<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bi_export_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('dataset_key', 128);
            $table->unsignedInteger('schema_version');
            $table->string('format', 16);
            $table->json('fields');
            $table->boolean('include_pii')->default(false);
            $table->string('watermark', 191)->nullable();
            $table->string('schedule_key', 128);
            $table->boolean('is_enabled')->default(true);
            $table->timestampTz('next_run_at')->nullable();
            $table->timestampTz('last_run_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'schedule_key'], 'bi_export_schedule_identity_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('bi_export_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('dataset_key', 128);
            $table->unsignedInteger('schema_version');
            $table->string('format', 16);
            $table->json('fields');
            $table->string('input_watermark', 191)->nullable();
            $table->string('output_watermark', 191)->nullable();
            $table->string('status', 24)->default('running');
            $table->unsignedBigInteger('row_count')->default(0);
            $table->char('artifact_sha256', 64)->nullable();
            $table->unsignedBigInteger('artifact_size_bytes')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'dataset_key', 'created_at'], 'bi_export_runs_dataset_index');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('schedule_id')->references('id')->on('bi_export_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_export_runs');
        Schema::dropIfExists('bi_export_schedules');
    }
};
