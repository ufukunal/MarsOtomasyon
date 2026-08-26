<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE VIEW sales_invoice_order_line_capacity AS
WITH draft_commitments AS (
    SELECT
        line.company_id,
        line.source_sales_order_id AS sales_order_id,
        line.source_sales_order_line_id AS sales_order_line_id,
        COALESCE(SUM(line.quantity), 0)::numeric(20, 6) AS draft_quantity
    FROM sales_invoice_lines AS line
    INNER JOIN sales_invoices AS invoice
      ON invoice.company_id = line.company_id
     AND invoice.id = line.sales_invoice_id
    WHERE invoice.status = 'draft'
      AND line.source_sales_order_id IS NOT NULL
      AND line.source_sales_order_line_id IS NOT NULL
    GROUP BY line.company_id, line.source_sales_order_id, line.source_sales_order_line_id
)
SELECT
    progress.company_id,
    progress.sales_order_id,
    progress.sales_order_line_id,
    progress.ordered_quantity::numeric(20, 6) AS ordered_quantity,
    progress.cancelled_quantity::numeric(20, 6) AS cancelled_quantity,
    progress.net_invoiced_quantity::numeric(20, 6) AS net_invoiced_quantity,
    COALESCE(draft.draft_quantity, 0)::numeric(20, 6) AS draft_quantity,
    (
        progress.net_invoiced_quantity
        + COALESCE(draft.draft_quantity, 0)
    )::numeric(20, 6) AS previous_quantity,
    (
        progress.invoice_remaining_quantity
        - COALESCE(draft.draft_quantity, 0)
    )::numeric(20, 6) AS remaining_quantity
FROM sales_order_line_progress AS progress
LEFT JOIN draft_commitments AS draft
  ON draft.company_id = progress.company_id
 AND draft.sales_order_id = progress.sales_order_id
 AND draft.sales_order_line_id = progress.sales_order_line_id
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_line_quantity_contract()
RETURNS trigger AS $$
DECLARE
    ordered numeric(20, 6);
    invoiced numeric(20, 6);
    cancelled numeric(20, 6);
    drafts numeric(20, 6);
    invoice_status text;
BEGIN
    SELECT status INTO invoice_status
    FROM sales_invoices
    WHERE company_id = NEW.company_id
      AND id = NEW.sales_invoice_id
    FOR UPDATE;

    IF invoice_status IS NULL THEN
        RAISE EXCEPTION 'sales invoice quantity contract header not found' USING ERRCODE = '23503';
    END IF;

    IF invoice_status <> 'draft' OR NEW.source_sales_order_line_id IS NULL THEN
        RETURN NEW;
    END IF;

    SELECT quantity INTO ordered
    FROM sales_order_lines
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.source_sales_order_id
      AND id = NEW.source_sales_order_line_id
    FOR UPDATE;

    IF ordered IS NULL THEN
        RAISE EXCEPTION 'sales invoice quantity contract order line not found' USING ERRCODE = '23503';
    END IF;

    SELECT
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'invoiced'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'cancelled'), 0)
    INTO invoiced, cancelled
    FROM sales_order_line_progress_effects
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.source_sales_order_id
      AND sales_order_line_id = NEW.source_sales_order_line_id;

    IF TG_OP = 'UPDATE' THEN
        SELECT COALESCE(SUM(line.quantity), 0)
        INTO drafts
        FROM sales_invoice_lines AS line
        INNER JOIN sales_invoices AS invoice
          ON invoice.company_id = line.company_id
         AND invoice.id = line.sales_invoice_id
        WHERE line.company_id = NEW.company_id
          AND line.source_sales_order_id = NEW.source_sales_order_id
          AND line.source_sales_order_line_id = NEW.source_sales_order_line_id
          AND invoice.status = 'draft'
          AND line.id <> OLD.id;
    ELSE
        SELECT COALESCE(SUM(line.quantity), 0)
        INTO drafts
        FROM sales_invoice_lines AS line
        INNER JOIN sales_invoices AS invoice
          ON invoice.company_id = line.company_id
         AND invoice.id = line.sales_invoice_id
        WHERE line.company_id = NEW.company_id
          AND line.source_sales_order_id = NEW.source_sales_order_id
          AND line.source_sales_order_line_id = NEW.source_sales_order_line_id
          AND invoice.status = 'draft';
    END IF;

    IF invoiced + cancelled + drafts + NEW.quantity > ordered THEN
        RAISE EXCEPTION 'sales invoice quantity exceeds order line remaining quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_invoice_lines_quantity_contract_guard
BEFORE INSERT OR UPDATE ON sales_invoice_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_line_quantity_contract();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_draft_capacity_progress()
RETURNS trigger AS $$
DECLARE
    ordered numeric(20, 6);
    invoiced numeric(20, 6);
    cancelled numeric(20, 6);
    drafts numeric(20, 6);
BEGIN
    IF TG_OP <> 'INSERT' OR NEW.progress_type NOT IN ('invoiced', 'cancelled') THEN
        RETURN NEW;
    END IF;

    SELECT quantity INTO ordered
    FROM sales_order_lines
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.sales_order_id
      AND id = NEW.sales_order_line_id
    FOR UPDATE;

    IF ordered IS NULL THEN
        RAISE EXCEPTION 'sales invoice draft capacity order line not found' USING ERRCODE = '23503';
    END IF;

    SELECT
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'invoiced'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'cancelled'), 0)
    INTO invoiced, cancelled
    FROM sales_order_line_progress_effects
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.sales_order_id
      AND sales_order_line_id = NEW.sales_order_line_id;

    IF NEW.progress_type = 'invoiced' THEN
        invoiced := invoiced + NEW.quantity_delta;
    ELSE
        cancelled := cancelled + NEW.quantity_delta;
    END IF;

    SELECT COALESCE(SUM(line.quantity), 0)
    INTO drafts
    FROM sales_invoice_lines AS line
    INNER JOIN sales_invoices AS invoice
      ON invoice.company_id = line.company_id
     AND invoice.id = line.sales_invoice_id
    WHERE line.company_id = NEW.company_id
      AND line.source_sales_order_id = NEW.sales_order_id
      AND line.source_sales_order_line_id = NEW.sales_order_line_id
      AND invoice.status = 'draft';

    IF invoiced + cancelled + drafts > ordered THEN
        RAISE EXCEPTION 'sales order progress conflicts with draft sales invoice quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_invoice_draft_capacity_progress_guard
BEFORE INSERT ON sales_order_line_progress_effects
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_draft_capacity_progress();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_draft_capacity_progress_guard ON sales_order_line_progress_effects');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_draft_capacity_progress()');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_lines_quantity_contract_guard ON sales_invoice_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_line_quantity_contract()');
        DB::statement('DROP VIEW IF EXISTS sales_invoice_order_line_capacity');
    }
};
