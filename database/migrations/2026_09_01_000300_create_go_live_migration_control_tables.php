<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('source_key', 80);
            $table->string('label', 160);
            $table->char('source_fingerprint', 64);
            $table->string('status', 24)->default('inventory');
            $table->timestampTz('last_rehearsed_at')->nullable();
            $table->timestampTz('cutover_started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'source_key'], 'migration_sources_company_key_unique');
            $table->unique(['company_id', 'id'], 'migration_sources_company_id_id_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('migration_source_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('migration_source_id');
            $table->string('entity_type', 80);
            $table->string('source_identity', 191);
            $table->char('payload_sha256', 64);
            $table->string('status', 24)->default('staged');
            $table->boolean('dry_run')->default(true);
            $table->string('target_type', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('imported_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'migration_source_id', 'entity_type', 'source_identity'], 'migration_records_source_identity_unique');
            $table->index(['company_id', 'migration_source_id', 'status'], 'migration_records_source_status_index');
            $table->foreign(['company_id', 'migration_source_id'], 'migration_records_source_company_fk')
                ->references(['company_id', 'id'])->on('migration_sources')->cascadeOnDelete();
        });

        Schema::create('migration_reconciliation_checks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('migration_source_id');
            $table->string('checkpoint_key', 120);
            $table->string('scope', 80);
            $table->string('expected_value', 191)->nullable();
            $table->string('actual_value', 191)->nullable();
            $table->boolean('passed');
            $table->json('details')->nullable();
            $table->timestampTz('checked_at');
            $table->timestampsTz();

            $table->unique(['company_id', 'migration_source_id', 'checkpoint_key'], 'migration_reconciliation_checkpoint_unique');
            $table->foreign(['company_id', 'migration_source_id'], 'migration_reconciliation_source_company_fk')
                ->references(['company_id', 'id'])->on('migration_sources')->cascadeOnDelete();
        });

        Schema::create('migration_channel_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('migration_source_id');
            $table->string('provider', 80);
            $table->string('channel_identity', 191);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_paused')->default(false);
            $table->text('cursor')->nullable();
            $table->text('watermark')->nullable();
            $table->string('inbox_marker', 191)->nullable();
            $table->timestampTz('checked_at');
            $table->timestampsTz();

            $table->unique(['company_id', 'migration_source_id', 'provider', 'channel_identity'], 'migration_channel_identity_unique');
            $table->foreign(['company_id', 'migration_source_id'], 'migration_channel_source_company_fk')
                ->references(['company_id', 'id'])->on('migration_sources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_channel_checkpoints');
        Schema::dropIfExists('migration_reconciliation_checks');
        Schema::dropIfExists('migration_source_records');
        Schema::dropIfExists('migration_sources');
    }
};
