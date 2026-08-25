<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxes', function (Blueprint $table): void {
            $table->unique(['company_id', 'id'], 'taxes_company_id_id_unique');
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'categories_company_id_id_unique');
            $table->index(['company_id', 'is_active'], 'categories_company_active_index');
        });

        DB::statement('CREATE UNIQUE INDEX categories_company_code_lower_unique ON categories (company_id, lower(code))');
        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_code_not_blank_check CHECK (char_length(btrim(code)) > 0)');
        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_name_not_blank_check CHECK (char_length(btrim(name)) > 0)');

        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'units_company_id_id_unique');
            $table->index(['company_id', 'is_active'], 'units_company_active_index');
        });

        DB::statement('CREATE UNIQUE INDEX units_company_code_lower_unique ON units (company_id, lower(code))');
        DB::statement('ALTER TABLE units ADD CONSTRAINT units_code_not_blank_check CHECK (char_length(btrim(code)) > 0)');
        DB::statement('ALTER TABLE units ADD CONSTRAINT units_name_not_blank_check CHECK (char_length(btrim(name)) > 0)');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('status', 16)->default('active');
            $table->string('name', 200);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('tax_id');
            $table->decimal('sale_price_net', 20, 6)->default(0);
            $table->decimal('purchase_price_net', 20, 6)->default(0);
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'products_company_id_id_unique');
            $table->foreign(['company_id', 'category_id'])
                ->references(['company_id', 'id'])
                ->on('categories')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'unit_id'])
                ->references(['company_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'tax_id'])
                ->references(['company_id', 'id'])
                ->on('taxes')
                ->restrictOnDelete();

            $table->index(['company_id', 'status'], 'products_company_status_index');
            $table->index(['company_id', 'category_id'], 'products_company_category_index');
            $table->index(['company_id', 'name'], 'products_company_name_index');
        });

        DB::statement('CREATE UNIQUE INDEX products_company_code_lower_unique ON products (company_id, lower(code))');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('active', 'inactive'))");
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_code_not_blank_check CHECK (char_length(btrim(code)) > 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_name_not_blank_check CHECK (char_length(btrim(name)) > 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_sale_price_net_check CHECK (sale_price_net >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_purchase_price_net_check CHECK (purchase_price_net >= 0)');

        Schema::create('barcodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('barcode', 128);
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->unique(['company_id', 'barcode'], 'barcodes_company_barcode_unique');
            $table->index(['company_id', 'product_id'], 'barcodes_company_product_index');
        });

        DB::statement('CREATE UNIQUE INDEX barcodes_product_primary_unique ON barcodes (company_id, product_id) WHERE is_primary = true');
        DB::statement('ALTER TABLE barcodes ADD CONSTRAINT barcodes_value_not_blank_check CHECK (char_length(btrim(barcode)) > 0 AND barcode = btrim(barcode))');
    }

    public function down(): void
    {
        Schema::dropIfExists('barcodes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');

        Schema::table('taxes', function (Blueprint $table): void {
            $table->dropUnique('taxes_company_id_id_unique');
        });
    }
};
