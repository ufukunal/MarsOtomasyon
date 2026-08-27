<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_return_progress_finalization()
RETURNS trigger AS $$
BEGIN
    IF OLD.status <> 'draft' OR NEW.status <> 'finalized' THEN
        RETURN NEW;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM purchase_return_lines AS line
        WHERE line.company_id = NEW.company_id
          AND line.purchase_return_id = NEW.id
          AND NOT EXISTS (
              SELECT 1
              FROM purchase_order_line_progress_effects AS effect
              WHERE effect.company_id = line.company_id
                AND effect.purchase_order_id = line.purchase_order_id
                AND effect.purchase_order_line_id = line.purchase_order_line_id
                AND effect.progress_type = 'received'
                AND effect.quantity_delta = -line.quantity
                AND effect.source_type = 'purchase_return_line'
                AND effect.source_id = line.id::text
                AND effect.effect_type = 'progress.receive.return'
          )
    ) THEN
        RAISE EXCEPTION 'finalized purchase return requires exact negative received progress effect for every line' USING ERRCODE = '23514';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM purchase_return_lines AS line
        WHERE line.company_id = NEW.company_id
          AND line.purchase_return_id = NEW.id
          AND NOT EXISTS (
              SELECT 1
              FROM purchase_order_line_progress_effects AS effect
              WHERE effect.company_id = line.company_id
                AND effect.purchase_order_id = line.purchase_order_id
                AND effect.purchase_order_line_id = line.purchase_order_line_id
                AND effect.progress_type = 'invoiced'
                AND effect.quantity_delta = -line.quantity
                AND effect.source_type = 'purchase_return_line'
                AND effect.source_id = line.id::text
                AND effect.effect_type = 'progress.invoice.return'
          )
    ) THEN
        RAISE EXCEPTION 'finalized purchase return requires exact negative invoiced progress effect for every line' USING ERRCODE = '23514';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM purchase_return_lines AS line
        WHERE line.company_id = NEW.company_id
          AND line.purchase_return_id = NEW.id
          AND 2 <> (
              SELECT COUNT(*)
              FROM purchase_order_line_progress_effects AS effect
              WHERE effect.company_id = line.company_id
                AND effect.source_type = 'purchase_return_line'
                AND effect.source_id = line.id::text
          )
    ) THEN
        RAISE EXCEPTION 'finalized purchase return requires exactly two purchase-order progress effects per line' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER purchase_returns_progress_finalization_guard
AFTER UPDATE ON purchase_returns
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_return_progress_finalization();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS purchase_returns_progress_finalization_guard ON purchase_returns');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_return_progress_finalization()');
    }
};
