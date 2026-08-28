<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_progress_effect()
RETURNS trigger AS $$
DECLARE
    ordered numeric(20, 6);
    parent_status text;
    received numeric(20, 6);
    invoiced numeric(20, 6);
    cancelled numeric(20, 6);
BEGIN
    IF TG_OP <> 'INSERT' THEN
        RAISE EXCEPTION 'purchase order progress effects are append-only' USING ERRCODE = '55000';
    END IF;

    SELECT line.quantity, po.status INTO ordered, parent_status
    FROM purchase_order_lines line
    JOIN purchase_orders po
      ON po.company_id = line.company_id
     AND po.id = line.purchase_order_id
    WHERE line.company_id = NEW.company_id
      AND line.purchase_order_id = NEW.purchase_order_id
      AND line.id = NEW.purchase_order_line_id
    FOR UPDATE OF line, po;

    IF ordered IS NULL OR parent_status IS NULL THEN
        RAISE EXCEPTION 'purchase order progress line not found' USING ERRCODE = '23503';
    END IF;

    IF NEW.quantity_delta > 0 AND parent_status <> 'open' THEN
        RAISE EXCEPTION 'positive purchase order progress requires open parent' USING ERRCODE = '23514';
    END IF;

    IF NEW.quantity_delta < 0 THEN
        IF NEW.source_type <> 'purchase_return_line' OR NEW.progress_type NOT IN ('received', 'invoiced') THEN
            RAISE EXCEPTION 'negative purchase order progress is reserved for purchase return correction' USING ERRCODE = '23514';
        END IF;
        IF parent_status NOT IN ('open', 'closed') THEN
            RAISE EXCEPTION 'purchase return correction requires open or closed parent' USING ERRCODE = '23514';
        END IF;
    END IF;

    SELECT
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'received'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'invoiced'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'cancelled'), 0)
    INTO received, invoiced, cancelled
    FROM purchase_order_line_progress_effects
    WHERE company_id = NEW.company_id
      AND purchase_order_id = NEW.purchase_order_id
      AND purchase_order_line_id = NEW.purchase_order_line_id;

    IF NEW.progress_type = 'received' THEN
        received := received + NEW.quantity_delta;
    ELSIF NEW.progress_type = 'invoiced' THEN
        invoiced := invoiced + NEW.quantity_delta;
    ELSE
        cancelled := cancelled + NEW.quantity_delta;
    END IF;

    IF received < 0 OR invoiced < 0 OR cancelled < 0 THEN
        RAISE EXCEPTION 'purchase order net progress cannot be negative' USING ERRCODE = '23514';
    END IF;
    IF received + cancelled > ordered THEN
        RAISE EXCEPTION 'purchase order receipt progress exceeds ordered quantity' USING ERRCODE = '23514';
    END IF;
    IF invoiced + cancelled > ordered THEN
        RAISE EXCEPTION 'purchase order invoice progress exceeds ordered quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_progress_effect()
RETURNS trigger AS $$
DECLARE
    ordered numeric(20, 6);
    received numeric(20, 6);
    invoiced numeric(20, 6);
    cancelled numeric(20, 6);
BEGIN
    IF TG_OP <> 'INSERT' THEN
        RAISE EXCEPTION 'purchase order progress effects are append-only' USING ERRCODE = '55000';
    END IF;

    SELECT quantity INTO ordered
    FROM purchase_order_lines
    WHERE company_id = NEW.company_id
      AND purchase_order_id = NEW.purchase_order_id
      AND id = NEW.purchase_order_line_id
    FOR UPDATE;

    IF ordered IS NULL THEN
        RAISE EXCEPTION 'purchase order progress line not found' USING ERRCODE = '23503';
    END IF;

    SELECT
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'received'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'invoiced'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'cancelled'), 0)
    INTO received, invoiced, cancelled
    FROM purchase_order_line_progress_effects
    WHERE company_id = NEW.company_id
      AND purchase_order_id = NEW.purchase_order_id
      AND purchase_order_line_id = NEW.purchase_order_line_id;

    IF NEW.progress_type = 'received' THEN
        received := received + NEW.quantity_delta;
    ELSIF NEW.progress_type = 'invoiced' THEN
        invoiced := invoiced + NEW.quantity_delta;
    ELSE
        cancelled := cancelled + NEW.quantity_delta;
    END IF;

    IF received < 0 OR invoiced < 0 OR cancelled < 0 THEN
        RAISE EXCEPTION 'purchase order net progress cannot be negative' USING ERRCODE = '23514';
    END IF;
    IF received + cancelled > ordered THEN
        RAISE EXCEPTION 'purchase order receipt progress exceeds ordered quantity' USING ERRCODE = '23514';
    END IF;
    IF invoiced + cancelled > ordered THEN
        RAISE EXCEPTION 'purchase order invoice progress exceeds ordered quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }
};