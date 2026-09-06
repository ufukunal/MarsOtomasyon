<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_client_operations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('client_id', 80);
            $table->uuid('operation_id');
            $table->string('operation_type', 80);
            $table->char('request_sha256', 64);
            $table->string('status', 24)->default('processing');
            $table->json('response_payload')->nullable();
            $table->timestampTz('completed_at', 3)->nullable();
            $table->timestampsTz(3);

            $table->unique(['company_id', 'client_id', 'operation_id'], 'mobile_client_operations_identity_unique');
            $table->index(['company_id', 'operation_type', 'created_at'], 'mobile_client_operations_type_index');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_client_operations');
    }
};
