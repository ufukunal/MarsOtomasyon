<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restore_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('backup_artifact_id');
            $table->foreignId('started_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->boolean('safety_backup_requested')->default(true);
            $table->jsonb('checks')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->foreign('backup_artifact_id')->references('id')->on('backup_artifacts')->restrictOnDelete();
            $table->index(['status', 'started_at'], 'restore_runs_status_started_index');
            $table->index(['backup_artifact_id', 'started_at'], 'restore_runs_backup_started_index');
        });

        DB::statement("ALTER TABLE restore_runs ADD CONSTRAINT restore_runs_status_check CHECK (status IN ('running','succeeded','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_runs');
    }
};
