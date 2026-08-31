<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('brand', 160)->nullable();
            $table->index(['company_id', 'brand'], 'products_company_brand_index');
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_brand_shape_check CHECK (brand IS NULL OR (brand = btrim(brand) AND char_length(brand) > 0))');

        Schema::create('account_b2b_product_visibilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id');
            $table->foreignId('account_id');
            $table->foreignId('product_id');
            $table->boolean('is_visible')->default(true);
            $table->timestampsTz();

            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])
                ->on('accounts')
                ->cascadeOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->unique(['company_id', 'account_id', 'product_id'], 'account_b2b_product_visibility_unique');
            $table->index(['company_id', 'account_id', 'is_visible'], 'account_b2b_product_visibility_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_b2b_product_visibilities');

        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_brand_shape_check');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_company_brand_index');
            $table->dropColumn('brand');
        });
    }
};
