<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_connections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('provider', 64);
            $table->string('label', 160);
            $table->text('credentials_encrypted');
            $table->json('capabilities');
            $table->boolean('is_enabled')->default(true);
            $table->timestampTz('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'provider'], 'shipping_connections_company_provider_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('external_shipment_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('dispatch_id');
            $table->unsignedBigInteger('shipping_connection_id');
            $table->string('provider', 64);
            $table->string('external_shipment_id', 191);
            $table->string('tracking_number', 191)->nullable();
            $table->text('label_reference')->nullable();
            $table->string('status', 64);
            $table->char('request_sha256', 64);
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'dispatch_id', 'provider'], 'external_shipment_dispatch_provider_unique');
            $table->unique(['company_id', 'provider', 'external_shipment_id'], 'external_shipment_provider_identity_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('dispatch_id')->references('id')->on('dispatches')->cascadeOnDelete();
            $table->foreign('shipping_connection_id')->references('id')->on('shipping_connections')->restrictOnDelete();
        });

        Schema::create('shipping_provider_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('dispatch_id');
            $table->string('provider', 64);
            $table->string('operation', 64);
            $table->uuid('idempotency_key');
            $table->char('request_sha256', 64);
            $table->string('status', 24)->default('sending');
            $table->string('external_shipment_id', 191)->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'provider', 'operation', 'idempotency_key'], 'shipping_provider_attempt_identity_unique');
            $table->index(['company_id', 'status', 'created_at'], 'shipping_provider_attempt_status_index');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('dispatch_id')->references('id')->on('dispatches')->cascadeOnDelete();
        });

        Schema::create('shipping_tracking_evidence', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('external_shipment_mapping_id');
            $table->string('provider_status', 64);
            $table->timestampTz('occurred_at')->nullable();
            $table->json('payload');
            $table->char('evidence_sha256', 64);
            $table->timestampTz('recorded_at');
            $table->timestampsTz();

            $table->unique(['external_shipment_mapping_id', 'evidence_sha256'], 'shipping_tracking_evidence_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('external_shipment_mapping_id')->references('id')->on('external_shipment_mappings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_tracking_evidence');
        Schema::dropIfExists('shipping_provider_attempts');
        Schema::dropIfExists('external_shipment_mappings');
        Schema::dropIfExists('shipping_connections');
    }
};
