<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->unique(['company_id', 'id'], 'stock_reservations_company_id_id_unique');
        });

        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->string('logical_line_key', 64)->nullable()->after('source_quote_revision_line_id');
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('location_id')->nullable()->after('warehouse_id');

            $table->foreign(['company_id', 'warehouse_id'], 'sales_order_lines_warehouse_fk')
                ->references(['company_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'], 'sales_order_lines_location_fk')
                ->references(['company_id', 'warehouse_id', 'id'])->on('warehouse_locations')->restrictOnDelete();
            $table->unique(['company_id', 'sales_order_id', 'logical_line_key'], 'sales_order_lines_logical_key_unique');
        });

        DB::statement(<<<'SQL'
ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_allocation_shape_check CHECK (
    (warehouse_id IS NULL AND location_id IS NULL)
    OR
    (warehouse_id IS NOT NULL AND location_id IS NOT NULL AND logical_line_key IS NOT NULL)
)
SQL);
        DB::statement("ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_logical_key_check CHECK (logical_line_key IS NULL OR logical_line_key ~ '^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')");

        Schema::create('sales_order_reservation_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sales_order_id');
            $table->string('logical_line_key', 64);
            $table->unsignedInteger('generation');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->decimal('quantity', 20, 6);
            $table->unsignedBigInteger('stock_reservation_id');
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'sales_order_id'], 'sales_order_reservation_generations_order_fk')
                ->references(['company_id', 'id'])->on('sales_orders')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'], 'sales_order_reservation_generations_product_fk')
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'], 'sales_order_reservation_generations_warehouse_fk')
                ->references(['company_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'], 'sales_order_reservation_generations_location_fk')
                ->references(['company_id', 'warehouse_id', 'id'])->on('warehouse_locations')->restrictOnDelete();
            $table->foreign(['company_id', 'stock_reservation_id'], 'sales_order_reservation_generations_reservation_fk')
                ->references(['company_id', 'id'])->on('stock_reservations')->restrictOnDelete();
            $table->unique(
                ['company_id', 'sales_order_id', 'logical_line_key', 'generation'],
                'sales_order_reservation_generations_identity_unique',
            );
            $table->unique(['company_id', 'stock_reservation_id'], 'sales_order_reservation_generations_reservation_unique');
            $table->index(['company_id', 'sales_order_id', 'logical_line_key'], 'sales_order_reservation_generations_line_index');
        });

        DB::statement('ALTER TABLE sales_order_reservation_generations ADD CONSTRAINT sales_order_reservation_generations_generation_check CHECK (generation > 0)');
        DB::statement('ALTER TABLE sales_order_reservation_generations ADD CONSTRAINT sales_order_reservation_generations_quantity_check CHECK (quantity > 0)');
        DB::statement("ALTER TABLE sales_order_reservation_generations ADD CONSTRAINT sales_order_reservation_generations_logical_key_check CHECK (logical_line_key ~ '^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')");
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX sales_order_reservation_generations_active_unique
ON sales_order_reservation_generations (company_id, sales_order_id, logical_line_key)
WHERE released_at IS NULL
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_order_reservation_generation_mutation()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'sales order reservation generations are immutable' USING ERRCODE = '55000';
    END IF;

    IF OLD.released_at IS NOT NULL
       OR NEW.released_at IS NULL
       OR ROW(
            NEW.company_id, NEW.sales_order_id, NEW.logical_line_key, NEW.generation,
            NEW.product_id, NEW.warehouse_id, NEW.location_id, NEW.quantity,
            NEW.stock_reservation_id, NEW.created_at
       ) IS DISTINCT FROM ROW(
            OLD.company_id, OLD.sales_order_id, OLD.logical_line_key, OLD.generation,
            OLD.product_id, OLD.warehouse_id, OLD.location_id, OLD.quantity,
            OLD.stock_reservation_id, OLD.created_at
       ) THEN
        RAISE EXCEPTION 'reservation generation may only transition once to released' USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_order_reservation_generations_immutable
BEFORE UPDATE OR DELETE ON sales_order_reservation_generations
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_order_reservation_generation_mutation();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_order_reservation_generations_immutable ON sales_order_reservation_generations');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_order_reservation_generation_mutation()');
        Schema::dropIfExists('sales_order_reservation_generations');

        DB::statement('ALTER TABLE sales_order_lines DROP CONSTRAINT IF EXISTS sales_order_lines_allocation_shape_check');
        DB::statement('ALTER TABLE sales_order_lines DROP CONSTRAINT IF EXISTS sales_order_lines_logical_key_check');
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->dropUnique('sales_order_lines_logical_key_unique');
            $table->dropForeign('sales_order_lines_location_fk');
            $table->dropForeign('sales_order_lines_warehouse_fk');
            $table->dropColumn(['logical_line_key', 'warehouse_id', 'location_id']);
        });
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->dropUnique('stock_reservations_company_id_id_unique');
        });
    }
};
