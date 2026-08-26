<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insert([
            [
                'key' => 'sales_invoices.view',
                'name' => 'Satış faturası görüntüleme',
                'description' => 'Aktif şirkette satış faturası taslaklarını listeleme ve detay görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'sales_invoices.manage',
                'name' => 'Satış faturası yönetimi',
                'description' => 'Aktif şirkette direct, sipariş bağlı ve irsaliye bağlı satış faturası taslağı oluşturma yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('dispatch_lines', function (Blueprint $table): void {
            $table->unique(['company_id', 'id'], 'dispatch_lines_company_id_id_unique');
        });

        Schema::create('sales_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('source_billing_address_id');
            $table->unsignedBigInteger('source_sales_order_id')->nullable();
            $table->unsignedBigInteger('source_dispatch_id')->nullable();
            $table->string('number', 64);
            $table->string('series_code', 64);
            $table->unsignedBigInteger('sequence_value');
            $table->string('mode', 24);
            $table->string('status', 16)->default('draft');
            $table->date('invoice_date');
            $table->char('currency_code', 3);
            $table->string('customer_legal_name', 200);
            $table->string('customer_trade_name', 200)->nullable();
            $table->string('customer_tax_identity_type', 16);
            $table->string('customer_tax_number', 32)->nullable();
            $table->string('customer_tax_office', 120)->nullable();
            $table->string('recipient_name', 200)->nullable();
            $table->string('address_line1', 240);
            $table->string('address_line2', 240)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120);
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2);
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'sales_invoices_company_id_id_unique');
            $table->unique(['company_id', 'number'], 'sales_invoices_company_number_unique');
            $table->unique(['company_id', 'series_code', 'sequence_value'], 'sales_invoices_company_series_sequence_unique');
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id', 'source_billing_address_id'], 'sales_invoices_billing_address_fk')
                ->references(['company_id', 'account_id', 'id'])->on('account_addresses')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id', 'source_sales_order_id'], 'sales_invoices_source_order_fk')
                ->references(['company_id', 'account_id', 'id'])->on('sales_orders')->restrictOnDelete();
            $table->foreign(['company_id', 'source_dispatch_id', 'source_sales_order_id'], 'sales_invoices_source_dispatch_fk')
                ->references(['company_id', 'id', 'sales_order_id'])->on('dispatches')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['company_id', 'status', 'invoice_date'], 'sales_invoices_company_status_date_index');
            $table->index(['company_id', 'source_sales_order_id'], 'sales_invoices_company_order_index');
            $table->index(['company_id', 'source_dispatch_id'], 'sales_invoices_company_dispatch_index');
        });

        DB::statement("ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_status_check CHECK (status = 'draft')");
        DB::statement("ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_mode_check CHECK (mode IN ('direct', 'order_linked', 'dispatch_linked'))");
        DB::statement(<<<'SQL'
ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_mode_source_shape_check CHECK (
    (mode = 'direct' AND source_sales_order_id IS NULL AND source_dispatch_id IS NULL)
    OR
    (mode = 'order_linked' AND source_sales_order_id IS NOT NULL AND source_dispatch_id IS NULL)
    OR
    (mode = 'dispatch_linked' AND source_sales_order_id IS NOT NULL AND source_dispatch_id IS NOT NULL)
)
SQL);
        DB::statement("ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_series_code_canonical_check CHECK (series_code = lower(btrim(series_code)) AND series_code ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_country_code_check CHECK (country_code = upper(country_code) AND country_code ~ '^[A-Z]{2}$')");
        DB::statement('ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_customer_legal_name_not_blank_check CHECK (char_length(btrim(customer_legal_name)) > 0)');
        DB::statement('ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_address_line1_not_blank_check CHECK (char_length(btrim(address_line1)) > 0)');
        DB::statement('ALTER TABLE sales_invoices ADD CONSTRAINT sales_invoices_city_not_blank_check CHECK (char_length(btrim(city)) > 0)');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    account_row accounts%ROWTYPE;
    address_row account_addresses%ROWTYPE;
    order_account bigint;
    order_currency char(3);
    dispatch_status text;
BEGIN
    SELECT * INTO account_row
    FROM accounts
    WHERE company_id = NEW.company_id AND id = NEW.account_id;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'sales invoice account not found in company' USING ERRCODE = '23503';
    END IF;

    IF account_row.status <> 'active' OR account_row.type NOT IN ('customer', 'mixed') THEN
        RAISE EXCEPTION 'sales invoice requires active customer account' USING ERRCODE = '23514';
    END IF;

    IF NEW.customer_legal_name IS DISTINCT FROM account_row.legal_name
        OR NEW.customer_trade_name IS DISTINCT FROM account_row.trade_name
        OR NEW.customer_tax_identity_type IS DISTINCT FROM account_row.tax_identity_type
        OR NEW.customer_tax_number IS DISTINCT FROM account_row.tax_number
        OR NEW.customer_tax_office IS DISTINCT FROM account_row.tax_office THEN
        RAISE EXCEPTION 'sales invoice customer legal snapshot mismatch' USING ERRCODE = '23514';
    END IF;

    SELECT * INTO address_row
    FROM account_addresses
    WHERE company_id = NEW.company_id
      AND account_id = NEW.account_id
      AND id = NEW.source_billing_address_id;

    IF NOT FOUND OR address_row.type <> 'billing' THEN
        RAISE EXCEPTION 'sales invoice requires billing address of customer' USING ERRCODE = '23514';
    END IF;

    IF NEW.recipient_name IS DISTINCT FROM address_row.recipient_name
        OR NEW.address_line1 IS DISTINCT FROM address_row.line1
        OR NEW.address_line2 IS DISTINCT FROM address_row.line2
        OR NEW.district IS DISTINCT FROM address_row.district
        OR NEW.city IS DISTINCT FROM address_row.city
        OR NEW.postal_code IS DISTINCT FROM address_row.postal_code
        OR NEW.country_code IS DISTINCT FROM upper(address_row.country_code) THEN
        RAISE EXCEPTION 'sales invoice billing address snapshot mismatch' USING ERRCODE = '23514';
    END IF;

    IF NEW.mode = 'direct' THEN
        IF NEW.currency_code IS DISTINCT FROM account_row.book_currency_code THEN
            RAISE EXCEPTION 'direct sales invoice currency must match account book currency' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    SELECT account_id, currency_code INTO order_account, order_currency
    FROM sales_orders
    WHERE company_id = NEW.company_id AND id = NEW.source_sales_order_id;

    IF order_account IS NULL
        OR order_account IS DISTINCT FROM NEW.account_id
        OR order_currency IS DISTINCT FROM NEW.currency_code THEN
        RAISE EXCEPTION 'sales invoice order lineage does not match account/currency snapshot' USING ERRCODE = '23514';
    END IF;

    IF NEW.mode = 'dispatch_linked' THEN
        SELECT status INTO dispatch_status
        FROM dispatches
        WHERE company_id = NEW.company_id
          AND id = NEW.source_dispatch_id
          AND sales_order_id = NEW.source_sales_order_id;

        IF dispatch_status IS DISTINCT FROM 'finalized' THEN
            RAISE EXCEPTION 'dispatch-linked sales invoice requires finalized dispatch' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;
SQL);
        DB::statement('CREATE TRIGGER sales_invoices_insert_guard BEFORE INSERT ON sales_invoices FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_insert()');

        Schema::create('sales_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sales_invoice_id');
            $table->unsignedBigInteger('source_sales_order_id')->nullable();
            $table->unsignedBigInteger('source_sales_order_line_id')->nullable();
            $table->unsignedBigInteger('source_dispatch_id')->nullable();
            $table->unsignedBigInteger('source_dispatch_line_id')->nullable();
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->string('product_code', 64);
            $table->string('product_name', 200);
            $table->string('description', 200)->nullable();
            $table->decimal('quantity', 20, 6);
            $table->timestampsTz();

            $table->foreign(['company_id', 'sales_invoice_id'])
                ->references(['company_id', 'id'])->on('sales_invoices')->restrictOnDelete();
            $table->foreign(['company_id', 'source_sales_order_id', 'source_sales_order_line_id'], 'sales_invoice_lines_source_order_line_fk')
                ->references(['company_id', 'sales_order_id', 'id'])->on('sales_order_lines')->restrictOnDelete();
            $table->foreign(['company_id', 'source_dispatch_line_id'], 'sales_invoice_lines_source_dispatch_line_fk')
                ->references(['company_id', 'id'])->on('dispatch_lines')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'], 'sales_invoice_lines_location_fk')
                ->references(['company_id', 'warehouse_id', 'id'])->on('warehouse_locations')->restrictOnDelete();
            $table->unique(['company_id', 'sales_invoice_id', 'position'], 'sales_invoice_lines_position_unique');
            $table->index(['company_id', 'source_sales_order_id', 'source_sales_order_line_id'], 'sales_invoice_lines_order_line_index');
            $table->index(['company_id', 'source_dispatch_id', 'source_dispatch_line_id'], 'sales_invoice_lines_dispatch_line_index');
        });

        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_product_code_not_blank_check CHECK (char_length(btrim(product_code)) > 0)');
        DB::statement('ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_product_name_not_blank_check CHECK (char_length(btrim(product_name)) > 0)');
        DB::statement(<<<'SQL'
ALTER TABLE sales_invoice_lines ADD CONSTRAINT sales_invoice_lines_source_shape_check CHECK (
    (source_sales_order_id IS NULL AND source_sales_order_line_id IS NULL AND source_dispatch_id IS NULL AND source_dispatch_line_id IS NULL)
    OR
    (source_sales_order_id IS NOT NULL AND source_sales_order_line_id IS NOT NULL AND source_dispatch_id IS NULL AND source_dispatch_line_id IS NULL)
    OR
    (source_sales_order_id IS NOT NULL AND source_sales_order_line_id IS NOT NULL AND source_dispatch_id IS NOT NULL AND source_dispatch_line_id IS NOT NULL)
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_line_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    parent_mode text;
    parent_order bigint;
    parent_dispatch bigint;
    source_product bigint;
    dispatch_order_line bigint;
    dispatch_product bigint;
    dispatch_warehouse bigint;
    dispatch_location bigint;
BEGIN
    SELECT mode, source_sales_order_id, source_dispatch_id
    INTO parent_mode, parent_order, parent_dispatch
    FROM sales_invoices
    WHERE company_id = NEW.company_id AND id = NEW.sales_invoice_id;

    IF parent_mode IS NULL THEN
        RAISE EXCEPTION 'sales invoice line parent not found' USING ERRCODE = '23503';
    END IF;

    IF parent_mode = 'direct' THEN
        IF NEW.source_sales_order_id IS NOT NULL
            OR NEW.source_sales_order_line_id IS NOT NULL
            OR NEW.source_dispatch_id IS NOT NULL
            OR NEW.source_dispatch_line_id IS NOT NULL THEN
            RAISE EXCEPTION 'direct sales invoice line cannot carry source document lineage' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF NEW.source_sales_order_id IS DISTINCT FROM parent_order THEN
        RAISE EXCEPTION 'sales invoice line order lineage does not match parent' USING ERRCODE = '23514';
    END IF;

    SELECT product_id INTO source_product
    FROM sales_order_lines
    WHERE company_id = NEW.company_id
      AND sales_order_id = NEW.source_sales_order_id
      AND id = NEW.source_sales_order_line_id;

    IF source_product IS NULL OR source_product IS DISTINCT FROM NEW.product_id THEN
        RAISE EXCEPTION 'sales invoice line product does not match source order line' USING ERRCODE = '23514';
    END IF;

    IF parent_mode = 'order_linked' THEN
        IF NEW.source_dispatch_id IS NOT NULL OR NEW.source_dispatch_line_id IS NOT NULL THEN
            RAISE EXCEPTION 'order-linked sales invoice line cannot carry dispatch lineage' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF NEW.source_dispatch_id IS DISTINCT FROM parent_dispatch OR NEW.source_dispatch_line_id IS NULL THEN
        RAISE EXCEPTION 'dispatch-linked sales invoice line must match parent dispatch' USING ERRCODE = '23514';
    END IF;

    SELECT sales_order_line_id, product_id, warehouse_id, location_id
    INTO dispatch_order_line, dispatch_product, dispatch_warehouse, dispatch_location
    FROM dispatch_lines
    WHERE company_id = NEW.company_id
      AND dispatch_id = NEW.source_dispatch_id
      AND sales_order_id = NEW.source_sales_order_id
      AND id = NEW.source_dispatch_line_id;

    IF dispatch_order_line IS NULL
        OR dispatch_order_line IS DISTINCT FROM NEW.source_sales_order_line_id
        OR dispatch_product IS DISTINCT FROM NEW.product_id
        OR dispatch_warehouse IS DISTINCT FROM NEW.warehouse_id
        OR dispatch_location IS DISTINCT FROM NEW.location_id THEN
        RAISE EXCEPTION 'sales invoice line dispatch lineage/allocation mismatch' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;
SQL);
        DB::statement('CREATE TRIGGER sales_invoice_lines_insert_guard BEFORE INSERT ON sales_invoice_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_line_insert()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_source_order_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM sales_invoices
        WHERE company_id = OLD.company_id AND source_sales_order_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'sales order with invoice lineage is immutable' USING ERRCODE = '55000';
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$;
SQL);
        DB::statement('CREATE TRIGGER sales_invoice_source_order_mutation_guard BEFORE UPDATE OR DELETE ON sales_orders FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_source_order_mutation()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_source_order_line_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM sales_invoice_lines
        WHERE company_id = OLD.company_id AND source_sales_order_line_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'sales order line with invoice lineage is immutable' USING ERRCODE = '55000';
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$;
SQL);
        DB::statement('CREATE TRIGGER sales_invoice_source_order_line_mutation_guard BEFORE UPDATE OR DELETE ON sales_order_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_source_order_line_mutation()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_source_dispatch_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM sales_invoices
        WHERE company_id = OLD.company_id AND source_dispatch_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'dispatch with invoice lineage is immutable' USING ERRCODE = '55000';
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$;
SQL);
        DB::statement('CREATE TRIGGER sales_invoice_source_dispatch_mutation_guard BEFORE UPDATE OR DELETE ON dispatches FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_source_dispatch_mutation()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_source_dispatch_line_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM sales_invoice_lines
        WHERE company_id = OLD.company_id AND source_dispatch_line_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'dispatch line with invoice lineage is immutable' USING ERRCODE = '55000';
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$;
SQL);
        DB::statement('CREATE TRIGGER sales_invoice_source_dispatch_line_mutation_guard BEFORE UPDATE OR DELETE ON dispatch_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_source_dispatch_line_mutation()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_source_dispatch_line_mutation_guard ON dispatch_lines');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_source_dispatch_mutation_guard ON dispatches');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_source_order_line_mutation_guard ON sales_order_lines');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_source_order_mutation_guard ON sales_orders');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_lines_insert_guard ON sales_invoice_lines');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoices_insert_guard ON sales_invoices');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_source_dispatch_line_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_source_dispatch_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_source_order_line_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_source_order_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_line_insert()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_insert()');

        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');

        Schema::table('dispatch_lines', function (Blueprint $table): void {
            $table->dropUnique('dispatch_lines_company_id_id_unique');
        });

        $permissionIds = array_map(
            'intval',
            DB::table('permissions')->whereIn('key', ['sales_invoices.view', 'sales_invoices.manage'])->pluck('id')->all(),
        );
        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }
        DB::table('permissions')->whereIn('key', ['sales_invoices.view', 'sales_invoices.manage'])->delete();
    }
};
