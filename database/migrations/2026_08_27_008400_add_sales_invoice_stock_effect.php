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
CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'dispatch_out', 'invoice_out'))
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_direction_check
CHECK (
    (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
    OR
    (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_invoice_source_check
CHECK (
    movement_type <> 'invoice_out'
    OR (
        source_type = 'sales_invoice_line'
        AND effect_type = 'stock.out'
        AND source_id ~ '^[1-9][0-9]*$'
        AND reversal_of_movement_id IS NULL
    )
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_stock_out()
RETURNS trigger AS $$
DECLARE
    source_line sales_invoice_lines%ROWTYPE;
    parent_mode text;
    parent_status text;
BEGIN
    IF NEW.movement_type <> 'invoice_out' THEN
        RETURN NEW;
    END IF;

    SELECT * INTO source_line
    FROM sales_invoice_lines
    WHERE id = CAST(NEW.source_id AS bigint)
      AND company_id = NEW.company_id
    FOR SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'invoice_out source sales invoice line not found' USING ERRCODE = '23514';
    END IF;

    SELECT mode, status INTO parent_mode, parent_status
    FROM sales_invoices
    WHERE company_id = source_line.company_id
      AND id = source_line.sales_invoice_id
    FOR SHARE;

    IF parent_status IS DISTINCT FROM 'draft'
       OR parent_mode NOT IN ('direct', 'order_linked')
       OR source_line.product_id <> NEW.product_id
       OR source_line.warehouse_id <> NEW.warehouse_id
       OR source_line.location_id <> NEW.location_id
       OR NEW.quantity_delta <> -source_line.quantity THEN
        RAISE EXCEPTION 'invoice_out stock movement must exactly match a draft direct/order sales invoice line' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER stock_movements_invoice_out_guard
BEFORE INSERT ON stock_movements
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_stock_out();

CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_dispatch_line_capacity()
RETURNS trigger AS $$
DECLARE
    dispatched_quantity numeric(20, 6);
    committed_quantity numeric(20, 6);
BEGIN
    IF NEW.source_dispatch_line_id IS NULL THEN
        RETURN NEW;
    END IF;

    SELECT quantity INTO dispatched_quantity
    FROM dispatch_lines
    WHERE company_id = NEW.company_id
      AND id = NEW.source_dispatch_line_id
      AND dispatch_id = NEW.source_dispatch_id
    FOR UPDATE;

    IF dispatched_quantity IS NULL THEN
        RAISE EXCEPTION 'sales invoice dispatch source line not found' USING ERRCODE = '23514';
    END IF;

    IF TG_OP = 'UPDATE' THEN
        SELECT COALESCE(SUM(line.quantity), 0)
        INTO committed_quantity
        FROM sales_invoice_lines AS line
        INNER JOIN sales_invoices AS invoice
          ON invoice.company_id = line.company_id
         AND invoice.id = line.sales_invoice_id
        WHERE line.company_id = NEW.company_id
          AND line.source_dispatch_line_id = NEW.source_dispatch_line_id
          AND line.id <> OLD.id
          AND invoice.status <> 'cancelled';
    ELSE
        SELECT COALESCE(SUM(line.quantity), 0)
        INTO committed_quantity
        FROM sales_invoice_lines AS line
        INNER JOIN sales_invoices AS invoice
          ON invoice.company_id = line.company_id
         AND invoice.id = line.sales_invoice_id
        WHERE line.company_id = NEW.company_id
          AND line.source_dispatch_line_id = NEW.source_dispatch_line_id
          AND invoice.status <> 'cancelled';
    END IF;

    IF committed_quantity + NEW.quantity > dispatched_quantity THEN
        RAISE EXCEPTION 'sales invoice quantity exceeds source dispatch line quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_invoice_dispatch_line_capacity_guard
BEFORE INSERT OR UPDATE ON sales_invoice_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_dispatch_line_capacity();

CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_stock_lifecycle_commit()
RETURNS trigger AS $$
DECLARE
    current_status text;
    current_mode text;
BEGIN
    SELECT status, mode INTO current_status, current_mode
    FROM sales_invoices
    WHERE company_id = NEW.company_id AND id = NEW.id;

    IF current_status NOT IN ('finalized', 'cancelled') THEN
        RETURN NEW;
    END IF;

    IF current_mode IN ('direct', 'order_linked') THEN
        IF EXISTS (
            SELECT 1
            FROM sales_invoice_lines AS line
            WHERE line.company_id = NEW.company_id
              AND line.sales_invoice_id = NEW.id
              AND NOT EXISTS (
                  SELECT 1
                  FROM stock_movements AS movement
                  WHERE movement.company_id = line.company_id
                    AND movement.source_type = 'sales_invoice_line'
                    AND movement.source_id = line.id::text
                    AND movement.effect_type = 'stock.out'
                    AND movement.movement_type = 'invoice_out'
                    AND movement.reversal_of_movement_id IS NULL
                    AND movement.product_id = line.product_id
                    AND movement.warehouse_id = line.warehouse_id
                    AND movement.location_id = line.location_id
                    AND movement.quantity_delta = -line.quantity
              )
        ) THEN
            RAISE EXCEPTION 'direct/order sales invoice finalization requires exact invoice stock out for every line' USING ERRCODE = '23514';
        END IF;

        IF current_status = 'cancelled' AND EXISTS (
            SELECT 1
            FROM sales_invoice_lines AS line
            WHERE line.company_id = NEW.company_id
              AND line.sales_invoice_id = NEW.id
              AND NOT EXISTS (
                  SELECT 1
                  FROM stock_movements AS original
                  INNER JOIN stock_movements AS reversal
                    ON reversal.reversal_of_movement_id = original.id
                  WHERE original.company_id = line.company_id
                    AND original.source_type = 'sales_invoice_line'
                    AND original.source_id = line.id::text
                    AND original.effect_type = 'stock.out'
                    AND original.movement_type = 'invoice_out'
                    AND original.reversal_of_movement_id IS NULL
                    AND original.quantity_delta = -line.quantity
                    AND reversal.company_id = line.company_id
                    AND reversal.source_type = 'sales_invoice_line'
                    AND reversal.source_id = line.id::text
                    AND reversal.effect_type = 'stock.out.reverse'
                    AND reversal.movement_type = 'adjustment_in'
                    AND reversal.quantity_delta = line.quantity
              )
        ) THEN
            RAISE EXCEPTION 'direct/order sales invoice cancellation requires exact invoice stock reversals' USING ERRCODE = '23514';
        END IF;
    ELSE
        IF EXISTS (
            SELECT 1
            FROM sales_invoice_lines AS line
            INNER JOIN stock_movements AS movement
              ON movement.company_id = line.company_id
             AND movement.source_type = 'sales_invoice_line'
             AND movement.source_id = line.id::text
             AND movement.effect_type = 'stock.out'
            WHERE line.company_id = NEW.company_id
              AND line.sales_invoice_id = NEW.id
        ) THEN
            RAISE EXCEPTION 'dispatch-linked sales invoice cannot create a second stock out' USING ERRCODE = '23514';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM sales_invoice_lines AS line
            INNER JOIN dispatch_lines AS dispatch_line
              ON dispatch_line.company_id = line.company_id
             AND dispatch_line.dispatch_id = line.source_dispatch_id
             AND dispatch_line.id = line.source_dispatch_line_id
            WHERE line.company_id = NEW.company_id
              AND line.sales_invoice_id = NEW.id
              AND NOT EXISTS (
                  SELECT 1
                  FROM stock_movements AS movement
                  WHERE movement.company_id = dispatch_line.company_id
                    AND movement.source_type = 'dispatch_line'
                    AND movement.source_id = dispatch_line.id::text
                    AND movement.effect_type = 'stock.out'
                    AND movement.movement_type = 'dispatch_out'
                    AND movement.reversal_of_movement_id IS NULL
                    AND movement.product_id = dispatch_line.product_id
                    AND movement.warehouse_id = dispatch_line.warehouse_id
                    AND movement.location_id = dispatch_line.location_id
                    AND movement.quantity_delta = -dispatch_line.quantity
              )
        ) THEN
            RAISE EXCEPTION 'dispatch-linked sales invoice requires the existing exact dispatch stock out' USING ERRCODE = '23514';
        END IF;
    END IF;

    IF current_mode = 'order_linked' THEN
        IF EXISTS (
            SELECT 1
            FROM sales_invoice_lines AS line
            WHERE line.company_id = NEW.company_id
              AND line.sales_invoice_id = NEW.id
              AND NOT EXISTS (
                  SELECT 1
                  FROM sales_order_line_progress_effects AS effect
                  WHERE effect.company_id = line.company_id
                    AND effect.sales_order_id = line.source_sales_order_id
                    AND effect.sales_order_line_id = line.source_sales_order_line_id
                    AND effect.progress_type = 'dispatched'
                    AND effect.quantity_delta = line.quantity
                    AND effect.reversal_of_progress_effect_id IS NULL
                    AND effect.source_type = 'sales_invoice_line'
                    AND effect.source_id = line.id::text
                    AND effect.effect_type = 'progress.dispatch'
              )
        ) THEN
            RAISE EXCEPTION 'order-linked sales invoice finalization requires exact fulfillment progress' USING ERRCODE = '23514';
        END IF;

        IF current_status = 'cancelled' AND EXISTS (
            SELECT 1
            FROM sales_invoice_lines AS line
            WHERE line.company_id = NEW.company_id
              AND line.sales_invoice_id = NEW.id
              AND NOT EXISTS (
                  SELECT 1
                  FROM sales_order_line_progress_effects AS original
                  INNER JOIN sales_order_line_progress_effects AS reversal
                    ON reversal.reversal_of_progress_effect_id = original.id
                  WHERE original.company_id = line.company_id
                    AND original.sales_order_id = line.source_sales_order_id
                    AND original.sales_order_line_id = line.source_sales_order_line_id
                    AND original.progress_type = 'dispatched'
                    AND original.quantity_delta = line.quantity
                    AND original.reversal_of_progress_effect_id IS NULL
                    AND original.source_type = 'sales_invoice_line'
                    AND original.source_id = line.id::text
                    AND original.effect_type = 'progress.dispatch'
                    AND reversal.company_id = line.company_id
                    AND reversal.sales_order_id = line.source_sales_order_id
                    AND reversal.sales_order_line_id = line.source_sales_order_line_id
                    AND reversal.progress_type = 'dispatched'
                    AND reversal.quantity_delta = -line.quantity
                    AND reversal.source_type = 'sales_invoice_line'
                    AND reversal.source_id = line.id::text
                    AND reversal.effect_type = 'progress.dispatch.reverse'
              )
        ) THEN
            RAISE EXCEPTION 'order-linked sales invoice cancellation requires exact fulfillment progress reversal' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER sales_invoice_stock_lifecycle_commit_guard
AFTER UPDATE ON sales_invoices
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_stock_lifecycle_commit();

CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_stock_effect_commit()
RETURNS trigger AS $$
DECLARE
    parent_status text;
    parent_mode text;
    invoice_line_id bigint;
BEGIN
    IF NEW.source_type <> 'sales_invoice_line'
       OR NEW.effect_type NOT IN ('stock.out', 'stock.out.reverse') THEN
        RETURN NEW;
    END IF;

    IF NEW.source_id !~ '^[1-9][0-9]*$' THEN
        RAISE EXCEPTION 'sales invoice stock effect source id is invalid' USING ERRCODE = '23514';
    END IF;

    invoice_line_id := CAST(NEW.source_id AS bigint);

    SELECT invoice.status, invoice.mode INTO parent_status, parent_mode
    FROM sales_invoice_lines AS line
    INNER JOIN sales_invoices AS invoice
      ON invoice.company_id = line.company_id
     AND invoice.id = line.sales_invoice_id
    WHERE line.company_id = NEW.company_id
      AND line.id = invoice_line_id;

    IF parent_status IS NULL OR parent_mode NOT IN ('direct', 'order_linked') THEN
        RAISE EXCEPTION 'sales invoice stock effect requires a direct/order invoice line' USING ERRCODE = '23514';
    END IF;

    IF NEW.effect_type = 'stock.out' THEN
        IF parent_status NOT IN ('finalized', 'cancelled')
           OR NEW.movement_type <> 'invoice_out'
           OR NEW.reversal_of_movement_id IS NOT NULL THEN
            RAISE EXCEPTION 'invoice stock out must commit with its source invoice finalized' USING ERRCODE = '23514';
        END IF;
    ELSE
        IF parent_status <> 'cancelled'
           OR NEW.movement_type <> 'adjustment_in'
           OR NEW.reversal_of_movement_id IS NULL
           OR NOT EXISTS (
               SELECT 1
               FROM stock_movements AS original
               WHERE original.id = NEW.reversal_of_movement_id
                 AND original.company_id = NEW.company_id
                 AND original.source_type = 'sales_invoice_line'
                 AND original.source_id = NEW.source_id
                 AND original.effect_type = 'stock.out'
                 AND original.movement_type = 'invoice_out'
           ) THEN
            RAISE EXCEPTION 'invoice stock reversal must commit with its source invoice cancelled' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER sales_invoice_stock_effect_commit_guard
AFTER INSERT ON stock_movements
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_stock_effect_commit();

CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_fulfillment_progress_commit()
RETURNS trigger AS $$
DECLARE
    parent_status text;
    parent_mode text;
BEGIN
    IF NEW.source_type <> 'sales_invoice_line'
       OR NEW.effect_type NOT IN ('progress.dispatch', 'progress.dispatch.reverse') THEN
        RETURN NEW;
    END IF;

    IF NEW.source_id !~ '^[1-9][0-9]*$' THEN
        RAISE EXCEPTION 'sales invoice fulfillment progress source id is invalid' USING ERRCODE = '23514';
    END IF;

    SELECT invoice.status, invoice.mode INTO parent_status, parent_mode
    FROM sales_invoice_lines AS line
    INNER JOIN sales_invoices AS invoice
      ON invoice.company_id = line.company_id
     AND invoice.id = line.sales_invoice_id
    WHERE line.company_id = NEW.company_id
      AND line.id = CAST(NEW.source_id AS bigint);

    IF parent_status IS NULL OR parent_mode <> 'order_linked' THEN
        RAISE EXCEPTION 'invoice fulfillment progress requires an order-linked invoice line' USING ERRCODE = '23514';
    END IF;

    IF NEW.effect_type = 'progress.dispatch' THEN
        IF parent_status NOT IN ('finalized', 'cancelled')
           OR NEW.progress_type <> 'dispatched'
           OR NEW.quantity_delta <= 0
           OR NEW.reversal_of_progress_effect_id IS NOT NULL THEN
            RAISE EXCEPTION 'invoice fulfillment progress must commit with its source invoice finalized' USING ERRCODE = '23514';
        END IF;
    ELSE
        IF parent_status <> 'cancelled'
           OR NEW.progress_type <> 'dispatched'
           OR NEW.quantity_delta >= 0
           OR NEW.reversal_of_progress_effect_id IS NULL
           OR NOT EXISTS (
               SELECT 1
               FROM sales_order_line_progress_effects AS original
               WHERE original.id = NEW.reversal_of_progress_effect_id
                 AND original.company_id = NEW.company_id
                 AND original.source_type = 'sales_invoice_line'
                 AND original.source_id = NEW.source_id
                 AND original.effect_type = 'progress.dispatch'
                 AND original.progress_type = 'dispatched'
           ) THEN
            RAISE EXCEPTION 'invoice fulfillment reversal must commit with its source invoice cancelled' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER sales_invoice_fulfillment_progress_commit_guard
AFTER INSERT ON sales_order_line_progress_effects
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_fulfillment_progress_commit();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_fulfillment_progress_commit_guard ON sales_order_line_progress_effects');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_fulfillment_progress_commit()');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_stock_effect_commit_guard ON stock_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_stock_effect_commit()');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_stock_lifecycle_commit_guard ON sales_invoices');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_stock_lifecycle_commit()');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_dispatch_line_capacity_guard ON sales_invoice_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_dispatch_line_capacity()');
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_invoice_out_guard ON stock_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_stock_out()');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_invoice_source_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_direction_check');

        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM stock_movements WHERE movement_type = 'invoice_out') THEN
        ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_type_check
            CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'dispatch_out', 'invoice_out'));
        ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_direction_check
            CHECK (
                (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                OR
                (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
            );
    ELSE
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
    END IF;
END $$;
SQL);
    }
};
