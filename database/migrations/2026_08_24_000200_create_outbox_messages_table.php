<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('event_id', 26)->unique();
            $table->string('effect_key', 180)->unique();
            $table->string('event_name', 120);
            $table->smallInteger('schema_version');
            $table->string('semantic_class', 40);
            $table->string('retry_capability', 40);
            $table->jsonb('payload');
            $table->char('effect_fingerprint', 64);
            $table->string('correlation_id', 64);
            $table->bigInteger('company_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->string('source_id', 128)->nullable();
            $table->bigInteger('source_version')->nullable();
            $table->string('status', 20)->default('pending');
            $table->smallInteger('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('occurred_at');
            $table->timestampTz('leased_at')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->string('lease_owner', 100)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'available_at'], 'outbox_status_available_idx');
            $table->index(['company_id', 'status'], 'outbox_company_status_idx');
        });

        DB::statement("ALTER TABLE outbox_messages ADD CONSTRAINT outbox_schema_version_check CHECK (schema_version > 0)");
        DB::statement("ALTER TABLE outbox_messages ADD CONSTRAINT outbox_semantic_class_check CHECK (semantic_class IN ('IMMUTABLE_EVENT_SNAPSHOT', 'CURRENT_DESIRED_STATE'))");
        DB::statement("ALTER TABLE outbox_messages ADD CONSTRAINT outbox_retry_capability_check CHECK (retry_capability IN ('SAFE_RETRY', 'IDEMPOTENT_WITH_KEY', 'QUERY_BEFORE_RETRY', 'NEVER_AUTO_RETRY'))");
        DB::statement("ALTER TABLE outbox_messages ADD CONSTRAINT outbox_status_check CHECK (status IN ('pending', 'leased', 'completed', 'failed'))");
        DB::statement('ALTER TABLE outbox_messages ADD CONSTRAINT outbox_attempts_check CHECK (attempts >= 0)');
        DB::statement('ALTER TABLE outbox_messages ADD CONSTRAINT outbox_company_id_check CHECK (company_id IS NULL OR company_id > 0)');
        DB::statement('ALTER TABLE outbox_messages ADD CONSTRAINT outbox_source_version_check CHECK (source_version IS NULL OR source_version > 0)');
        DB::statement("ALTER TABLE outbox_messages ADD CONSTRAINT outbox_source_pair_check CHECK ((source_type IS NULL AND source_id IS NULL) OR (source_type IS NOT NULL AND source_id IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
