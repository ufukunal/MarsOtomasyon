<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bi_export_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dataset_key', 128);
            $table->unsignedInteger('schema_version');
            $table->string('format', 16);
            $table->json('fields');
            $table->boolean('include_pii')->default(false);
            $table->string('watermark', 191)->nullable();
            $table->string('schedule_key', 128);
            $table->unsignedInteger('interval_minutes')->default(1440);
            $table->boolean('is_enabled')->default(true);
            $table->timestampTz('next_run_at')->nullable();
            $table->timestampTz('last_run_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->unique(['company_id', 'schedule_key'], 'bi_export_schedule_identity_unique');
        });

        Schema::create('bi_export_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('bi_export_schedules')->nullOnDelete();
            $table->foreignId('retry_of_run_id')->nullable()->constrained('bi_export_runs')->nullOnDelete();
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
            $table->timestampTz('artifact_expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
            $table->index(['company_id', 'dataset_key', 'created_at'], 'bi_export_runs_dataset_index');
        });

        $now = now();
        DB::table('permissions')->insert([
            ['key' => 'reports.bi.export', 'name' => 'BI dışa aktarım', 'description' => 'Şirket kapsamlı BI datasetlerini dışa aktarma yetkisi.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'reports.bi.pii', 'name' => 'BI PII erişimi', 'description' => 'BI dışa aktarımlarında PII alanlarını maskesiz kullanma yetkisi.', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::statement("ALTER TABLE bi_export_runs ADD CONSTRAINT bi_export_runs_status_check CHECK (status IN ('running','succeeded','partial','failed'))");
        DB::statement('ALTER TABLE bi_export_schedules ADD CONSTRAINT bi_export_schedules_interval_check CHECK (interval_minutes >= 5)');
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('key', ['reports.bi.export', 'reports.bi.pii'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('key', ['reports.bi.export', 'reports.bi.pii'])->delete();
        Schema::dropIfExists('bi_export_runs');
        Schema::dropIfExists('bi_export_schedules');
    }
};
