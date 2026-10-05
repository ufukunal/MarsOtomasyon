<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('print_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('machine_key', 64)->nullable();
            $table->string('print_type', 30);
            $table->foreignId('template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->unsignedInteger('template_revision_no')->nullable();
            $table->foreignId('profile_id')->nullable()->constrained('print_profiles')->nullOnDelete();
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 20);
            $table->jsonb('result_metadata')->nullable();
            $table->char('parameters_hash', 64)->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'source_type', 'source_id']);
            $table->index(['parameters_hash']);
        });

        DB::connection('master')->statement(
            "ALTER TABLE print_jobs
             ADD CONSTRAINT print_jobs_status_check
             CHECK (status IN ('processing', 'done', 'failed'))"
        );

        DB::connection('master')->statement(
            'ALTER TABLE print_jobs
             ADD CONSTRAINT print_jobs_quantity_check
             CHECK (quantity > 0)'
        );
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('print_jobs');
    }
};
