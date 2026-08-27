<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_stock_effect_commit()
RETURNS trigger AS $$
DECLARE
    parent_status text;
    parent_mode text;
    invoice_line_id bigint;
    effect_source_type text;
    effect_source_id text;
    effect_name text;
BEGIN
    effect_source_type := to_jsonb(NEW)->>'source_type';
    effect_source_id := to_jsonb(NEW)->>'source_id';
    effect_name := to_jsonb(NEW)->>'effect_type';

    IF effect_source_type IS DISTINCT FROM 'sales_invoice_line'
       OR effect_name IS NULL
       OR effect_name NOT IN ('stock.out', 'stock.out.reverse') THEN
        RETURN NEW;
    END IF;

    IF effect_source_id IS NULL OR effect_source_id !~ '^[1-9][0-9]*$' THEN
        RAISE EXCEPTION 'sales invoice stock effect source id is invalid' USING ERRCODE = '23514';
    END IF;

    invoice_line_id := CAST(effect_source_id AS bigint);

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

    IF effect_name = 'stock.out' THEN
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
                 AND original.source_id = effect_source_id
                 AND original.effect_type = 'stock.out'
                 AND original.movement_type = 'invoice_out'
           ) THEN
            RAISE EXCEPTION 'invoice stock reversal must commit with its source invoice cancelled' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }

    public function down(): void
    {
        // The owning 008400 migration removes this function when M8.5 is rolled back.
        // Keep the corrected definition active until that owning migration is reverted.
    }
};
