<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('variant_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_group_id')->constrained('variant_groups')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['variant_group_id', 'name']);
        });

        Schema::connection('period')->create('product_variant_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_attribute_id')->constrained('variant_attributes')->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['product_id', 'variant_attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('product_variant_values');
        Schema::connection('period')->dropIfExists('variant_attributes');
    }
};
