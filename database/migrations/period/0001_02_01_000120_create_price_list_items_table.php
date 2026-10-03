<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('price', 18, 4);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                ['price_list_id', 'product_id', 'valid_from'],
                'price_list_items_unique',
            );
        });

        DB::connection('period')->statement(
            'ALTER TABLE price_list_items ADD CONSTRAINT price_list_items_price_nonnegative CHECK (price >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE price_list_items ADD CONSTRAINT price_list_items_date_order CHECK (valid_to IS NULL OR valid_from IS NULL OR valid_to >= valid_from)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('price_list_items');
    }
};
