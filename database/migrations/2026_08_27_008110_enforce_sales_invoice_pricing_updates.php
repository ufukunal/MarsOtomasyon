<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_lines_pricing_guard ON sales_invoice_lines');
        DB::statement('CREATE TRIGGER sales_invoice_lines_pricing_guard BEFORE INSERT OR UPDATE ON sales_invoice_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_pricing_line_insert()');

        DB::statement('DROP TRIGGER IF EXISTS sales_invoices_pricing_header_guard ON sales_invoices');
        DB::statement('CREATE TRIGGER sales_invoices_pricing_header_guard BEFORE INSERT OR UPDATE ON sales_invoices FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_pricing_header_insert()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_lines_pricing_guard ON sales_invoice_lines');
        DB::statement('CREATE TRIGGER sales_invoice_lines_pricing_guard BEFORE INSERT ON sales_invoice_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_pricing_line_insert()');

        DB::statement('DROP TRIGGER IF EXISTS sales_invoices_pricing_header_guard ON sales_invoices');
        DB::statement('CREATE TRIGGER sales_invoices_pricing_header_guard BEFORE INSERT ON sales_invoices FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_pricing_header_insert()');
    }
};
