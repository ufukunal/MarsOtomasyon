<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('deployment_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('release_id', 120)->unique();
            $table->string('commit_sha', 64);
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->string('initiated_by_name')->nullable();
            $table->string('status', 20);
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->string('previous_release_id', 120)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->timestampsTz();
            $table->index(['status', 'started_at']);
        });

        Schema::connection('master')->create('backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('recovery_set_id')->unique();
            $table->string('trigger_type', 20);
            $table->string('status', 20);
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->string('storage_disk', 80);
            $table->string('manifest_path')->nullable();
            $table->string('master_backup_path')->nullable();
            $table->string('files_backup_path')->nullable();
            $table->jsonb('period_manifest')->nullable();
            $table->jsonb('checksum_manifest')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->timestampsTz();
            $table->index(['status', 'started_at']);
        });

        Schema::connection('master')->create('restore_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('recovery_set_id');
            $table->foreignId('source_backup_run_id')->constrained('backup_runs')->restrictOnDelete();
            $table->string('target_type', 30);
            $table->string('status', 20);
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->jsonb('verification_summary')->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->timestampsTz();
            $table->index(['status', 'started_at']);
            $table->index('recovery_set_id');
        });

        Schema::connection('master')->create('operational_heartbeats', function (Blueprint $table): void {
            $table->string('service_key', 80)->primary();
            $table->timestampTz('last_seen_at');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::connection('master')->create('health_check_runs', function (Blueprint $table): void {
            $table->id();
            $table->timestampTz('checked_at');
            $table->string('overall_status', 20);
            $table->jsonb('checks');
            $table->uuid('correlation_id')->nullable();
            $table->timestampsTz();
            $table->index(['overall_status', 'checked_at']);
        });

        DB::connection('master')->statement(
            "ALTER TABLE deployment_runs ADD CONSTRAINT deployment_runs_status_check
             CHECK (status IN ('preparing','migrating','verifying','active','failed','rolled_back'))"
        );
        DB::connection('master')->statement(
            "ALTER TABLE backup_runs ADD CONSTRAINT backup_runs_trigger_check
             CHECK (trigger_type IN ('scheduled','deploy','period_carry','manual'))"
        );
        DB::connection('master')->statement(
            "ALTER TABLE backup_runs ADD CONSTRAINT backup_runs_status_check
             CHECK (status IN ('running','done','failed','verified'))"
        );
        DB::connection('master')->statement(
            "ALTER TABLE restore_runs ADD CONSTRAINT restore_runs_target_check
             CHECK (target_type IN ('temporary','archive','production_recovery'))"
        );
        DB::connection('master')->statement(
            "ALTER TABLE restore_runs ADD CONSTRAINT restore_runs_status_check
             CHECK (status IN ('preparing','restoring','verifying','verified','failed'))"
        );
        DB::connection('master')->statement(
            "ALTER TABLE health_check_runs ADD CONSTRAINT health_check_runs_status_check
             CHECK (overall_status IN ('healthy','degraded','failed'))"
        );
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('health_check_runs');
        Schema::connection('master')->dropIfExists('operational_heartbeats');
        Schema::connection('master')->dropIfExists('restore_runs');
        Schema::connection('master')->dropIfExists('backup_runs');
        Schema::connection('master')->dropIfExists('deployment_runs');
    }
};
