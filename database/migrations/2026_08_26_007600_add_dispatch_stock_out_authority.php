<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_direction_check');
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_type_check
CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'dispatch_out'))
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_direction_check
CHECK (
    (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
    OR
    (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_dispatch_source_check
CHECK (
    movement_type <> 'dispatch_out'
    OR (
        source_type = 'dispatch_line'
        AND effect_type = 'stock.out'
        AND source_id ~ '^[1-9][0-9]*$'
    )
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_dispatch_stock_out()
RETURNS trigger AS $$
DECLARE
    source_line dispatch_lines%ROWTYPE;
BEGIN
    IF NEW.movement_type <> 'dispatch_out' THEN
        RETURN NEW;
    END IF;

    SELECT * INTO source_line
    FROM dispatch_lines
    WHERE id = CAST(NEW.source_id AS bigint)
      AND company_id = NEW.company_id
    FOR SHARE;

    IF NOT FOUND
       OR source_line.product_id <> NEW.product_id
       OR source_line.warehouse_id <> NEW.warehouse_id
       OR source_line.location_id <> NEW.location_id
       OR NEW.quantity_delta <> -source_line.quantity THEN
        RAISE EXCEPTION 'dispatch_out stock movement must exactly match its dispatch line' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER stock_movements_dispatch_out_guard
BEFORE INSERT ON stock_movements
FOR EACH ROW EXECUTE FUNCTION mars_guard_dispatch_stock_out();

CREATE OR REPLACE FUNCTION mars_guard_dispatch_line_after_stock_out()
RETURNS trigger AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM stock_movements
        WHERE company_id = OLD.company_id
          AND source_type = 'dispatch_line'
          AND source_id = OLD.id::text
          AND effect_type = 'stock.out'
          AND movement_type = 'dispatch_out'
    ) THEN
        RAISE EXCEPTION 'dispatch line is immutable after stock out' USING ERRCODE = '55000';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER dispatch_lines_stock_out_guard
BEFORE UPDATE OR DELETE ON dispatch_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_dispatch_line_after_stock_out();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS dispatch_lines_stock_out_guard ON dispatch_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_dispatch_line_after_stock_out()');
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_dispatch_out_guard ON stock_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_dispatch_stock_out()');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_dispatch_source_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_direction_check');

        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM stock_movements WHERE movement_type = 'dispatch_out') THEN
        ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_type_check
            CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'dispatch_out'));
        ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_direction_check
            CHECK (
                (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                OR
                (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
            );
    ELSE
        ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_type_check
            CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out'));
        ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_direction_check
            CHECK (
                (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                OR
                (movement_type IN ('adjustment_out', 'transfer_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
            );
    END IF;
END $$;
SQL);
    }
};
