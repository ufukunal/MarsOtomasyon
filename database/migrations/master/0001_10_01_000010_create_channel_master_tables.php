<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('sales_channel_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('platform', 30);
            $table->string('name');
            $table->string('external_store_id')->nullable();
            $table->text('credentials_encrypted');
            $table->jsonb('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['company_id', 'platform', 'is_active']);
            $table->index(['company_id', 'external_store_id']);
        });

        DB::connection('master')->statement(
            "ALTER TABLE sales_channel_accounts ADD CONSTRAINT sales_channel_accounts_platform_valid
             CHECK (platform IN ('trendyol','hepsiburada','n11','woocommerce'))"
        );

        Schema::connection('master')->create('channel_external_event_registry', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_account_id')
                ->constrained('sales_channel_accounts')
                ->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->string('external_id', 120);
            $table->unsignedBigInteger('period_id')->nullable();
            $table->unsignedBigInteger('period_document_id')->nullable();
            $table->timestamp('external_occurred_at')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(
                ['channel_account_id', 'event_type', 'external_id'],
                'channel_external_event_unique',
            );
            $table->index(['status', 'updated_at']);
            $table->index(['period_id', 'period_document_id']);
        });

        DB::connection('master')->statement(
            "ALTER TABLE channel_external_event_registry ADD CONSTRAINT channel_external_event_type_valid
             CHECK (event_type IN ('order','cancel','return'))"
        );
        DB::connection('master')->statement(
            "ALTER TABLE channel_external_event_registry ADD CONSTRAINT channel_external_event_status_valid
             CHECK (status IN ('processing','done','failed'))"
        );
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('channel_external_event_registry');
        Schema::connection('master')->dropIfExists('sales_channel_accounts');
    }
};
