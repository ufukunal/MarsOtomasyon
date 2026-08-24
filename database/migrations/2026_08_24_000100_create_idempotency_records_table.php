<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('scope', 100);
            $table->string('idempotency_key', 128);
            $table->char('fingerprint', 64);
            $table->string('status', 20)->default('in_progress');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['scope', 'idempotency_key'], 'idempotency_scope_key_unique');
        });

        DB::statement("ALTER TABLE idempotency_records ADD CONSTRAINT idempotency_status_check CHECK (status IN ('in_progress', 'completed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
