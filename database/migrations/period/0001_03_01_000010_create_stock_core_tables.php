<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('product_code', 40);

            $table->date('movement_date');
            $table->string('direction', 3);
            $table->string('reason', 30);
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_cost', 18, 4);
            $table->decimal('balance_after', 18, 3);
            $table->decimal('avg_cost_after', 18, 4);

            $table->string('document_type', 40)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('document_no', 40)->nullable();

            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'location_id', 'movement_date']);
            $table->index(['document_type', 'document_id']);
            $table->index(['movement_date', 'reason']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE stock_movements
             ADD CONSTRAINT stock_movements_direction_valid
             CHECK (direction IN ('in','out'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE stock_movements
             ADD CONSTRAINT stock_movements_quantity_positive
             CHECK (quantity > 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE stock_movements
             ADD CONSTRAINT stock_movements_unit_cost_nonnegative
             CHECK (unit_cost >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE stock_movements
             ADD CONSTRAINT stock_movements_total_cost_valid
             CHECK (total_cost = trunc(quantity * unit_cost, 4))'
        );
        DB::connection('period')->statement(
            'ALTER TABLE stock_movements
             ADD CONSTRAINT stock_movements_avg_cost_nonnegative
             CHECK (avg_cost_after >= 0)'
        );

        Schema::connection('period')->create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();

            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('reserved', 18, 3)->default(0);
            $table->decimal('consignment_reserved', 18, 3)->default(0);
            $table->decimal('quarantine', 18, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'location_id'], 'stock_balances_unique');
            $table->index('location_id');
        });

        DB::connection('period')->statement(
            'ALTER TABLE stock_balances
             ADD CONSTRAINT stock_balances_reserved_nonnegative
             CHECK (reserved >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE stock_balances
             ADD CONSTRAINT stock_balances_consignment_nonnegative
             CHECK (consignment_reserved >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE stock_balances
             ADD CONSTRAINT stock_balances_quarantine_nonnegative
             CHECK (quarantine >= 0)'
        );

        Schema::connection('period')->create('product_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('last_purchase_price', 18, 4)->default(0);
            $table->decimal('moving_average', 18, 4)->default(0);
            $table->decimal('import_cost', 18, 4)->default(0);
            $table->decimal('production_cost', 18, 4)->default(0);
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamps();

            $table->unique('product_id');
        });

        DB::connection('period')->statement(
            'ALTER TABLE product_costs
             ADD CONSTRAINT product_costs_values_nonnegative
             CHECK (
                 last_purchase_price >= 0
                 AND moving_average >= 0
                 AND import_cost >= 0
                 AND production_cost >= 0
             )'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('product_costs');
        Schema::connection('period')->dropIfExists('stock_balances');
        Schema::connection('period')->dropIfExists('stock_movements');
    }
};
