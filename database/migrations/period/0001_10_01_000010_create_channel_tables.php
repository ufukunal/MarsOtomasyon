<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('channel_account_period_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('channel_account_id');
            $table->foreignId('marketplace_customer_contact_id')
                ->constrained('contacts')
                ->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique('channel_account_id');
        });

        Schema::connection('period')->create('channel_product_listings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('channel_account_id');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('external_product_id')->nullable();
            $table->string('external_listing_id')->nullable();
            $table->string('external_sku')->nullable();
            $table->string('stock_mode', 20)->nullable();
            $table->decimal('max_channel_quantity', 18, 3)->nullable();
            $table->decimal('withhold_quantity', 18, 3)->default(0);
            $table->decimal('fixed_quantity', 18, 3)->nullable();
            $table->decimal('manual_quantity', 18, 3)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->decimal('price_override', 18, 4)->nullable();
            $table->string('title_override')->nullable();
            $table->text('description_override')->nullable();
            $table->string('image_collection')->nullable();
            $table->jsonb('category_metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['channel_account_id', 'product_id']);
            $table->index(['channel_account_id', 'external_listing_id']);
            $table->index(['channel_account_id', 'is_active']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE channel_product_listings ADD CONSTRAINT channel_product_listings_stock_mode_valid
             CHECK (stock_mode IS NULL OR stock_mode IN ('stock','production','manual'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE channel_product_listings ADD CONSTRAINT channel_product_listings_quantities_valid CHECK (
                (max_channel_quantity IS NULL OR max_channel_quantity >= 0)
                AND withhold_quantity >= 0
                AND (fixed_quantity IS NULL OR fixed_quantity >= 0)
                AND (manual_quantity IS NULL OR manual_quantity >= 0)
                AND (lead_time_days IS NULL OR lead_time_days >= 0)
                AND (price_override IS NULL OR price_override >= 0)
            )'
        );

        Schema::connection('period')->create('channel_listing_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_product_listing_id')
                ->constrained('channel_product_listings')
                ->cascadeOnDelete();
            $table->foreignId('location_id')
                ->constrained('locations')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['channel_product_listing_id', 'location_id']);
        });

        Schema::connection('period')->create('channel_order_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('documents')->restrictOnDelete();
            $table->unsignedBigInteger('channel_account_id');
            $table->string('external_order_id', 120);
            $table->string('external_order_no')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('postcode')->nullable();
            $table->string('cargo_company')->nullable();
            $table->string('cargo_code')->nullable();
            $table->string('external_shipment_id')->nullable();
            $table->string('external_package_id')->nullable();
            $table->jsonb('campaign_metadata')->nullable();
            $table->timestamps();

            $table->unique('sales_order_id');
            $table->unique(['channel_account_id', 'external_order_id']);
        });

        Schema::connection('period')->create('channel_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('channel_account_id');
            $table->string('direction', 10);
            $table->string('entity_type', 30);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('external_id')->nullable();
            $table->string('action', 40);
            $table->string('status', 20);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('correlation_id', 80)->nullable();
            $table->string('payload_hash', 128)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->index(['channel_account_id', 'status', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['correlation_id']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE channel_sync_events ADD CONSTRAINT channel_sync_events_direction_valid
             CHECK (direction IN ('outbound','inbound'))"
        );
        DB::connection('period')->statement(
            "ALTER TABLE channel_sync_events ADD CONSTRAINT channel_sync_events_status_valid
             CHECK (status IN ('queued','processing','success','failed'))"
        );

        Schema::connection('period')->create('channel_sync_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_sync_event_id')
                ->constrained('channel_sync_events')
                ->cascadeOnDelete();
            $table->text('error_summary');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->string('resolved_by_name')->nullable();
            $table->timestamps();

            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('channel_sync_errors');
        Schema::connection('period')->dropIfExists('channel_sync_events');
        Schema::connection('period')->dropIfExists('channel_order_snapshots');
        Schema::connection('period')->dropIfExists('channel_listing_locations');
        Schema::connection('period')->dropIfExists('channel_product_listings');
        Schema::connection('period')->dropIfExists('channel_account_period_settings');
    }
};
