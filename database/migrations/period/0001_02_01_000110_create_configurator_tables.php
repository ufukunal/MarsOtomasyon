<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('config_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        Schema::connection('period')->create('config_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('config_definition_id')->constrained('config_definitions')->cascadeOnDelete();
            $table->foreignId('component_product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        DB::connection('period')->statement(
            'CREATE UNIQUE INDEX config_options_one_default
             ON config_options (config_definition_id) WHERE is_default = true'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('config_options');
        Schema::connection('period')->dropIfExists('config_definitions');
    }
};
