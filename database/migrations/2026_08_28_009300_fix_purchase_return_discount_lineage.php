<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_return_source()
RETURNS trigger AS $$
DECLARE
    source_order purchase_orders%ROWTYPE;
    supplier_type text;
    supplier_status text;
    mismatched_invoice_id bigint;
BEGIN
    SELECT * INTO source_order
    FROM purchase_orders
    WHERE company_id = NEW.company_id AND id = NEW.purchase_order_id
    FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'purchase return source purchase order not found' USING ERRCODE = '23503';
    END IF;

    IF NEW.account_id IS DISTINCT FROM source_order.account_id
       OR NEW.currency_code IS DISTINCT FROM source_order.currency_code THEN
        RAISE EXCEPTION 'purchase return supplier/currency must match source purchase order' USING ERRCODE = '23514';
    END IF;

    SELECT line.supplier_invoice_id INTO mismatched_invoice_id
    FROM purchase_return_lines AS line
    INNER JOIN supplier_invoices AS invoice
      ON invoice.company_id = line.company_id
     AND invoice.id = line.supplier_invoice_id
    WHERE line.company_id = NEW.company_id
      AND line.purchase_return_id = NEW.id
      AND invoice.document_discount_rate IS DISTINCT FROM NEW.document_discount_rate
    LIMIT 1;

    IF mismatched_invoice_id IS NOT NULL THEN
        RAISE EXCEPTION 'purchase return discount must match source supplier invoice snapshot' USING ERRCODE = '23514';
    END IF;

    SELECT type, status INTO supplier_type, supplier_status
    FROM accounts
    WHERE company_id = NEW.company_id AND id = NEW.account_id
    FOR SHARE;
    IF supplier_status IS DISTINCT FROM 'active' OR supplier_type NOT IN ('supplier', 'mixed') THEN
        RAISE EXCEPTION 'purchase return requires active supplier or mixed account' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_return_invoice_discount_lineage()
RETURNS trigger AS $$
DECLARE
    parent_discount numeric(9, 6);
    invoice_discount numeric(9, 6);
BEGIN
    SELECT document_discount_rate INTO parent_discount
    FROM purchase_returns
    WHERE company_id = NEW.company_id AND id = NEW.purchase_return_id
    FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'purchase return parent not found for invoice discount lineage' USING ERRCODE = '23503';
    END IF;

    SELECT document_discount_rate INTO invoice_discount
    FROM supplier_invoices
    WHERE company_id = NEW.company_id AND id = NEW.supplier_invoice_id
    FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'purchase return source supplier invoice not found for discount lineage' USING ERRCODE = '23503';
    END IF;

    IF parent_discount IS DISTINCT FROM invoice_discount THEN
        RAISE EXCEPTION 'purchase return discount must match source supplier invoice snapshot' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER purchase_return_lines_invoice_discount_lineage_guard
BEFORE INSERT OR UPDATE OF company_id, purchase_return_id, supplier_invoice_id ON purchase_return_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_return_invoice_discount_lineage();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS purchase_return_lines_invoice_discount_lineage_guard ON purchase_return_lines;
DROP FUNCTION IF EXISTS mars_guard_purchase_return_invoice_discount_lineage();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_return_source()
RETURNS trigger AS $$
DECLARE
    source_order purchase_orders%ROWTYPE;
    supplier_type text;
    supplier_status text;
BEGIN
    SELECT * INTO source_order
    FROM purchase_orders
    WHERE company_id = NEW.company_id AND id = NEW.purchase_order_id
    FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'purchase return source purchase order not found' USING ERRCODE = '23503';
    END IF;
    IF NEW.account_id IS DISTINCT FROM source_order.account_id
       OR NEW.currency_code IS DISTINCT FROM source_order.currency_code
       OR NEW.document_discount_rate IS DISTINCT FROM source_order.document_discount_rate THEN
        RAISE EXCEPTION 'purchase return supplier/currency/discount must match source purchase order' USING ERRCODE = '23514';
    END IF;

    SELECT type, status INTO supplier_type, supplier_status
    FROM accounts
    WHERE company_id = NEW.company_id AND id = NEW.account_id
    FOR SHARE;
    IF supplier_status IS DISTINCT FROM 'active' OR supplier_type NOT IN ('supplier', 'mixed') THEN
        RAISE EXCEPTION 'purchase return requires active supplier or mixed account' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }
};
