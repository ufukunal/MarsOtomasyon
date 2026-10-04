<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('purchase_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_invoice_line_id')->unique()->constrained('document_lines')->restrictOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained('document_lines')->restrictOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained('document_lines')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->decimal('matched_quantity', 18, 3);
            $table->decimal('order_unit_price', 18, 4);
            $table->decimal('invoice_unit_price', 18, 4);
            $table->decimal('price_variance_rate', 9, 4)->default(0);
            $table->decimal('cost_unit_try', 18, 4)->nullable();
            $table->decimal('previous_moving_average', 18, 4)->nullable();
            $table->decimal('previous_last_purchase_price', 18, 4)->nullable();
            $table->decimal('cost_value_delta', 18, 4)->nullable();
            $table->decimal('new_moving_average', 18, 4)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index('purchase_order_line_id');
            $table->index('goods_receipt_line_id');
            $table->index(['product_id', 'created_at']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE purchase_matches ADD CONSTRAINT purchase_matches_quantity_positive CHECK (matched_quantity > 0)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('purchase_matches');
    }
};
