<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('action', 100);
            $table->string('status', 12);
            $table->jsonb('result')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
        });

        DB::connection('master')->statement(
            "ALTER TABLE idempotency_keys
             ADD CONSTRAINT idempotency_keys_status_valid
             CHECK (status IN ('processing','done'))"
        );
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('idempotency_keys');
    }
};
