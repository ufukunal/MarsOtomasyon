<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('barcode', 40)->nullable()->index();
            $table->decimal('vat_rate', 7, 4)->default(20);
            $table->decimal('list_price', 18, 4)->default(0);
            $table->char('currency', 3)->default('TRY');
            $table->string('kind', 12)->default('normal');
            $table->foreignId('variant_group_id')->nullable()->constrained('variant_groups')->nullOnDelete();
            $table->boolean('allow_negative_stock')->default(false);
            $table->decimal('min_stock', 18, 3)->default(0);
            $table->string('channel_stock_mode', 20)->default('stock');
            $table->unsignedBigInteger('source_company_id')->nullable();
            $table->unsignedBigInteger('source_record_id')->nullable();
            $table->string('search_index')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index('source_company_id');
        });

        DB::connection('period')->statement(
            "ALTER TABLE products ADD CONSTRAINT products_kind_valid CHECK (kind IN ('normal','set','configurable'))"
        );
        DB::connection('period')->statement(
            "ALTER TABLE products ADD CONSTRAINT products_channel_stock_mode_valid CHECK (channel_stock_mode IN ('stock','production','manual'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE products ADD CONSTRAINT products_vat_rate_valid CHECK (vat_rate >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE products ADD CONSTRAINT products_list_price_nonnegative CHECK (list_price >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE products ADD CONSTRAINT products_min_stock_nonnegative CHECK (min_stock >= 0)'
        );
        DB::connection('period')->statement(
            'CREATE INDEX products_search_index_trgm ON products USING gin (search_index gin_trgm_ops)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('products');
    }
};
