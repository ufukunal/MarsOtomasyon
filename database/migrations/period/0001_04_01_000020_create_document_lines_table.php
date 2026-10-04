<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('document_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->string('line_kind', 20)->default('stock');
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('conversion_factor', 18, 6)->nullable();
            $table->decimal('base_quantity', 18, 3)->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->decimal('unit_price', 18, 4);
            $table->decimal('line_discount_rate', 7, 4)->default(0);
            $table->decimal('line_discount_amount', 18, 4)->default(0);
            $table->decimal('vat_rate', 7, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->boolean('reserve_stock')->default(false);
            $table->decimal('cancelled_quantity', 18, 3)->default(0);
            $table->jsonb('configuration')->nullable();
            $table->foreignId('source_line_id')->nullable()->constrained('document_lines')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['document_id', 'line_no']);
            $table->index('source_line_id');
        });

        DB::connection('period')->statement(
            "ALTER TABLE document_lines ADD CONSTRAINT document_lines_kind_valid CHECK (line_kind IN ('stock','service'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE document_lines ADD CONSTRAINT document_lines_quantity_positive CHECK (quantity > 0)'
        );
        DB::connection('period')->statement(
            "ALTER TABLE document_lines ADD CONSTRAINT document_lines_stock_shape_valid CHECK (
                (line_kind = 'stock' AND product_id IS NOT NULL AND unit_id IS NOT NULL AND conversion_factor > 0 AND base_quantity > 0)
                OR
                (line_kind = 'service' AND conversion_factor IS NULL AND base_quantity IS NULL AND location_id IS NULL)
            )"
        );
        DB::connection('period')->statement(
            'ALTER TABLE document_lines ADD CONSTRAINT document_lines_money_valid CHECK (
                unit_price >= 0 AND line_discount_rate BETWEEN 0 AND 100 AND line_discount_amount >= 0 AND vat_rate >= 0
            )'
        );
        DB::connection('period')->statement(
            'ALTER TABLE document_lines ADD CONSTRAINT document_lines_cancelled_valid CHECK (cancelled_quantity >= 0 AND cancelled_quantity <= quantity)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE document_lines ADD CONSTRAINT document_lines_no_self_source CHECK (source_line_id IS NULL OR source_line_id <> id)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('document_lines');
    }
};
