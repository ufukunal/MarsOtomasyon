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
            $table->timestampTz('cancelled_at')->nullable()->after('finalized_at');
        });

        DB::statement('ALTER TABLE dispatches DROP CONSTRAINT dispatches_finalized_at_check');
        DB::statement('ALTER TABLE dispatches DROP CONSTRAINT dispatches_status_check');
        DB::statement("ALTER TABLE dispatches ADD CONSTRAINT dispatches_status_check CHECK (status IN ('draft', 'finalized', 'cancelled'))");
        DB::statement(<<<'SQL'
ALTER TABLE dispatches ADD CONSTRAINT dispatches_lifecycle_timestamp_check CHECK (
    (status = 'draft' AND finalized_at IS NULL AND cancelled_at IS NULL)
    OR
    (status = 'finalized' AND finalized_at IS NOT NULL AND cancelled_at IS NULL)
    OR
    (status = 'cancelled' AND finalized_at IS NOT NULL AND cancelled_at IS NOT NULL)
)
SQL);

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
    source_dispatch_status text;
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
            IF NEW.source_id !~ '^[1-9][0-9]*$' THEN
                RAISE EXCEPTION 'dispatch progress source identity is invalid' USING ERRCODE = '23514';
            END IF;

            SELECT line.id, line.quantity, dispatch.status
            INTO source_line_id, source_line_quantity, source_dispatch_status
            FROM dispatch_lines AS line
            INNER JOIN dispatches AS dispatch
              ON dispatch.company_id = line.company_id
             AND dispatch.id = line.dispatch_id
            WHERE line.company_id = NEW.company_id
              AND line.sales_order_id = NEW.sales_order_id
              AND line.sales_order_line_id = NEW.sales_order_line_id
              AND line.id = CAST(NEW.source_id AS bigint)
            FOR SHARE OF line, dispatch;

            IF source_line_id IS NULL THEN
                RAISE EXCEPTION 'dispatch progress source line not found' USING ERRCODE = '23514';
            END IF;

            IF NEW.quantity_delta > 0 THEN
                IF NEW.effect_type <> 'progress.dispatch'
                   OR NEW.reversal_of_progress_effect_id IS NOT NULL
                   OR source_dispatch_status <> 'draft'
                   OR NEW.quantity_delta <> source_line_quantity THEN
                    RAISE EXCEPTION 'dispatch progress must exactly match its draft dispatch line' USING ERRCODE = '23514';
                END IF;
            ELSE
                IF NEW.effect_type <> 'progress.dispatch.reverse'
                   OR NEW.reversal_of_progress_effect_id IS NULL
                   OR source_dispatch_status <> 'finalized'
                   OR NEW.quantity_delta <> -source_line_quantity
                   OR NOT EXISTS (
                       SELECT 1
                       FROM sales_order_line_progress_effects AS original
                       WHERE original.id = NEW.reversal_of_progress_effect_id
                         AND original.company_id = NEW.company_id
                         AND original.sales_order_id = NEW.sales_order_id
                         AND original.sales_order_line_id = NEW.sales_order_line_id
                         AND original.source_type = 'dispatch_line'
                         AND original.source_id = NEW.source_id
                         AND original.effect_type = 'progress.dispatch'
                         AND original.progress_type = 'dispatched'
                         AND original.quantity_delta = source_line_quantity
                         AND original.reversal_of_progress_effect_id IS NULL
                   ) THEN
                    RAISE EXCEPTION 'dispatch progress reversal must exactly negate its finalized source line effect' USING ERRCODE = '23514';
                END IF;
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
      AND (
          NEW.progress_type <> 'dispatched'
          OR NEW.quantity_delta <= 0
          OR source_line_id IS NULL
          OR line.id <> source_line_id
      );

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
        IF OLD.status <> 'draft' THEN
            RAISE EXCEPTION 'non-draft dispatch is immutable' USING ERRCODE = '55000';
        END IF;

        RETURN OLD;
    END IF;

    IF OLD.status = 'cancelled' THEN
        RAISE EXCEPTION 'cancelled dispatch is immutable' USING ERRCODE = '55000';
    END IF;

    IF OLD.status = 'finalized' THEN
        IF NEW.status <> 'cancelled' THEN
            RAISE EXCEPTION 'finalized dispatch is immutable except atomic cancellation' USING ERRCODE = '55000';
        END IF;

        IF NEW.cancelled_at IS NULL OR NEW.finalized_at IS DISTINCT FROM OLD.finalized_at THEN
            RAISE EXCEPTION 'cancelled dispatch requires preserved finalized_at and cancelled_at' USING ERRCODE = '23514';
        END IF;

        IF (to_jsonb(NEW) - 'status' - 'cancelled_at' - 'updated_at') IS DISTINCT FROM
           (to_jsonb(OLD) - 'status' - 'cancelled_at' - 'updated_at') THEN
            RAISE EXCEPTION 'dispatch cancellation may only change cancellation fields' USING ERRCODE = '23514';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM dispatch_lines AS line
            WHERE line.company_id = OLD.company_id
              AND line.dispatch_id = OLD.id
              AND (
                  NOT EXISTS (
                      SELECT 1
                      FROM stock_movements AS original
                      WHERE original.company_id = line.company_id
                        AND original.source_type = 'dispatch_line'
                        AND original.source_id = line.id::text
                        AND original.effect_type = 'stock.out'
                        AND original.movement_type = 'dispatch_out'
                        AND original.quantity_delta = -line.quantity
                        AND EXISTS (
                            SELECT 1
                            FROM stock_movements AS reversal
                            WHERE reversal.company_id = original.company_id
                              AND reversal.reversal_of_movement_id = original.id
                              AND reversal.source_type = 'dispatch_line'
                              AND reversal.source_id = line.id::text
                              AND reversal.effect_type = 'stock.out.reverse'
                              AND reversal.movement_type = 'adjustment_in'
                              AND reversal.quantity_delta = line.quantity
                        )
                  )
                  OR NOT EXISTS (
                      SELECT 1
                      FROM sales_order_line_progress_effects AS original
                      WHERE original.company_id = line.company_id
                        AND original.sales_order_id = line.sales_order_id
                        AND original.sales_order_line_id = line.sales_order_line_id
                        AND original.source_type = 'dispatch_line'
                        AND original.source_id = line.id::text
                        AND original.effect_type = 'progress.dispatch'
                        AND original.progress_type = 'dispatched'
                        AND original.quantity_delta = line.quantity
                        AND EXISTS (
                            SELECT 1
                            FROM sales_order_line_progress_effects AS reversal
                            WHERE reversal.company_id = original.company_id
                              AND reversal.reversal_of_progress_effect_id = original.id
                              AND reversal.source_type = 'dispatch_line'
                              AND reversal.source_id = line.id::text
                              AND reversal.effect_type = 'progress.dispatch.reverse'
                              AND reversal.progress_type = 'dispatched'
                              AND reversal.quantity_delta = -line.quantity
                        )
                  )
              )
        ) THEN
            RAISE EXCEPTION 'dispatch cancellation requires exact stock and sales-order progress reversals for every line' USING ERRCODE = '23514';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM dispatch_lines AS line
            INNER JOIN sales_order_lines AS order_line
              ON order_line.company_id = line.company_id
             AND order_line.sales_order_id = line.sales_order_id
             AND order_line.id = line.sales_order_line_id
            INNER JOIN sales_order_line_progress AS progress
              ON progress.company_id = order_line.company_id
             AND progress.sales_order_id = order_line.sales_order_id
             AND progress.sales_order_line_id = order_line.id
            WHERE line.company_id = OLD.company_id
              AND line.dispatch_id = OLD.id
              AND order_line.warehouse_id IS NOT NULL
              AND (
                  (
                      progress.dispatch_remaining_quantity > 0
                      AND NOT EXISTS (
                          SELECT 1
                          FROM sales_order_reservation_generations AS generation
                          INNER JOIN stock_reservations AS reservation
                            ON reservation.company_id = generation.company_id
                           AND reservation.id = generation.stock_reservation_id
                          WHERE generation.company_id = order_line.company_id
                            AND generation.sales_order_id = order_line.sales_order_id
                            AND generation.logical_line_key = order_line.logical_line_key
                            AND generation.released_at IS NULL
                            AND generation.product_id = order_line.product_id
                            AND generation.warehouse_id = order_line.warehouse_id
                            AND generation.location_id = order_line.location_id
                            AND generation.quantity = progress.dispatch_remaining_quantity
                            AND reservation.status = 'active'
                            AND reservation.product_id = order_line.product_id
                            AND reservation.warehouse_id = order_line.warehouse_id
                            AND reservation.location_id = order_line.location_id
                            AND reservation.quantity = progress.dispatch_remaining_quantity
                      )
                  )
                  OR
                  (
                      progress.dispatch_remaining_quantity = 0
                      AND EXISTS (
                          SELECT 1
                          FROM sales_order_reservation_generations AS generation
                          WHERE generation.company_id = order_line.company_id
                            AND generation.sales_order_id = order_line.sales_order_id
                            AND generation.logical_line_key = order_line.logical_line_key
                            AND generation.released_at IS NULL
                      )
                  )
              )
        ) THEN
            RAISE EXCEPTION 'dispatch cancellation requires exact reopened sales-order reservation state' USING ERRCODE = '23514';
        END IF;

        RETURN NEW;
    END IF;

    IF OLD.status = NEW.status THEN
        RETURN NEW;
    END IF;

    IF OLD.status <> 'draft' OR NEW.status <> 'finalized' THEN
        RAISE EXCEPTION 'invalid dispatch status transition' USING ERRCODE = '23514';
    END IF;

    IF NEW.finalized_at IS NULL OR NEW.cancelled_at IS NOT NULL THEN
        RAISE EXCEPTION 'finalized dispatch requires finalized_at and no cancelled_at' USING ERRCODE = '23514';
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
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_dispatch_reversal_commit()
RETURNS trigger AS $$
DECLARE
    dispatch_status text;
BEGIN
    IF TG_TABLE_NAME = 'stock_movements' THEN
        IF NEW.source_type <> 'dispatch_line' OR NEW.effect_type <> 'stock.out.reverse' THEN
            RETURN NEW;
        END IF;
    ELSE
        IF NEW.source_type <> 'dispatch_line'
           OR NEW.effect_type <> 'progress.dispatch.reverse'
           OR NEW.progress_type <> 'dispatched' THEN
            RETURN NEW;
        END IF;
    END IF;

    IF NEW.source_id !~ '^[1-9][0-9]*$' THEN
        RAISE EXCEPTION 'dispatch reversal source identity is invalid' USING ERRCODE = '23514';
    END IF;

    SELECT dispatch.status
    INTO dispatch_status
    FROM dispatch_lines AS line
    INNER JOIN dispatches AS dispatch
      ON dispatch.company_id = line.company_id
     AND dispatch.id = line.dispatch_id
    WHERE line.company_id = NEW.company_id
      AND line.id = CAST(NEW.source_id AS bigint);

    IF dispatch_status IS DISTINCT FROM 'cancelled' THEN
        RAISE EXCEPTION 'dispatch reversal must commit with its source dispatch cancelled' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER dispatch_stock_reversal_commit_guard
AFTER INSERT ON stock_movements
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_dispatch_reversal_commit();

CREATE CONSTRAINT TRIGGER dispatch_progress_reversal_commit_guard
AFTER INSERT ON sales_order_line_progress_effects
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_dispatch_reversal_commit();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS dispatch_stock_reversal_commit_guard ON stock_movements');
        DB::statement('DROP TRIGGER IF EXISTS dispatch_progress_reversal_commit_guard ON sales_order_line_progress_effects');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_dispatch_reversal_commit()');

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

    IF NOT EXISTS (SELECT 1 FROM dispatch_lines WHERE company_id = OLD.company_id AND dispatch_id = OLD.id) THEN
        RAISE EXCEPTION 'dispatch finalization requires at least one line' USING ERRCODE = '23514';
    END IF;

    IF EXISTS (
        SELECT 1 FROM dispatch_lines AS line
        WHERE line.company_id = OLD.company_id
          AND line.dispatch_id = OLD.id
          AND (
              NOT EXISTS (
                  SELECT 1 FROM stock_movements AS movement
                  WHERE movement.company_id = line.company_id
                    AND movement.source_type = 'dispatch_line'
                    AND movement.source_id = line.id::text
                    AND movement.effect_type = 'stock.out'
                    AND movement.movement_type = 'dispatch_out'
                    AND movement.quantity_delta = -line.quantity
              )
              OR NOT EXISTS (
                  SELECT 1 FROM sales_order_line_progress_effects AS effect
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
SQL);

        DB::statement('ALTER TABLE dispatches DROP CONSTRAINT IF EXISTS dispatches_lifecycle_timestamp_check');
        DB::statement('ALTER TABLE dispatches DROP CONSTRAINT IF EXISTS dispatches_status_check');
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM dispatches WHERE status = 'cancelled') THEN
        ALTER TABLE dispatches ADD CONSTRAINT dispatches_status_check CHECK (status IN ('draft', 'finalized', 'cancelled'));
    ELSE
        ALTER TABLE dispatches ADD CONSTRAINT dispatches_status_check CHECK (status IN ('draft', 'finalized'));
    END IF;
END $$;
SQL);
        DB::statement("ALTER TABLE dispatches ADD CONSTRAINT dispatches_finalized_at_check CHECK ((status = 'draft' AND finalized_at IS NULL) OR (status IN ('finalized', 'cancelled') AND finalized_at IS NOT NULL))");

        Schema::table('dispatches', function (Blueprint $table): void {
            $table->dropColumn('cancelled_at');
        });
    }
};
