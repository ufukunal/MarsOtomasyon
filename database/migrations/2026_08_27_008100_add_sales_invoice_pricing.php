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
            $table->decimal('document_discount_rate', 9, 6)->after('currency_code');
            $table->decimal('base_net_total', 20, 6)->after('document_discount_rate');
            $table->decimal('line_discount_total', 20, 6)->after('base_net_total');
            $table->decimal('document_discount_total', 20, 6)->after('line_discount_total');
            $table->decimal('net_total', 20, 6)->after('document_discount_total');
            $table->decimal('tax_total', 20, 6)->after('net_total');
            $table->decimal('gross_total', 20, 6)->after('tax_total');
        });

        DB::statement('ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_document_discount_rate_check CHECK (document_discount_rate >= 0 AND document_discount_rate <= 100)');
        DB::statement('ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_totals_nonnegative_check CHECK (base_net_total >= 0 AND line_discount_total >= 0 AND document_discount_total >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_total_reconciliation_check CHECK (base_net_total - line_discount_total - document_discount_total = net_total AND net_total + tax_total = gross_total)');

        Schema::table('sales_invoice_lines', function (Blueprint $table): void {
            $table->string('price_basis', 8)->after('quantity');
            $table->decimal('unit_price', 20, 6)->after('price_basis');
            $table->decimal('line_discount_rate', 9, 6)->after('unit_price');
            $table->unsignedBigInteger('tax_id')->after('line_discount_rate');
            $table->string('tax_code', 32)->after('tax_id');
            $table->decimal('tax_rate', 9, 6)->after('tax_code');
            $table->boolean('tax_is_zeroed')->default(false)->after('tax_rate');
            $table->unsignedBigInteger('tax_zero_reason_id')->nullable()->after('tax_is_zeroed');
            $table->string('tax_zero_reason_code', 32)->nullable()->after('tax_zero_reason_id');
            $table->decimal('base_net', 20, 6)->after('tax_zero_reason_code');
            $table->decimal('line_discount_net', 20, 6)->after('base_net');
            $table->decimal('document_discount_net', 20, 6)->after('line_discount_net');
            $table->decimal('net_total', 20, 6)->after('document_discount_net');
            $table->decimal('tax_total', 20, 6)->after('net_total');
            $table->decimal('gross_total', 20, 6)->after('tax_total');

            $table->foreign(['company_id', 'tax_id'], 'sales_invoice_lines_tax_fk')
                ->references(['company_id', 'id'])->on('taxes')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_zero_reason_id'], 'sales_invoice_lines_zero_reason_fk')
                ->references(['company_id', 'id'])->on('tax_zero_reasons')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_price_basis_check CHECK (price_basis IN ('net', 'gross'))");
        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_discount_rate_check CHECK (line_discount_rate >= 0 AND line_discount_rate <= 100)');
        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_tax_rate_check CHECK (tax_rate >= 0 AND tax_rate <= 100)');
        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_amounts_nonnegative_check CHECK (base_net >= 0 AND line_discount_net >= 0 AND document_discount_net >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_total_reconciliation_check CHECK (base_net - line_discount_net - document_discount_net = net_total AND net_total + tax_total = gross_total)');
        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_zero_reason_shape_check CHECK ((tax_rate = 0 AND tax_zero_reason_id IS NOT NULL AND tax_zero_reason_code IS NOT NULL) OR (tax_rate > 0 AND tax_zero_reason_id IS NULL AND tax_zero_reason_code IS NULL))');
        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_tax_zeroed_shape_check CHECK ((tax_is_zeroed = false) OR (tax_is_zeroed = true AND tax_rate = 0 AND tax_zero_reason_id IS NOT NULL))');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_pricing_header_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    source_discount numeric(9,6);
BEGIN
    IF NEW.mode = 'direct' THEN
        RETURN NEW;
    END IF;

    SELECT document_discount_rate INTO source_discount
    FROM sales_orders
    WHERE company_id = NEW.company_id AND id = NEW.source_sales_order_id;

    IF source_discount IS NULL OR NEW.document_discount_rate IS DISTINCT FROM source_discount THEN
        RAISE EXCEPTION 'linked sales invoice document discount must match source order' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
        DB::statement('CREATE TRIGGER sales_invoices_pricing_header_guard BEFORE INSERT ON sales_invoices FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_pricing_header_insert()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_pricing_line_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    parent_mode text;
    tax_code_value text;
    natural_tax_rate numeric(9,6);
    zero_reason_code_value text;
    zero_reason_active boolean;
    source_row sales_order_lines%ROWTYPE;
BEGIN
    SELECT mode INTO parent_mode
    FROM sales_invoices
    WHERE company_id = NEW.company_id AND id = NEW.sales_invoice_id;

    IF parent_mode IS NULL THEN
        RAISE EXCEPTION 'sales invoice pricing parent not found' USING ERRCODE = '23503';
    END IF;

    IF parent_mode IN ('order_linked', 'dispatch_linked') THEN
        SELECT * INTO source_row
        FROM sales_order_lines
        WHERE company_id = NEW.company_id
          AND sales_order_id = NEW.source_sales_order_id
          AND id = NEW.source_sales_order_line_id;

        IF NOT FOUND
            OR NEW.price_basis IS DISTINCT FROM source_row.price_basis
            OR NEW.unit_price IS DISTINCT FROM source_row.unit_price
            OR NEW.line_discount_rate IS DISTINCT FROM source_row.line_discount_rate
            OR NEW.tax_id IS DISTINCT FROM source_row.tax_id
            OR NEW.tax_code IS DISTINCT FROM source_row.tax_code
            OR NEW.tax_rate IS DISTINCT FROM source_row.tax_rate
            OR NEW.tax_is_zeroed IS DISTINCT FROM source_row.tax_is_zeroed
            OR NEW.tax_zero_reason_id IS DISTINCT FROM source_row.tax_zero_reason_id
            OR NEW.tax_zero_reason_code IS DISTINCT FROM source_row.tax_zero_reason_code THEN
            RAISE EXCEPTION 'linked sales invoice pricing snapshot must match source order line' USING ERRCODE = '23514';
        END IF;

        RETURN NEW;
    END IF;

    SELECT code, rate INTO tax_code_value, natural_tax_rate
    FROM taxes
    WHERE company_id = NEW.company_id AND id = NEW.tax_id AND is_active = true;

    IF tax_code_value IS NULL OR NEW.tax_code IS DISTINCT FROM tax_code_value THEN
        RAISE EXCEPTION 'direct sales invoice requires active product tax snapshot' USING ERRCODE = '23514';
    END IF;

    IF NEW.tax_is_zeroed THEN
        IF natural_tax_rate = 0 OR NEW.tax_rate <> 0 THEN
            RAISE EXCEPTION 'direct sales invoice explicit tax zeroing requires positive natural tax' USING ERRCODE = '23514';
        END IF;
    ELSIF NEW.tax_rate IS DISTINCT FROM natural_tax_rate THEN
        RAISE EXCEPTION 'direct sales invoice tax rate must match product tax' USING ERRCODE = '23514';
    END IF;

    IF NEW.tax_rate = 0 THEN
        SELECT code, is_active INTO zero_reason_code_value, zero_reason_active
        FROM tax_zero_reasons
        WHERE company_id = NEW.company_id AND id = NEW.tax_zero_reason_id;

        IF zero_reason_code_value IS NULL
            OR zero_reason_active IS DISTINCT FROM true
            OR NEW.tax_zero_reason_code IS DISTINCT FROM zero_reason_code_value THEN
            RAISE EXCEPTION 'direct zero-tax invoice line requires active zero reason snapshot' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;
SQL);
        DB::statement('CREATE TRIGGER sales_invoice_lines_pricing_guard BEFORE INSERT ON sales_invoice_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_pricing_line_insert()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_lines_pricing_guard ON sales_invoice_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_pricing_line_insert()');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoices_pricing_header_guard ON sales_invoices');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_pricing_header_insert()');

        DB::statement('ALTER TABLE sales_invoice_lines DROP CONSTRAINT IF EXISTS sales_invoice_lines_tax_zeroed_shape_check');
        DB::statement('ALTER TABLE sales_invoice_lines DROP CONSTRAINT IF EXISTS sales_invoice_lines_zero_reason_shape_check');
        DB::statement('ALTER TABLE sales_invoice_lines DROP CONSTRAINT IF EXISTS sales_invoice_lines_total_reconciliation_check');
        DB::statement('ALTER TABLE sales_invoice_lines DROP CONSTRAINT IF EXISTS sales_invoice_lines_amounts_nonnegative_check');
        DB::statement('ALTER TABLE sales_invoice_lines DROP CONSTRAINT IF EXISTS sales_invoice_lines_tax_rate_check');
        DB::statement('ALTER TABLE sales_invoice_lines DROP CONSTRAINT IF EXISTS sales_invoice_lines_discount_rate_check');
        DB::statement('ALTER TABLE sales_invoice_lines DROP CONSTRAINT IF EXISTS sales_invoice_lines_unit_price_check');
        DB::statement('ALTER TABLE sales_invoice_lines DROP CONSTRAINT IF EXISTS sales_invoice_lines_price_basis_check');

        Schema::table('sales_invoice_lines', function (Blueprint $table): void {
            $table->dropForeign('sales_invoice_lines_zero_reason_fk');
            $table->dropForeign('sales_invoice_lines_tax_fk');
            $table->dropColumn([
                'price_basis', 'unit_price', 'line_discount_rate', 'tax_id', 'tax_code', 'tax_rate',
                'tax_is_zeroed', 'tax_zero_reason_id', 'tax_zero_reason_code', 'base_net',
                'line_discount_net', 'document_discount_net', 'net_total', 'tax_total', 'gross_total',
            ]);
        });

        DB::statement('ALTER TABLE sales_invoices DROP CONSTRAINT IF EXISTS sales_invoices_total_reconciliation_check');
        DB::statement('ALTER TABLE sales_invoices DROP CONSTRAINT IF EXISTS sales_invoices_totals_nonnegative_check');
        DB::statement('ALTER TABLE sales_invoices DROP CONSTRAINT IF EXISTS sales_invoices_document_discount_rate_check');

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'document_discount_rate', 'base_net_total', 'line_discount_total', 'document_discount_total',
                'net_total', 'tax_total', 'gross_total',
            ]);
        });
    }
};
