<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->timestampTz('finalized_at')->nullable()->after('status');
            $table->timestampTz('cancelled_at')->nullable()->after('finalized_at');
        });

        DB::statement('ALTER TABLE sales_invoices DROP CONSTRAINT sales_invoices_status_check');
        DB::statement("ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_status_check CHECK (status IN ('draft', 'finalized', 'cancelled'))");
        DB::statement(<<<'SQL'
ALTER TABLE sales_invoices
ADD CONSTRAINT sales_invoices_lifecycle_timestamps_check CHECK (
    (status = 'draft' AND finalized_at IS NULL AND cancelled_at IS NULL)
    OR (status = 'finalized' AND finalized_at IS NOT NULL AND cancelled_at IS NULL)
    OR (status = 'cancelled' AND finalized_at IS NOT NULL AND cancelled_at IS NOT NULL)
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_account_lifecycle()
RETURNS trigger AS $$
DECLARE
    original_transaction_id bigint;
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status <> 'draft' THEN
            RAISE EXCEPTION 'finalized sales invoice is immutable' USING ERRCODE = '55000';
        END IF;

        RETURN OLD;
    END IF;

    IF OLD.status = NEW.status THEN
        IF OLD.status <> 'draft' THEN
            RAISE EXCEPTION 'finalized sales invoice is immutable' USING ERRCODE = '55000';
        END IF;

        RETURN NEW;
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'finalized' THEN
        IF NEW.finalized_at IS NULL OR NEW.cancelled_at IS NOT NULL THEN
            RAISE EXCEPTION 'sales invoice finalization timestamps are invalid' USING ERRCODE = '23514';
        END IF;

        IF (to_jsonb(NEW) - 'status' - 'finalized_at' - 'updated_at') IS DISTINCT FROM
           (to_jsonb(OLD) - 'status' - 'finalized_at' - 'updated_at') THEN
            RAISE EXCEPTION 'sales invoice finalization may only change finalization fields' USING ERRCODE = '23514';
        END IF;

        SELECT transaction.id
        INTO original_transaction_id
        FROM account_transactions AS transaction
        WHERE transaction.company_id = OLD.company_id
          AND transaction.account_id = OLD.account_id
          AND transaction.posting_date = OLD.invoice_date
          AND transaction.currency_code = OLD.currency_code
          AND transaction.signed_amount = OLD.gross_total
          AND transaction.source_type = 'sales_invoice'
          AND transaction.source_id = OLD.id::text
          AND transaction.effect_type = 'account.sales_invoice'
          AND transaction.reversal_of_transaction_id IS NULL;

        IF original_transaction_id IS NULL THEN
            RAISE EXCEPTION 'sales invoice finalization requires exact account effect' USING ERRCODE = '23514';
        END IF;

        RETURN NEW;
    END IF;

    IF OLD.status = 'finalized' AND NEW.status = 'cancelled' THEN
        IF OLD.finalized_at IS NULL OR NEW.finalized_at IS DISTINCT FROM OLD.finalized_at OR NEW.cancelled_at IS NULL THEN
            RAISE EXCEPTION 'sales invoice cancellation timestamps are invalid' USING ERRCODE = '23514';
        END IF;

        IF (to_jsonb(NEW) - 'status' - 'cancelled_at' - 'updated_at') IS DISTINCT FROM
           (to_jsonb(OLD) - 'status' - 'cancelled_at' - 'updated_at') THEN
            RAISE EXCEPTION 'sales invoice cancellation may only change cancellation fields' USING ERRCODE = '23514';
        END IF;

        SELECT transaction.id
        INTO original_transaction_id
        FROM account_transactions AS transaction
        WHERE transaction.company_id = OLD.company_id
          AND transaction.account_id = OLD.account_id
          AND transaction.posting_date = OLD.invoice_date
          AND transaction.currency_code = OLD.currency_code
          AND transaction.signed_amount = OLD.gross_total
          AND transaction.source_type = 'sales_invoice'
          AND transaction.source_id = OLD.id::text
          AND transaction.effect_type = 'account.sales_invoice'
          AND transaction.reversal_of_transaction_id IS NULL;

        IF original_transaction_id IS NULL OR NOT EXISTS (
            SELECT 1
            FROM account_transactions AS reversal
            WHERE reversal.company_id = OLD.company_id
              AND reversal.account_id = OLD.account_id
              AND reversal.currency_code = OLD.currency_code
              AND reversal.signed_amount = -OLD.gross_total
              AND reversal.source_type = 'sales_invoice'
              AND reversal.source_id = OLD.id::text
              AND reversal.effect_type = 'account.sales_invoice.reverse'
              AND reversal.reversal_of_transaction_id = original_transaction_id
        ) THEN
            RAISE EXCEPTION 'sales invoice cancellation requires exact account reversal' USING ERRCODE = '23514';
        END IF;

        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'invalid sales invoice status transition' USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_invoice_account_lifecycle_guard
BEFORE UPDATE OR DELETE ON sales_invoices
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_account_lifecycle();

CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_progress_lifecycle_commit()
RETURNS trigger AS $$
BEGIN
    IF OLD.status = 'draft' AND NEW.status = 'finalized' THEN
        IF EXISTS (
            SELECT 1
            FROM sales_invoice_lines AS line
            WHERE line.company_id = NEW.company_id
              AND line.sales_invoice_id = NEW.id
              AND line.source_sales_order_line_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM sales_order_line_progress_effects AS effect
                  WHERE effect.company_id = line.company_id
                    AND effect.sales_order_id = line.source_sales_order_id
                    AND effect.sales_order_line_id = line.source_sales_order_line_id
                    AND effect.progress_type = 'invoiced'
                    AND effect.quantity_delta = line.quantity
                    AND effect.reversal_of_progress_effect_id IS NULL
                    AND effect.source_type = 'sales_invoice_line'
                    AND effect.source_id = line.id::text
                    AND effect.effect_type = 'progress.invoice'
              )
        ) THEN
            RAISE EXCEPTION 'linked sales invoice finalization requires exact order progress effects' USING ERRCODE = '23514';
        END IF;
    ELSIF OLD.status = 'finalized' AND NEW.status = 'cancelled' THEN
        IF EXISTS (
            SELECT 1
            FROM sales_invoice_lines AS line
            WHERE line.company_id = NEW.company_id
              AND line.sales_invoice_id = NEW.id
              AND line.source_sales_order_line_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM sales_order_line_progress_effects AS original
                  INNER JOIN sales_order_line_progress_effects AS reversal
                    ON reversal.reversal_of_progress_effect_id = original.id
                  WHERE original.company_id = line.company_id
                    AND original.sales_order_id = line.source_sales_order_id
                    AND original.sales_order_line_id = line.source_sales_order_line_id
                    AND original.progress_type = 'invoiced'
                    AND original.quantity_delta = line.quantity
                    AND original.reversal_of_progress_effect_id IS NULL
                    AND original.source_type = 'sales_invoice_line'
                    AND original.source_id = line.id::text
                    AND original.effect_type = 'progress.invoice'
                    AND reversal.company_id = line.company_id
                    AND reversal.sales_order_id = line.source_sales_order_id
                    AND reversal.sales_order_line_id = line.source_sales_order_line_id
                    AND reversal.progress_type = 'invoiced'
                    AND reversal.quantity_delta = -line.quantity
                    AND reversal.source_type = 'sales_invoice_line'
                    AND reversal.source_id = line.id::text
                    AND reversal.effect_type = 'progress.invoice.reverse'
              )
        ) THEN
            RAISE EXCEPTION 'linked sales invoice cancellation requires exact order progress reversals' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER sales_invoice_progress_lifecycle_commit_guard
AFTER UPDATE ON sales_invoices
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_progress_lifecycle_commit();

CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_line_lifecycle()
RETURNS trigger AS $$
DECLARE
    parent_status text;
BEGIN
    IF TG_OP IN ('UPDATE', 'DELETE') THEN
        SELECT status INTO parent_status
        FROM sales_invoices
        WHERE company_id = OLD.company_id AND id = OLD.sales_invoice_id;

        IF parent_status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'finalized sales invoice lines are immutable' USING ERRCODE = '55000';
        END IF;
    END IF;

    IF TG_OP IN ('INSERT', 'UPDATE') THEN
        SELECT status INTO parent_status
        FROM sales_invoices
        WHERE company_id = NEW.company_id AND id = NEW.sales_invoice_id;

        IF parent_status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'sales invoice lines may only belong to a draft invoice' USING ERRCODE = '55000';
        END IF;
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_invoice_line_lifecycle_guard
BEFORE INSERT OR UPDATE OR DELETE ON sales_invoice_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_line_lifecycle();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_line_lifecycle_guard ON sales_invoice_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_line_lifecycle()');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_progress_lifecycle_commit_guard ON sales_invoices');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_progress_lifecycle_commit()');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_account_lifecycle_guard ON sales_invoices');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_account_lifecycle()');
        DB::statement('ALTER TABLE sales_invoices DROP CONSTRAINT IF EXISTS sales_invoices_lifecycle_timestamps_check');
        DB::statement('ALTER TABLE sales_invoices DROP CONSTRAINT IF EXISTS sales_invoices_status_check');
        DB::statement("UPDATE sales_invoices SET status = 'draft', finalized_at = NULL, cancelled_at = NULL");
        DB::statement("ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_status_check CHECK (status = 'draft')");

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropColumn(['cancelled_at', 'finalized_at']);
        });
    }
};
