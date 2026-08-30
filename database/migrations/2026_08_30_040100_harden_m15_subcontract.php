<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_direction_check');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_direction_check
            CHECK (
                (movement_type IN (
                    'opening_in', 'adjustment_in', 'transfer_in', 'goods_receipt_in', 'sales_return_in',
                    'production_receipt_in', 'subcontract_receipt_in'
                ) AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                OR
                (movement_type IN (
                    'adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out', 'purchase_return_out',
                    'production_material_out', 'production_loss_out', 'subcontract_send_out'
                ) AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
            )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION mars_prevent_subcontract_append_only_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'subcontract append-only row is immutable';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER subcontract_events_immutable_trigger
            BEFORE UPDATE OR DELETE ON subcontract_events
            FOR EACH ROW EXECUTE FUNCTION mars_prevent_subcontract_append_only_mutation();

            CREATE TRIGGER subcontract_receipts_immutable_trigger
            BEFORE UPDATE OR DELETE ON subcontract_receipts
            FOR EACH ROW EXECUTE FUNCTION mars_prevent_subcontract_append_only_mutation();

            CREATE TRIGGER subcontract_losses_immutable_trigger
            BEFORE UPDATE OR DELETE ON subcontract_losses
            FOR EACH ROW EXECUTE FUNCTION mars_prevent_subcontract_append_only_mutation();

            CREATE OR REPLACE FUNCTION mars_guard_subcontract_order_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'subcontract order deletion is not allowed';
                END IF;
                IF OLD.status = 'completed' THEN
                    RAISE EXCEPTION 'completed subcontract order is immutable';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER subcontract_orders_mutation_guard_trigger
            BEFORE UPDATE OR DELETE ON subcontract_orders
            FOR EACH ROW EXECUTE FUNCTION mars_guard_subcontract_order_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS subcontract_orders_mutation_guard_trigger ON subcontract_orders');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_subcontract_order_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS subcontract_losses_immutable_trigger ON subcontract_losses');
        DB::statement('DROP TRIGGER IF EXISTS subcontract_receipts_immutable_trigger ON subcontract_receipts');
        DB::statement('DROP TRIGGER IF EXISTS subcontract_events_immutable_trigger ON subcontract_events');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_subcontract_append_only_mutation()');

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_direction_check');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_direction_check
            CHECK (
                (movement_type IN (
                    'opening_in', 'adjustment_in', 'transfer_in', 'goods_receipt_in', 'sales_return_in', 'production_receipt_in'
                ) AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                OR
                (movement_type IN (
                    'adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out', 'purchase_return_out',
                    'production_material_out', 'production_loss_out'
                ) AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
            )
            SQL);
    }
};
