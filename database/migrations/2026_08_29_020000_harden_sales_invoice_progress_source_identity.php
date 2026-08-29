<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_progress_source_identity()
RETURNS trigger AS $$
DECLARE
    source_line_id bigint;
    source_line_quantity numeric(20, 6);
BEGIN
    IF TG_OP <> 'INSERT' THEN
        RETURN NEW;
    END IF;

    -- Reversals have their own exact lifecycle contract. A sales invoice line
    -- also legitimately emits dispatch effects, so this hardening applies only
    -- to the original invoiced progress effect produced from that line.
    IF NEW.reversal_of_progress_effect_id IS NOT NULL
       OR NEW.source_type <> 'sales_invoice_line'
       OR NEW.effect_type <> 'progress.invoice' THEN
        RETURN NEW;
    END IF;

    IF NEW.progress_type <> 'invoiced'
       OR NEW.source_id !~ '^[1-9][0-9]*$' THEN
        RAISE EXCEPTION 'sales invoice progress source identity is invalid' USING ERRCODE = '23514';
    END IF;

    SELECT line.id, line.quantity
    INTO source_line_id, source_line_quantity
    FROM sales_invoice_lines AS line
    WHERE line.company_id = NEW.company_id
      AND line.source_sales_order_id = NEW.sales_order_id
      AND line.source_sales_order_line_id = NEW.sales_order_line_id
      AND line.id = CAST(NEW.source_id AS bigint)
    FOR SHARE;

    IF source_line_id IS NULL OR NEW.quantity_delta <> source_line_quantity THEN
        RAISE EXCEPTION 'sales invoice progress must exactly match its source invoice line' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_invoice_progress_source_identity_guard
BEFORE INSERT ON sales_order_line_progress_effects
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_progress_source_identity();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_progress_source_identity_guard ON sales_order_line_progress_effects');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_progress_source_identity()');
    }
};
