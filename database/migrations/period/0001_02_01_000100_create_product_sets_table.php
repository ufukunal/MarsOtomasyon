<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('product_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('set_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['set_product_id', 'component_product_id']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE product_sets ADD CONSTRAINT product_sets_positive_quantity CHECK (quantity > 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE product_sets ADD CONSTRAINT product_sets_distinct_product CHECK (set_product_id <> component_product_id)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('product_sets');
    }
};
