<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('target_version', 64);
            $table->string('channel', 24);
            $table->char('manifest_sha256', 64);
            $table->char('package_sha256', 64);
            $table->string('status', 32)->default('requested');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('failure_code', 96)->nullable();
            $table->text('failure_message')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index(['target_version', 'channel']);
            $table->index('requested_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_runs');
    }
};
