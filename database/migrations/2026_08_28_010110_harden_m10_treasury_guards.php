<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_treasury_balance_mutation()
RETURNS trigger AS $$
BEGIN
    IF current_setting('mars.treasury_projection', true) IS DISTINCT FROM '1' THEN
        RAISE EXCEPTION 'treasury_balances are projection-only' USING ERRCODE = '55000';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION mars_guard_cash_count_line()
RETURNS trigger AS $$
DECLARE
    parent_status text;
BEGIN
    IF TG_OP = 'DELETE' THEN
        SELECT status INTO parent_status FROM treasury_cash_counts
         WHERE company_id = OLD.company_id AND id = OLD.treasury_cash_count_id;
    ELSE
        SELECT status INTO parent_status FROM treasury_cash_counts
         WHERE company_id = NEW.company_id AND id = NEW.treasury_cash_count_id;
    END IF;
    IF parent_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'cash count lines require draft parent' USING ERRCODE = '55000';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION mars_prevent_finalized_treasury_document_delete()
RETURNS trigger AS $$
BEGIN
    IF OLD.status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'finalized treasury documents are immutable' USING ERRCODE = '55000';
    END IF;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER treasury_payments_finalized_delete_guard
BEFORE DELETE ON treasury_payments
FOR EACH ROW EXECUTE FUNCTION mars_prevent_finalized_treasury_document_delete();

CREATE TRIGGER treasury_manual_movements_finalized_delete_guard
BEFORE DELETE ON treasury_manual_movements
FOR EACH ROW EXECUTE FUNCTION mars_prevent_finalized_treasury_document_delete();

CREATE TRIGGER treasury_transfers_finalized_delete_guard
BEFORE DELETE ON treasury_transfers
FOR EACH ROW EXECUTE FUNCTION mars_prevent_finalized_treasury_document_delete();

CREATE TRIGGER treasury_expenses_finalized_delete_guard
BEFORE DELETE ON treasury_expenses
FOR EACH ROW EXECUTE FUNCTION mars_prevent_finalized_treasury_document_delete();

CREATE TRIGGER treasury_cash_counts_finalized_delete_guard
BEFORE DELETE ON treasury_cash_counts
FOR EACH ROW EXECUTE FUNCTION mars_prevent_finalized_treasury_document_delete();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS treasury_cash_counts_finalized_delete_guard ON treasury_cash_counts');
        DB::statement('DROP TRIGGER IF EXISTS treasury_expenses_finalized_delete_guard ON treasury_expenses');
        DB::statement('DROP TRIGGER IF EXISTS treasury_transfers_finalized_delete_guard ON treasury_transfers');
        DB::statement('DROP TRIGGER IF EXISTS treasury_manual_movements_finalized_delete_guard ON treasury_manual_movements');
        DB::statement('DROP TRIGGER IF EXISTS treasury_payments_finalized_delete_guard ON treasury_payments');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_finalized_treasury_document_delete()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_treasury_balance_mutation()
RETURNS trigger AS $$
BEGIN
    IF current_setting('mars.treasury_projection', true) IS DISTINCT FROM '1' THEN
        RAISE EXCEPTION 'treasury_balances are projection-only' USING ERRCODE = '55000';
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION mars_guard_cash_count_line()
RETURNS trigger AS $$
DECLARE
    parent_status text;
BEGIN
    IF TG_OP = 'DELETE' THEN
        SELECT status INTO parent_status FROM treasury_cash_counts
         WHERE company_id = OLD.company_id AND id = OLD.treasury_cash_count_id;
    ELSE
        SELECT status INTO parent_status FROM treasury_cash_counts
         WHERE company_id = NEW.company_id AND id = NEW.treasury_cash_count_id;
    END IF;
    IF parent_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'cash count lines require draft parent' USING ERRCODE = '55000';
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;
SQL);
    }
};
