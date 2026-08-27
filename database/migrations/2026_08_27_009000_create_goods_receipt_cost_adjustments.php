<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_cost_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('goods_receipt_line_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->string('reference', 64);
            $table->decimal('total_value_delta', 20, 6);
            $table->decimal('eligible_quantity', 20, 6);
            $table->decimal('on_hand_quantity_basis', 20, 6);
            $table->decimal('consumed_quantity_basis', 20, 6);
            $table->decimal('inventory_value_delta', 20, 6);
            $table->decimal('consumed_cost_delta', 20, 6);
            $table->decimal('balance_quantity_after', 20, 6);
            $table->decimal('average_unit_cost_after', 20, 6);
            $table->decimal('inventory_value_after', 20, 6);
            $table->string('note', 1000)->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'goods_receipt_id'])
                ->references(['company_id', 'id'])->on('goods_receipts')->restrictOnDelete();
            $table->foreign(
                ['company_id', 'goods_receipt_id', 'goods_receipt_line_id'],
                'goods_receipt_cost_adjustments_line_fk',
            )->references(['company_id', 'goods_receipt_id', 'id'])->on('goods_receipt_lines')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])->on('warehouse_locations')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['company_id', 'goods_receipt_line_id', 'reference'], 'goods_receipt_cost_adjustments_reference_unique');
            $table->index(['company_id', 'goods_receipt_id', 'occurred_at'], 'goods_receipt_cost_adjustments_receipt_index');
        });

        DB::statement("ALTER TABLE goods_receipt_cost_adjustments ADD CONSTRAINT goods_receipt_cost_adjustments_reference_check CHECK (reference = btrim(reference) AND reference <> '')");
        DB::statement('ALTER TABLE goods_receipt_cost_adjustments ADD CONSTRAINT goods_receipt_cost_adjustments_total_nonzero_check CHECK (total_value_delta <> 0)');
        DB::statement('ALTER TABLE goods_receipt_cost_adjustments ADD CONSTRAINT goods_receipt_cost_adjustments_quantity_check CHECK (eligible_quantity > 0 AND on_hand_quantity_basis >= 0 AND consumed_quantity_basis >= 0 AND eligible_quantity = on_hand_quantity_basis + consumed_quantity_basis)');
        DB::statement('ALTER TABLE goods_receipt_cost_adjustments ADD CONSTRAINT goods_receipt_cost_adjustments_value_split_check CHECK (total_value_delta = inventory_value_delta + consumed_cost_delta)');
        DB::statement('ALTER TABLE goods_receipt_cost_adjustments ADD CONSTRAINT goods_receipt_cost_adjustments_balance_check CHECK (balance_quantity_after >= 0 AND average_unit_cost_after >= 0 AND inventory_value_after >= 0)');
        DB::statement('ALTER TABLE goods_receipt_cost_adjustments ADD CONSTRAINT goods_receipt_cost_adjustments_zero_balance_check CHECK (balance_quantity_after <> 0 OR (average_unit_cost_after = 0 AND inventory_value_after = 0))');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_prevent_goods_receipt_cost_adjustment_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'goods_receipt_cost_adjustments is append-only' USING ERRCODE = '55000';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER goods_receipt_cost_adjustments_immutable_trigger
BEFORE UPDATE OR DELETE ON goods_receipt_cost_adjustments
FOR EACH ROW EXECUTE FUNCTION mars_prevent_goods_receipt_cost_adjustment_mutation();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS goods_receipt_cost_adjustments_immutable_trigger ON goods_receipt_cost_adjustments');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_goods_receipt_cost_adjustment_mutation()');
        Schema::dropIfExists('goods_receipt_cost_adjustments');
    }
};
