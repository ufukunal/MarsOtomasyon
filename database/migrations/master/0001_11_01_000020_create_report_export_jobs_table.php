<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('report_export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_key', 120);
            $table->string('format', 10);
            $table->jsonb('filters');
            $table->jsonb('periods');
            $table->jsonb('permission_scope');
            $table->char('parameters_hash', 64);
            $table->string('status', 20)->default('queued');
            $table->unsignedSmallInteger('progress')->nullable();
            $table->string('storage_disk', 80)->nullable();
            $table->string('storage_path', 500)->nullable();
            $table->string('error_summary', 1000)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'user_id', 'created_at'], 'report_export_jobs_history_idx');
            $table->index(['status', 'created_at'], 'report_export_jobs_status_idx');
            $table->index('parameters_hash');
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('report_export_jobs');
    }
};
