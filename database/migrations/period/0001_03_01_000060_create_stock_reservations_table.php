<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('document_type', 40);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_line_id');
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'location_id', 'status']);
            $table->index(['document_type', 'document_id', 'document_line_id']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE stock_reservations
             ADD CONSTRAINT stock_reservations_quantity_positive
             CHECK (quantity > 0)'
        );
        DB::connection('period')->statement(
            "ALTER TABLE stock_reservations
             ADD CONSTRAINT stock_reservations_status_valid
             CHECK (status IN ('active','released','consumed'))"
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('stock_reservations');
    }
};
