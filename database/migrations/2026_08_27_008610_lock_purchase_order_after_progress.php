<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_mutation()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'purchase orders cannot be deleted' USING ERRCODE = '55000';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM purchase_order_line_progress_effects
        WHERE company_id = OLD.company_id
          AND purchase_order_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'purchase order is immutable after progress starts' USING ERRCODE = '55000';
    END IF;

    IF OLD.status <> 'draft' OR NEW.status <> 'draft' THEN
        RAISE EXCEPTION 'only draft purchase orders are mutable' USING ERRCODE = '55000';
    END IF;

    IF NEW.company_id IS DISTINCT FROM OLD.company_id
        OR NEW.number IS DISTINCT FROM OLD.number
        OR NEW.series_code IS DISTINCT FROM OLD.series_code
        OR NEW.sequence_value IS DISTINCT FROM OLD.sequence_value THEN
        RAISE EXCEPTION 'purchase order document identity is immutable' USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_mutation()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'purchase orders cannot be deleted' USING ERRCODE = '55000';
    END IF;

    IF OLD.status <> 'draft' OR NEW.status <> 'draft' THEN
        RAISE EXCEPTION 'only draft purchase orders are mutable' USING ERRCODE = '55000';
    END IF;

    IF NEW.company_id IS DISTINCT FROM OLD.company_id
        OR NEW.number IS DISTINCT FROM OLD.number
        OR NEW.series_code IS DISTINCT FROM OLD.series_code
        OR NEW.sequence_value IS DISTINCT FROM OLD.sequence_value THEN
        RAISE EXCEPTION 'purchase order document identity is immutable' USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }
};
