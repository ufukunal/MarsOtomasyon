<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_direct_product_tax()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    parent_mode text;
    product_tax_id bigint;
BEGIN
    SELECT mode INTO parent_mode
    FROM sales_invoices
    WHERE company_id = NEW.company_id AND id = NEW.sales_invoice_id;

    IF parent_mode IS DISTINCT FROM 'direct' THEN
        RETURN NEW;
    END IF;

    SELECT tax_id INTO product_tax_id
    FROM products
    WHERE company_id = NEW.company_id AND id = NEW.product_id AND status = 'active';

    IF product_tax_id IS NULL OR NEW.tax_id IS DISTINCT FROM product_tax_id THEN
        RAISE EXCEPTION 'direct sales invoice tax identity must match product tax' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
        DB::statement('CREATE TRIGGER sales_invoice_lines_direct_product_tax_guard BEFORE INSERT OR UPDATE ON sales_invoice_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_direct_product_tax()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_lines_direct_product_tax_guard ON sales_invoice_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_direct_product_tax()');
    }
};
