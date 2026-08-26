<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table): void {
            $table->timestampTz('finalized_at')->nullable()->after('status');
        });

        DB::statement('ALTER TABLE dispatches DROP CONSTRAINT dispatches_status_check');
        DB::statement("ALTER TABLE dispatches ADD CONSTRAINT dispatches_status_check CHECK (status IN ('draft', 'finalized'))");
        DB::statement("ALTER TABLE dispatches ADD CONSTRAINT dispatches_finalized_at_check CHECK ((status = 'draft' AND finalized_at IS NULL) OR (status = 'finalized' AND finalized_at IS NOT NULL))");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_dispatch_draft_capacity_progress()
RETURNS trigger AS $$
DECLARE
    ordered numeric(20, 6);
    dispatched numeric(20, 6);
    cancelled numeric(20, 6);
    drafts numeric(20, 6);
    source_line_id bigint;
    source_line_quantity numeric(20, 6);
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

        IF NEW.source_type = 'dispatch_line' THEN
            IF NEW.effect_type <> 'progress.dispatch' OR NEW.source_id !~ '^[1-9][0-9]*$' THEN
                RAISE EXCEPTION 'dispatch progress source identity is invalid' USING ERRCODE = '23514';
            END IF;

            SELECT line.id, line.quantity
            INTO source_line_id, source_line_quantity
            FROM dispatch_lines AS line
            INNER JOIN dispatches AS dispatch
              ON dispatch.company_id = line.company_id
             AND dispatch.id = line.dispatch_id
            WHERE line.company_id = NEW.company_id
              AND line.sales_order_id = NEW.sales_order_id
              AND line.sales_order_line_id = NEW.sales_order_line_id
              AND line.id = CAST(NEW.source_id AS bigint)
              AND dispatch.status = 'draft'
            FOR SHARE OF line, dispatch;

            IF source_line_id IS NULL OR NEW.quantity_delta <> source_line_quantity THEN
                RAISE EXCEPTION 'dispatch progress must exactly match its draft dispatch line' USING ERRCODE = '23514';
            END IF;
        END IF;
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
      AND dispatch.status = 'draft'
      AND (source_line_id IS NULL OR line.id <> source_line_id);

    IF dispatched + cancelled + drafts > ordered THEN
        RAISE EXCEPTION 'sales order progress conflicts with draft dispatch quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_dispatch_finalization()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status = 'finalized' THEN
            RAISE EXCEPTION 'finalized dispatch is immutable' USING ERRCODE = '55000';
        END IF;

        RETURN OLD;
    END IF;

    IF OLD.status = 'finalized' THEN
        RAISE EXCEPTION 'finalized dispatch is immutable' USING ERRCODE = '55000';
    END IF;

    IF OLD.status = NEW.status THEN
        RETURN NEW;
    END IF;

    IF OLD.status <> 'draft' OR NEW.status <> 'finalized' THEN
        RAISE EXCEPTION 'invalid dispatch status transition' USING ERRCODE = '23514';
    END IF;

    IF NEW.finalized_at IS NULL THEN
        RAISE EXCEPTION 'finalized dispatch requires finalized_at' USING ERRCODE = '23514';
    END IF;

    IF (to_jsonb(NEW) - 'status' - 'finalized_at' - 'updated_at') IS DISTINCT FROM
       (to_jsonb(OLD) - 'status' - 'finalized_at' - 'updated_at') THEN
        RAISE EXCEPTION 'dispatch finalization may only change finalization fields' USING ERRCODE = '23514';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM dispatch_lines
        WHERE company_id = OLD.company_id AND dispatch_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'dispatch finalization requires at least one line' USING ERRCODE = '23514';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM dispatch_lines AS line
        WHERE line.company_id = OLD.company_id
          AND line.dispatch_id = OLD.id
          AND (
              NOT EXISTS (
                  SELECT 1
                  FROM stock_movements AS movement
                  WHERE movement.company_id = line.company_id
                    AND movement.source_type = 'dispatch_line'
                    AND movement.source_id = line.id::text
                    AND movement.effect_type = 'stock.out'
                    AND movement.movement_type = 'dispatch_out'
                    AND movement.quantity_delta = -line.quantity
              )
              OR NOT EXISTS (
                  SELECT 1
                  FROM sales_order_line_progress_effects AS effect
                  WHERE effect.company_id = line.company_id
                    AND effect.sales_order_id = line.sales_order_id
                    AND effect.sales_order_line_id = line.sales_order_line_id
                    AND effect.source_type = 'dispatch_line'
                    AND effect.source_id = line.id::text
                    AND effect.effect_type = 'progress.dispatch'
                    AND effect.progress_type = 'dispatched'
                    AND effect.quantity_delta = line.quantity
              )
          )
    ) THEN
        RAISE EXCEPTION 'dispatch finalization requires exact stock and sales-order progress effects for every line' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER dispatch_finalization_guard
BEFORE UPDATE OR DELETE ON dispatches
FOR EACH ROW EXECUTE FUNCTION mars_guard_dispatch_finalization();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS dispatch_finalization_guard ON dispatches');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_dispatch_finalization()');
        DB::statement("UPDATE dispatches SET status = 'finalized' WHERE status = 'cancelled'");

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
SQL);

        DB::statement('ALTER TABLE dispatches DROP CONSTRAINT IF EXISTS dispatches_finalized_at_check');
        DB::statement('ALTER TABLE dispatches DROP CONSTRAINT IF EXISTS dispatches_status_check');
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM dispatches WHERE status = 'finalized') THEN
        ALTER TABLE dispatches ADD CONSTRAINT dispatches_status_check CHECK (status IN ('draft', 'finalized'));
    ELSE
        ALTER TABLE dispatches ADD CONSTRAINT dispatches_status_check CHECK (status = 'draft');
    END IF;
END $$;
SQL);

        Schema::table('dispatches', function (Blueprint $table): void {
            $table->dropColumn('finalized_at');
        });
    }
};
