<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->char('public_id', 26)->nullable();
            $table->string('financial_mode', 24)->default('direct_account');
            $table->unsignedBigInteger('default_account_id')->nullable();
            $table->unsignedBigInteger('clearing_account_id')->nullable();
            $table->string('connection_test_status', 16)->default('untested');
            $table->timestampTz('connection_tested_at')->nullable();
            $table->text('connection_test_message')->nullable();

            $table->foreign('default_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('clearing_account_id')->references('id')->on('accounts')->restrictOnDelete();
        });

        DB::table('integration_connections')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('integration_connections')->where('id', $row->id)->update([
                        'public_id' => (string) Str::ulid(),
                    ]);
                }
            });

        DB::statement('ALTER TABLE integration_connections ALTER COLUMN public_id SET NOT NULL');
        DB::statement("ALTER TABLE integration_connections ADD CONSTRAINT integration_connections_public_id_shape_check CHECK (public_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')");
        DB::statement("ALTER TABLE integration_connections ADD CONSTRAINT integration_connections_financial_mode_check CHECK (financial_mode IN ('direct_account', 'clearing_account'))");
        DB::statement("ALTER TABLE integration_connections ADD CONSTRAINT integration_connections_test_status_check CHECK (connection_test_status IN ('untested', 'ok', 'failed'))");
        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->unique('public_id', 'integration_connections_public_id_unique');
            $table->index(['company_id', 'public_id'], 'integration_connections_company_public_idx');
        });

        Schema::table('integration_sync_effects', function (Blueprint $table): void {
            $table->string('guard_type', 32)->nullable();
            $table->unsignedBigInteger('guard_id')->nullable();
            $table->unsignedBigInteger('guard_version')->nullable();
            $table->text('ignored_reason')->nullable();
            $table->index(['guard_type', 'guard_id', 'guard_version'], 'integration_sync_guard_idx');
        });

        Schema::create('channel_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('external_product_id', 192)->nullable();
            $table->string('external_variant_id', 192)->nullable();
            $table->string('external_sku', 192)->nullable();
            $table->string('status', 16)->default('active');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'connection_id'])
                ->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->unique(['company_id', 'connection_id', 'product_id'], 'channel_product_mapping_local_unique');
            $table->index(['company_id', 'connection_id', 'external_sku'], 'channel_product_mapping_sku_idx');
        });
        DB::statement("ALTER TABLE channel_product_mappings ADD CONSTRAINT channel_product_mappings_status_check CHECK (status IN ('active', 'inactive'))");
        DB::statement("ALTER TABLE channel_product_mappings ADD CONSTRAINT channel_product_mappings_public_id_shape_check CHECK (public_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')");

        Schema::create('channel_listing_states', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->foreignId('mapping_id')->constrained('channel_product_mappings')->restrictOnDelete();
            $table->unsignedBigInteger('desired_version')->default(0);
            $table->decimal('desired_stock', 20, 6)->nullable();
            $table->decimal('desired_price', 20, 6)->nullable();
            $table->char('desired_currency_code', 3)->nullable();
            $table->jsonb('desired_media')->nullable();
            $table->unsignedBigInteger('published_version')->default(0);
            $table->decimal('published_stock', 20, 6)->nullable();
            $table->decimal('published_price', 20, 6)->nullable();
            $table->char('published_currency_code', 3)->nullable();
            $table->jsonb('published_media')->nullable();
            $table->string('status', 16)->default('idle');
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'connection_id'])
                ->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->unique(['company_id', 'connection_id', 'mapping_id'], 'channel_listing_state_mapping_unique');
            $table->index(['company_id', 'status', 'updated_at']);
        });
        DB::statement("ALTER TABLE channel_listing_states ADD CONSTRAINT channel_listing_states_status_check CHECK (status IN ('idle', 'queued', 'synced', 'failed'))");
        DB::statement('ALTER TABLE channel_listing_states ADD CONSTRAINT channel_listing_states_values_check CHECK ((desired_stock IS NULL OR desired_stock >= 0) AND (desired_price IS NULL OR desired_price >= 0) AND (published_stock IS NULL OR published_stock >= 0) AND (published_price IS NULL OR published_price >= 0))');
        DB::statement("ALTER TABLE channel_listing_states ADD CONSTRAINT channel_listing_states_public_id_shape_check CHECK (public_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')");

        Schema::create('channel_order_inbox', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->string('external_order_id', 192);
            $table->string('external_event_id', 160)->nullable();
            $table->char('payload_sha256', 64);
            $table->jsonb('normalized_payload');
            $table->jsonb('customer_snapshot')->nullable();
            $table->string('financial_mode', 24);
            $table->foreignId('account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->restrictOnDelete();
            $table->string('status', 24)->default('received');
            $table->string('problem_code', 64)->nullable();
            $table->text('problem_message')->nullable();
            $table->timestampTz('received_at');
            $table->timestampTz('imported_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'connection_id'])
                ->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->unique(['company_id', 'connection_id', 'external_order_id'], 'channel_order_inbox_external_unique');
            $table->index(['company_id', 'status', 'received_at']);
        });
        DB::statement("ALTER TABLE channel_order_inbox ADD CONSTRAINT channel_order_inbox_status_check CHECK (status IN ('received', 'imported', 'stock_problem', 'failed', 'ignored'))");
        DB::statement("ALTER TABLE channel_order_inbox ADD CONSTRAINT channel_order_inbox_financial_mode_check CHECK (financial_mode IN ('direct_account', 'clearing_account'))");
        DB::statement("ALTER TABLE channel_order_inbox ADD CONSTRAINT channel_order_inbox_public_id_shape_check CHECK (public_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')");

        Schema::create('channel_problems', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->foreignId('order_inbox_id')->nullable()->constrained('channel_order_inbox')->restrictOnDelete();
            $table->foreignId('listing_state_id')->nullable()->constrained('channel_listing_states')->restrictOnDelete();
            $table->string('type', 64);
            $table->string('status', 16)->default('open');
            $table->text('message');
            $table->jsonb('context')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'connection_id'])
                ->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->index(['company_id', 'status', 'type', 'created_at']);
        });
        DB::statement("ALTER TABLE channel_problems ADD CONSTRAINT channel_problems_status_check CHECK (status IN ('open', 'resolved'))");
        DB::statement("ALTER TABLE channel_problems ADD CONSTRAINT channel_problems_public_id_shape_check CHECK (public_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')");

        Schema::create('channel_return_events', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->string('external_return_id', 192);
            $table->string('external_order_id', 192);
            $table->char('payload_sha256', 64);
            $table->jsonb('evidence');
            $table->foreignId('sales_return_id')->nullable()->constrained('sales_returns')->restrictOnDelete();
            $table->string('status', 24)->default('awaiting_invoice');
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'connection_id'])
                ->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->unique(['company_id', 'connection_id', 'external_return_id'], 'channel_return_events_external_unique');
        });
        DB::statement("ALTER TABLE channel_return_events ADD CONSTRAINT channel_return_events_status_check CHECK (status IN ('awaiting_invoice', 'linked', 'ignored', 'failed'))");
        DB::statement("ALTER TABLE channel_return_events ADD CONSTRAINT channel_return_events_public_id_shape_check CHECK (public_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')");

        Schema::create('channel_invoice_syncs', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->restrictOnDelete();
            $table->string('external_order_id', 192);
            $table->string('status', 16)->default('queued');
            $table->unsignedBigInteger('sync_effect_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'connection_id'])
                ->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->foreign('sync_effect_id')->references('id')->on('integration_sync_effects')->restrictOnDelete();
            $table->unique(['company_id', 'connection_id', 'sales_invoice_id'], 'channel_invoice_syncs_invoice_unique');
        });
        DB::statement("ALTER TABLE channel_invoice_syncs ADD CONSTRAINT channel_invoice_syncs_status_check CHECK (status IN ('queued', 'synced', 'failed'))");
        DB::statement("ALTER TABLE channel_invoice_syncs ADD CONSTRAINT channel_invoice_syncs_public_id_shape_check CHECK (public_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')");

        Schema::create('channel_settlement_evidence', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->string('external_settlement_id', 192);
            $table->char('currency_code', 3);
            $table->decimal('gross_amount', 20, 6);
            $table->decimal('fee_amount', 20, 6);
            $table->decimal('net_amount', 20, 6);
            $table->foreignId('clearing_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->timestampTz('occurred_at');
            $table->jsonb('evidence');
            $table->string('status', 16)->default('received');
            $table->timestampTz('handed_off_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'connection_id'])
                ->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->unique(['company_id', 'connection_id', 'external_settlement_id'], 'channel_settlement_evidence_external_unique');
            $table->index(['company_id', 'status', 'occurred_at']);
        });
        DB::statement("ALTER TABLE channel_settlement_evidence ADD CONSTRAINT channel_settlement_evidence_status_check CHECK (status IN ('received', 'handed_off', 'failed'))");
        DB::statement('ALTER TABLE channel_settlement_evidence ADD CONSTRAINT channel_settlement_evidence_amounts_check CHECK (gross_amount >= 0 AND fee_amount >= 0 AND net_amount = gross_amount - fee_amount)');
        DB::statement("ALTER TABLE channel_settlement_evidence ADD CONSTRAINT channel_settlement_evidence_currency_check CHECK (currency_code ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE channel_settlement_evidence ADD CONSTRAINT channel_settlement_evidence_public_id_shape_check CHECK (public_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_settlement_evidence');
        Schema::dropIfExists('channel_invoice_syncs');
        Schema::dropIfExists('channel_return_events');
        Schema::dropIfExists('channel_problems');
        Schema::dropIfExists('channel_order_inbox');
        Schema::dropIfExists('channel_listing_states');
        Schema::dropIfExists('channel_product_mappings');

        Schema::table('integration_sync_effects', function (Blueprint $table): void {
            $table->dropIndex('integration_sync_guard_idx');
            $table->dropColumn(['guard_type', 'guard_id', 'guard_version', 'ignored_reason']);
        });

        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->dropIndex('integration_connections_company_public_idx');
            $table->dropUnique('integration_connections_public_id_unique');
            $table->dropForeign(['default_account_id']);
            $table->dropForeign(['clearing_account_id']);
            $table->dropColumn([
                'public_id',
                'financial_mode',
                'default_account_id',
                'clearing_account_id',
                'connection_test_status',
                'connection_tested_at',
                'connection_test_message',
            ]);
        });
    }
};
