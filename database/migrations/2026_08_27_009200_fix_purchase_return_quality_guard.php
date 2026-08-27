<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_return_line_source()
RETURNS trigger AS $$
DECLARE
    parent purchase_returns%ROWTYPE;
    receipt_line goods_receipt_lines%ROWTYPE;
    invoice_line supplier_invoice_lines%ROWTYPE;
    receipt_status text;
    invoice_status text;
    invoice_account_id bigint;
    invoice_currency text;
    accepted_custody_quantity numeric(20, 6);
BEGIN
    SELECT * INTO parent
    FROM purchase_returns
    WHERE company_id = NEW.company_id AND id = NEW.purchase_return_id
    FOR SHARE;
    IF NOT FOUND OR parent.status <> 'draft' THEN
        RAISE EXCEPTION 'purchase return lines require draft parent' USING ERRCODE = '55000';
    END IF;

    SELECT * INTO receipt_line
    FROM goods_receipt_lines
    WHERE company_id = NEW.company_id
      AND goods_receipt_id = NEW.goods_receipt_id
      AND id = NEW.goods_receipt_line_id
    FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'purchase return goods receipt source line not found' USING ERRCODE = '23503';
    END IF;

    SELECT status INTO receipt_status
    FROM goods_receipts
    WHERE company_id = NEW.company_id AND id = NEW.goods_receipt_id
    FOR SHARE;
    IF receipt_status IS DISTINCT FROM 'finalized' THEN
        RAISE EXCEPTION 'purchase return requires finalized goods receipt lineage' USING ERRCODE = '23514';
    END IF;

    SELECT quality.accepted_quantity INTO accepted_custody_quantity
    FROM goods_receipt_line_quality AS quality
    WHERE quality.company_id = NEW.company_id
      AND quality.goods_receipt_line_id = NEW.goods_receipt_line_id;
    IF accepted_custody_quantity IS NULL
       OR accepted_custody_quantity <= 0
       OR NEW.quantity > accepted_custody_quantity THEN
        RAISE EXCEPTION 'purchase return quantity requires accepted goods receipt custody' USING ERRCODE = '23514';
    END IF;

    SELECT * INTO invoice_line
    FROM supplier_invoice_lines
    WHERE company_id = NEW.company_id
      AND supplier_invoice_id = NEW.supplier_invoice_id
      AND id = NEW.supplier_invoice_line_id
    FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'purchase return supplier invoice source line not found' USING ERRCODE = '23503';
    END IF;

    SELECT status, account_id, currency_code
      INTO invoice_status, invoice_account_id, invoice_currency
    FROM supplier_invoices
    WHERE company_id = NEW.company_id AND id = NEW.supplier_invoice_id
    FOR SHARE;
    IF invoice_status IS DISTINCT FROM 'finalized' THEN
        RAISE EXCEPTION 'purchase return requires finalized supplier invoice lineage' USING ERRCODE = '23514';
    END IF;

    IF NEW.purchase_order_id IS DISTINCT FROM parent.purchase_order_id
       OR receipt_line.purchase_order_id IS DISTINCT FROM parent.purchase_order_id
       OR invoice_line.purchase_order_id IS DISTINCT FROM parent.purchase_order_id
       OR receipt_line.purchase_order_line_id IS DISTINCT FROM NEW.purchase_order_line_id
       OR invoice_line.purchase_order_line_id IS DISTINCT FROM NEW.purchase_order_line_id
       OR receipt_line.product_id IS DISTINCT FROM NEW.product_id
       OR invoice_line.product_id IS DISTINCT FROM NEW.product_id
       OR invoice_account_id IS DISTINCT FROM parent.account_id
       OR invoice_currency IS DISTINCT FROM parent.currency_code
       OR NEW.warehouse_id IS DISTINCT FROM receipt_line.warehouse_id
       OR NEW.location_id IS DISTINCT FROM receipt_line.location_id
       OR NEW.product_code IS DISTINCT FROM invoice_line.product_code
       OR NEW.product_name IS DISTINCT FROM invoice_line.product_name
       OR NEW.description IS DISTINCT FROM invoice_line.description
       OR NEW.price_basis IS DISTINCT FROM invoice_line.price_basis
       OR NEW.unit_price IS DISTINCT FROM invoice_line.unit_price
       OR NEW.line_discount_rate IS DISTINCT FROM invoice_line.line_discount_rate
       OR NEW.tax_id IS DISTINCT FROM invoice_line.tax_id
       OR NEW.tax_code IS DISTINCT FROM invoice_line.tax_code
       OR NEW.tax_rate IS DISTINCT FROM invoice_line.tax_rate
       OR NEW.tax_is_zeroed IS DISTINCT FROM invoice_line.tax_is_zeroed
       OR NEW.tax_zero_reason_id IS DISTINCT FROM invoice_line.tax_zero_reason_id
       OR NEW.tax_zero_reason_code IS DISTINCT FROM invoice_line.tax_zero_reason_code
       OR NEW.quantity > invoice_line.quantity THEN
        RAISE EXCEPTION 'purchase return physical/financial lineage or commercial snapshot mismatch' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }

    public function down(): void
    {
        // The original function is dropped by the purchase-return base migration.
    }
};
