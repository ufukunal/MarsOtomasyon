<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->unique(
                ['company_id', 'sales_order_id', 'id'],
                'sales_order_lines_company_order_id_unique',
            );
        });

        Schema::create('sales_order_line_progress_effects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('sales_order_line_id');
            $table->string('progress_type', 16);
            $table->decimal('quantity_delta', 20, 6);
            $table->char('operation_key', 64);
            $table->char('request_fingerprint', 64);
            $table->string('source_type', 100);
            $table->string('source_id', 255);
            $table->string('effect_type', 100);
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'sales_order_id'], 'sales_order_progress_effects_order_fk')
                ->references(['company_id', 'id'])->on('sales_orders')->restrictOnDelete();
            $table->foreign(
                ['company_id', 'sales_order_id', 'sales_order_line_id'],
                'sales_order_progress_effects_line_fk',
            )->references(['company_id', 'sales_order_id', 'id'])->on('sales_order_lines')->restrictOnDelete();
            $table->unique('operation_key', 'sales_order_progress_effects_operation_unique');
            $table->unique(
                ['company_id', 'source_type', 'source_id', 'effect_type'],
                'sales_order_progress_effects_source_unique',
            );
            $table->index(
                ['company_id', 'sales_order_id', 'sales_order_line_id', 'progress_type'],
                'sales_order_progress_effects_line_type_index',
            );
        });

        DB::statement("ALTER TABLE sales_order_line_progress_effects ADD CONSTRAINT sales_order_progress_effects_type_check CHECK (progress_type IN ('dispatched', 'invoiced', 'cancelled'))");
        DB::statement('ALTER TABLE sales_order_line_progress_effects ADD CONSTRAINT sales_order_progress_effects_quantity_check CHECK (quantity_delta <> 0)');
        DB::statement("ALTER TABLE sales_order_line_progress_effects ADD CONSTRAINT sales_order_progress_effects_operation_key_check CHECK (operation_key ~ '^[a-f0-9]{64}$')");
        DB::statement("ALTER TABLE sales_order_line_progress_effects ADD CONSTRAINT sales_order_progress_effects_request_fingerprint_check CHECK (request_fingerprint ~ '^[a-f0-9]{64}$')");
        DB::statement("ALTER TABLE sales_order_line_progress_effects ADD CONSTRAINT sales_order_progress_effects_source_type_check CHECK (source_type ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE sales_order_line_progress_effects ADD CONSTRAINT sales_order_progress_effects_effect_type_check CHECK (effect_type ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE sales_order_line_progress_effects ADD CONSTRAINT sales_order_progress_effects_source_id_check CHECK (source_id <> '' AND source_id = btrim(source_id))");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_order_line_progress_effect()
RETURNS trigger AS $$
DECLARE
    ordered numeric(20, 6);
    dispatched numeric(20, 6);
    invoiced numeric(20, 6);
    cancelled numeric(20, 6);
BEGIN
    IF TG_OP <> 'INSERT' THEN
        RAISE EXCEPTION 'sales order progress effects are append-only' USING ERRCODE = '55000';
    END IF;

    SELECT quantity INTO ordered
    FROM sales_order_lines
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.sales_order_id
      AND id = NEW.sales_order_line_id
    FOR UPDATE;

    IF ordered IS NULL THEN
        RAISE EXCEPTION 'sales order progress line not found' USING ERRCODE = '23503';
    END IF;

    SELECT
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'dispatched'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'invoiced'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'cancelled'), 0)
    INTO dispatched, invoiced, cancelled
    FROM sales_order_line_progress_effects
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.sales_order_id
      AND sales_order_line_id = NEW.sales_order_line_id;

    IF NEW.progress_type = 'dispatched' THEN
        dispatched := dispatched + NEW.quantity_delta;
    ELSIF NEW.progress_type = 'invoiced' THEN
        invoiced := invoiced + NEW.quantity_delta;
    ELSE
        cancelled := cancelled + NEW.quantity_delta;
    END IF;

    IF dispatched < 0 OR invoiced < 0 OR cancelled < 0 THEN
        RAISE EXCEPTION 'sales order net progress cannot be negative' USING ERRCODE = '23514';
    END IF;

    IF dispatched + cancelled > ordered THEN
        RAISE EXCEPTION 'sales order dispatch progress exceeds ordered quantity' USING ERRCODE = '23514';
    END IF;

    IF invoiced + cancelled > ordered THEN
        RAISE EXCEPTION 'sales order invoice progress exceeds ordered quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_order_progress_effects_guard
BEFORE INSERT OR UPDATE OR DELETE ON sales_order_line_progress_effects
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_order_line_progress_effect();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_order_line_after_progress()
RETURNS trigger AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM sales_order_line_progress_effects
        WHERE company_id = OLD.company_id
          AND sales_order_id = OLD.sales_order_id
          AND sales_order_line_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'sales order line is immutable after progress starts' USING ERRCODE = '55000';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_order_lines_progress_guard
BEFORE UPDATE OR DELETE ON sales_order_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_order_line_after_progress();
SQL);

        DB::unprepared(<<<'SQL'
CREATE VIEW sales_order_line_progress AS
WITH progress AS (
    SELECT
        company_id,
        sales_order_id,
        sales_order_line_id,
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'dispatched'), 0)::numeric(20, 6) AS net_dispatched_quantity,
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'invoiced'), 0)::numeric(20, 6) AS net_invoiced_quantity,
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'cancelled'), 0)::numeric(20, 6) AS cancelled_quantity
    FROM sales_order_line_progress_effects
    GROUP BY company_id, sales_order_id, sales_order_line_id
)
SELECT
    line.company_id,
    line.sales_order_id,
    line.id AS sales_order_line_id,
    line.quantity::numeric(20, 6) AS ordered_quantity,
    COALESCE(progress.net_dispatched_quantity, 0)::numeric(20, 6) AS net_dispatched_quantity,
    COALESCE(progress.net_invoiced_quantity, 0)::numeric(20, 6) AS net_invoiced_quantity,
    COALESCE(progress.cancelled_quantity, 0)::numeric(20, 6) AS cancelled_quantity,
    (
        line.quantity
        - COALESCE(progress.cancelled_quantity, 0)
        - COALESCE(progress.net_dispatched_quantity, 0)
    )::numeric(20, 6) AS dispatch_remaining_quantity,
    (
        line.quantity
        - COALESCE(progress.cancelled_quantity, 0)
        - COALESCE(progress.net_invoiced_quantity, 0)
    )::numeric(20, 6) AS invoice_remaining_quantity,
    (
        line.quantity
        - COALESCE(progress.cancelled_quantity, 0)
        - GREATEST(
            COALESCE(progress.net_dispatched_quantity, 0),
            COALESCE(progress.net_invoiced_quantity, 0)
        )
    )::numeric(20, 6) AS remaining_quantity
FROM sales_order_lines AS line
LEFT JOIN progress
  ON progress.company_id = line.company_id
 AND progress.sales_order_id = line.sales_order_id
 AND progress.sales_order_line_id = line.id
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS sales_order_line_progress');
        DB::statement('DROP TRIGGER IF EXISTS sales_order_lines_progress_guard ON sales_order_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_order_line_after_progress()');
        DB::statement('DROP TRIGGER IF EXISTS sales_order_progress_effects_guard ON sales_order_line_progress_effects');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_order_line_progress_effect()');
        Schema::dropIfExists('sales_order_line_progress_effects');

        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->dropUnique('sales_order_lines_company_order_id_unique');
        });
    }
};
