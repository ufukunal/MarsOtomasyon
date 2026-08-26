<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE VIEW dispatch_order_line_capacity AS
WITH draft_commitments AS (
    SELECT
        line.company_id,
        line.sales_order_id,
        line.sales_order_line_id,
        COALESCE(SUM(line.quantity), 0)::numeric(20, 6) AS draft_quantity
    FROM dispatch_lines AS line
    INNER JOIN dispatches AS dispatch
      ON dispatch.company_id = line.company_id
     AND dispatch.id = line.dispatch_id
    WHERE dispatch.status = 'draft'
    GROUP BY line.company_id, line.sales_order_id, line.sales_order_line_id
)
SELECT
    progress.company_id,
    progress.sales_order_id,
    progress.sales_order_line_id,
    progress.ordered_quantity::numeric(20, 6) AS ordered_quantity,
    progress.cancelled_quantity::numeric(20, 6) AS cancelled_quantity,
    progress.net_dispatched_quantity::numeric(20, 6) AS net_dispatched_quantity,
    COALESCE(draft.draft_quantity, 0)::numeric(20, 6) AS draft_quantity,
    (
        progress.net_dispatched_quantity
        + COALESCE(draft.draft_quantity, 0)
    )::numeric(20, 6) AS previous_quantity,
    (
        progress.dispatch_remaining_quantity
        - COALESCE(draft.draft_quantity, 0)
    )::numeric(20, 6) AS remaining_quantity
FROM sales_order_line_progress AS progress
LEFT JOIN draft_commitments AS draft
  ON draft.company_id = progress.company_id
 AND draft.sales_order_id = progress.sales_order_id
 AND draft.sales_order_line_id = progress.sales_order_line_id
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_dispatch_line_quantity_contract()
RETURNS trigger AS $$
DECLARE
    ordered numeric(20, 6);
    dispatched numeric(20, 6);
    cancelled numeric(20, 6);
    drafts numeric(20, 6);
    dispatch_status text;
BEGIN
    SELECT status INTO dispatch_status
    FROM dispatches
    WHERE company_id = NEW.company_id
      AND id = NEW.dispatch_id;

    IF dispatch_status IS NULL THEN
        RAISE EXCEPTION 'dispatch quantity contract header not found' USING ERRCODE = '23503';
    END IF;

    IF dispatch_status <> 'draft' THEN
        RETURN NEW;
    END IF;

    SELECT quantity INTO ordered
    FROM sales_order_lines
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.sales_order_id
      AND id = NEW.sales_order_line_id
    FOR UPDATE;

    IF ordered IS NULL THEN
        RAISE EXCEPTION 'dispatch quantity contract order line not found' USING ERRCODE = '23503';
    END IF;

    SELECT
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'dispatched'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'cancelled'), 0)
    INTO dispatched, cancelled
    FROM sales_order_line_progress_effects
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.sales_order_id
      AND sales_order_line_id = NEW.sales_order_line_id;

    SELECT COALESCE(SUM(line.quantity), 0)
    INTO drafts
    FROM dispatch_lines AS line
    INNER JOIN dispatches AS dispatch
      ON dispatch.company_id = line.company_id
     AND dispatch.id = line.dispatch_id
    WHERE line.company_id = NEW.company_id
      AND line.sales_order_id = NEW.sales_order_id
      AND line.sales_order_line_id = NEW.sales_order_line_id
      AND dispatch.status = 'draft'
      AND (TG_OP <> 'UPDATE' OR line.id <> OLD.id);

    IF dispatched + cancelled + drafts + NEW.quantity > ordered THEN
        RAISE EXCEPTION 'dispatch quantity exceeds order line remaining quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER dispatch_lines_quantity_contract_guard
BEFORE INSERT OR UPDATE ON dispatch_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_dispatch_line_quantity_contract();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_dispatch_draft_capacity_progress()
RETURNS trigger AS $$
DECLARE
    ordered numeric(20, 6);
    dispatched numeric(20, 6);
    cancelled numeric(20, 6);
    drafts numeric(20, 6);
BEGIN
    IF TG_OP <> 'INSERT' OR NEW.progress_type NOT IN ('dispatched', 'cancelled') THEN
        RETURN NEW;
    END IF;

    SELECT quantity INTO ordered
    FROM sales_order_lines
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.sales_order_id
      AND id = NEW.sales_order_line_id
    FOR UPDATE;

    IF ordered IS NULL THEN
        RAISE EXCEPTION 'dispatch draft capacity order line not found' USING ERRCODE = '23503';
    END IF;

    SELECT
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'dispatched'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'cancelled'), 0)
    INTO dispatched, cancelled
    FROM sales_order_line_progress_effects
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.sales_order_id
      AND sales_order_line_id = NEW.sales_order_line_id;

    IF NEW.progress_type = 'dispatched' THEN
        dispatched := dispatched + NEW.quantity_delta;
    ELSE
        cancelled := cancelled + NEW.quantity_delta;
    END IF;

    SELECT COALESCE(SUM(line.quantity), 0)
    INTO drafts
    FROM dispatch_lines AS line
    INNER JOIN dispatches AS dispatch
      ON dispatch.company_id = line.company_id
     AND dispatch.id = line.dispatch_id
    WHERE line.company_id = NEW.company_id
      AND line.sales_order_id = NEW.sales_order_id
      AND line.sales_order_line_id = NEW.sales_order_line_id
      AND dispatch.status = 'draft';

    IF dispatched + cancelled + drafts > ordered THEN
        RAISE EXCEPTION 'sales order progress conflicts with draft dispatch quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER dispatch_draft_capacity_progress_guard
BEFORE INSERT ON sales_order_line_progress_effects
FOR EACH ROW EXECUTE FUNCTION mars_guard_dispatch_draft_capacity_progress();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS dispatch_draft_capacity_progress_guard ON sales_order_line_progress_effects');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_dispatch_draft_capacity_progress()');
        DB::statement('DROP TRIGGER IF EXISTS dispatch_lines_quantity_contract_guard ON dispatch_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_dispatch_line_quantity_contract()');
        DB::statement('DROP VIEW IF EXISTS dispatch_order_line_capacity');
    }
};
