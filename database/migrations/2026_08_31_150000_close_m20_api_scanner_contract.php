<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('key_id', 26)->unique();
            $table->string('name', 160);
            $table->char('secret_hash', 64);
            $table->jsonb('permissions');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->index(['company_id', 'revoked_at']);
        });

        Schema::create('api_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('api_access_token_id')->constrained('api_access_tokens')->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->char('request_hash', 64);
            $table->string('status', 24)->default('processing');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['api_access_token_id', 'idempotency_key']);
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('scanner_enrollment_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('key_id', 26)->unique();
            $table->char('secret_hash', 64);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('scanner_agents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('public_id', 26)->unique();
            $table->string('name', 160);
            $table->char('secret_hash', 64);
            $table->jsonb('capabilities')->default('{}');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->index(['company_id', 'revoked_at']);
        });

        Schema::create('scanner_agent_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('scanner_agent_id')->constrained('scanner_agents')->cascadeOnDelete();
            $table->char('public_id', 26)->unique();
            $table->uuid('idempotency_key');
            $table->string('operation', 80);
            $table->jsonb('payload');
            $table->string('status', 24)->default('queued');
            $table->jsonb('result')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['scanner_agent_id', 'idempotency_key']);
            $table->index(['company_id', 'scanner_agent_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_agent_jobs');
        Schema::dropIfExists('scanner_agents');
        Schema::dropIfExists('scanner_enrollment_tokens');
        Schema::dropIfExists('api_idempotency_keys');
        Schema::dropIfExists('api_access_tokens');
    }
};
