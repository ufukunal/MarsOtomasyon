<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('count_date');
            $table->string('status', 12)->default('draft');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['count_date', 'status']);
            $table->index('location_id');
        });

        DB::connection('period')->statement(
            "ALTER TABLE stock_counts ADD CONSTRAINT stock_counts_status_valid
             CHECK (status IN ('draft','counting','review','posted','cancelled'))"
        );

        Schema::connection('period')->create('stock_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('system_quantity', 18, 3)->default(0);
            $table->decimal('counted_quantity', 18, 3)->nullable();
            $table->decimal('difference', 18, 3)->default(0);
            $table->boolean('is_approved')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['stock_count_id', 'product_id']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE stock_count_lines ADD CONSTRAINT stock_count_lines_counted_nonnegative
             CHECK (counted_quantity IS NULL OR counted_quantity >= 0)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('stock_count_lines');
        Schema::connection('period')->dropIfExists('stock_counts');
    }
};
