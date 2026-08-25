<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'warehouses_company_id_id_unique');
            $table->index(['company_id', 'is_active'], 'warehouses_company_active_index');
        });

        DB::statement('CREATE UNIQUE INDEX warehouses_company_code_lower_unique ON warehouses (company_id, lower(code))');
        DB::statement('ALTER TABLE warehouses ADD CONSTRAINT warehouses_code_not_blank_check CHECK (char_length(btrim(code)) > 0)');
        DB::statement('ALTER TABLE warehouses ADD CONSTRAINT warehouses_name_not_blank_check CHECK (char_length(btrim(name)) > 0)');

        Schema::create('warehouse_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('warehouse_id');
            $table->string('code', 64);
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'warehouse_id', 'id'], 'warehouse_locations_company_warehouse_id_unique');
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])
                ->on('warehouses')
                ->cascadeOnDelete();
            $table->index(['company_id', 'warehouse_id', 'is_active'], 'warehouse_locations_company_warehouse_active_index');
        });

        DB::statement('CREATE UNIQUE INDEX warehouse_locations_warehouse_code_lower_unique ON warehouse_locations (company_id, warehouse_id, lower(code))');
        DB::statement('ALTER TABLE warehouse_locations ADD CONSTRAINT warehouse_locations_code_not_blank_check CHECK (char_length(btrim(code)) > 0)');
        DB::statement('ALTER TABLE warehouse_locations ADD CONSTRAINT warehouse_locations_name_not_blank_check CHECK (char_length(btrim(name)) > 0)');

        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->decimal('quantity', 20, 6)->default(0);
            $table->decimal('average_unit_cost', 20, 6)->default(0);
            $table->decimal('inventory_value', 20, 6)->default(0);
            $table->timestampsTz();

            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])
                ->on('warehouses')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])
                ->on('warehouse_locations')
                ->restrictOnDelete();
            $table->unique(['company_id', 'product_id', 'warehouse_id', 'location_id'], 'stock_balances_scope_unique');
            $table->index(['company_id', 'warehouse_id', 'product_id'], 'stock_balances_company_warehouse_product_index');
        });

        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_quantity_non_negative_check CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_average_cost_non_negative_check CHECK (average_unit_cost >= 0)');
        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_inventory_value_non_negative_check CHECK (inventory_value >= 0)');
        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_zero_quantity_value_check CHECK (quantity <> 0 OR (average_unit_cost = 0 AND inventory_value = 0))');

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('operation_key', 64);
            $table->string('request_fingerprint', 64);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->string('movement_type', 32);
            $table->decimal('quantity_delta', 20, 6);
            $table->decimal('unit_cost', 20, 6);
            $table->decimal('value_delta', 20, 6);
            $table->decimal('balance_quantity_after', 20, 6);
            $table->decimal('average_unit_cost_after', 20, 6);
            $table->decimal('inventory_value_after', 20, 6);
            $table->string('note', 240)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])
                ->on('warehouses')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])
                ->on('warehouse_locations')
                ->restrictOnDelete();
            $table->unique(['company_id', 'operation_key'], 'stock_movements_company_operation_unique');
            $table->index(['company_id', 'product_id', 'occurred_at'], 'stock_movements_company_product_occurred_index');
            $table->index(['company_id', 'warehouse_id', 'location_id', 'occurred_at'], 'stock_movements_scope_occurred_index');
        });

        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out'))");
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_non_zero_check CHECK (quantity_delta <> 0)');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_direction_check CHECK ((movement_type IN ('opening_in', 'adjustment_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0) OR (movement_type = 'adjustment_out' AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0))");
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_balance_after_non_negative_check CHECK (balance_quantity_after >= 0 AND average_unit_cost_after >= 0 AND inventory_value_after >= 0)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION mars_prevent_stock_movement_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'stock_movements is append-only; use an explicit reversal or adjustment';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER stock_movements_immutable_trigger
            BEFORE UPDATE OR DELETE ON stock_movements
            FOR EACH ROW EXECUTE FUNCTION mars_prevent_stock_movement_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_immutable_trigger ON stock_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_stock_movement_mutation()');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('warehouses');
    }
};
