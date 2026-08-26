<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_dispatch_progress_finalization_commit()
RETURNS trigger AS $$
DECLARE
    dispatch_status text;
BEGIN
    IF NEW.source_type <> 'dispatch_line'
       OR NEW.effect_type <> 'progress.dispatch'
       OR NEW.progress_type <> 'dispatched' THEN
        RETURN NEW;
    END IF;

    SELECT dispatch.status
    INTO dispatch_status
    FROM dispatch_lines AS line
    INNER JOIN dispatches AS dispatch
      ON dispatch.company_id = line.company_id
     AND dispatch.id = line.dispatch_id
    WHERE line.company_id = NEW.company_id
      AND line.sales_order_id = NEW.sales_order_id
      AND line.sales_order_line_id = NEW.sales_order_line_id
      AND line.id = CAST(NEW.source_id AS bigint);

    IF dispatch_status IS DISTINCT FROM 'finalized' THEN
        RAISE EXCEPTION 'dispatch progress must commit with its source dispatch finalized' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER dispatch_progress_finalization_commit_guard
AFTER INSERT ON sales_order_line_progress_effects
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_dispatch_progress_finalization_commit();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS dispatch_progress_finalization_commit_guard ON sales_order_line_progress_effects');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_dispatch_progress_finalization_commit()');
    }
};
