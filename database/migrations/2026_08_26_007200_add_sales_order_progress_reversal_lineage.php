<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_line_progress_effects', function (Blueprint $table): void {
            $table->unsignedBigInteger('reversal_of_progress_effect_id')->nullable();
            $table->foreign('reversal_of_progress_effect_id', 'sales_order_progress_effects_reversal_fk')
                ->references('id')->on('sales_order_line_progress_effects')->restrictOnDelete();
            $table->unique('reversal_of_progress_effect_id', 'sales_order_progress_effects_reversal_unique');
        });

        DB::statement(<<<'SQL'
ALTER TABLE sales_order_line_progress_effects
ADD CONSTRAINT sales_order_progress_effects_reversal_shape_check
CHECK (
    (quantity_delta > 0 AND reversal_of_progress_effect_id IS NULL)
    OR
    (quantity_delta < 0 AND reversal_of_progress_effect_id IS NOT NULL)
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_order_line_progress_effect()
RETURNS trigger AS $$
DECLARE
    ordered numeric(20, 6);
    dispatched numeric(20, 6);
    invoiced numeric(20, 6);
    cancelled numeric(20, 6);
    original_company_id bigint;
    original_sales_order_id bigint;
    original_sales_order_line_id bigint;
    original_progress_type text;
    original_quantity_delta numeric(20, 6);
    original_reversal_id bigint;
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

    IF NEW.quantity_delta < 0 THEN
        SELECT
            company_id,
            sales_order_id,
            sales_order_line_id,
            progress_type,
            quantity_delta,
            reversal_of_progress_effect_id
        INTO
            original_company_id,
            original_sales_order_id,
            original_sales_order_line_id,
            original_progress_type,
            original_quantity_delta,
            original_reversal_id
        FROM sales_order_line_progress_effects
        WHERE id = NEW.reversal_of_progress_effect_id
        FOR UPDATE;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'sales order reversal original effect not found' USING ERRCODE = '23514';
        END IF;

        IF original_quantity_delta <= 0 OR original_reversal_id IS NOT NULL THEN
            RAISE EXCEPTION 'a sales order reversal cannot itself be reversed' USING ERRCODE = '23514';
        END IF;

        IF original_company_id <> NEW.company_id
            OR original_sales_order_id <> NEW.sales_order_id
            OR original_sales_order_line_id <> NEW.sales_order_line_id
            OR original_progress_type <> NEW.progress_type THEN
            RAISE EXCEPTION 'sales order reversal must preserve original progress scope' USING ERRCODE = '23514';
        END IF;

        IF NEW.quantity_delta <> -original_quantity_delta THEN
            RAISE EXCEPTION 'sales order reversal must exactly negate original quantity' USING ERRCODE = '23514';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM sales_order_line_progress_effects
            WHERE reversal_of_progress_effect_id = NEW.reversal_of_progress_effect_id
        ) THEN
            RAISE EXCEPTION 'sales order progress effect is already reversed' USING ERRCODE = '23514';
        END IF;
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
SQL);
    }

    public function down(): void
    {
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
SQL);

        DB::statement('ALTER TABLE sales_order_line_progress_effects DROP CONSTRAINT sales_order_progress_effects_reversal_shape_check');

        Schema::table('sales_order_line_progress_effects', function (Blueprint $table): void {
            $table->dropUnique('sales_order_progress_effects_reversal_unique');
            $table->dropForeign('sales_order_progress_effects_reversal_fk');
            $table->dropColumn('reversal_of_progress_effect_id');
        });
    }
};
