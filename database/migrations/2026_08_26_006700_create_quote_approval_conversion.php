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
            'key' => 'quotes.approve',
            'name' => 'Teklif ticari onayı',
            'description' => 'Aktif şirkette immutable teklif revizyonunu onaylama/reddetme ve onaylı teklifi satış siparişine dönüştürme yetkisi.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::table('quote_revisions', function (Blueprint $table): void {
            $table->unique(['company_id', 'quote_id', 'id'], 'quote_revisions_company_quote_id_unique');
        });
        Schema::table('quote_revision_lines', function (Blueprint $table): void {
            $table->unique(['company_id', 'revision_id', 'id'], 'quote_revision_lines_company_revision_id_unique');
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->unsignedBigInteger('selected_revision_id')->nullable()->after('status');
            $table->foreignId('decision_by_user_id')->nullable()->after('selected_revision_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('decision_at')->nullable()->after('decision_by_user_id');
            $table->text('decision_note')->nullable()->after('decision_at');
            $table->timestampTz('converted_at')->nullable()->after('decision_note');
            $table->foreign(['company_id', 'id', 'selected_revision_id'], 'quotes_selected_revision_fk')
                ->references(['company_id', 'quote_id', 'id'])->on('quote_revisions')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE quotes DROP CONSTRAINT quotes_status_check');
        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_status_check CHECK (status IN ('draft', 'approved', 'rejected', 'converted', 'cancelled'))");
        DB::statement(<<<'SQL'
ALTER TABLE quotes ADD CONSTRAINT quotes_decision_shape_check CHECK (
    (status IN ('draft', 'cancelled') AND selected_revision_id IS NULL AND decision_by_user_id IS NULL AND decision_at IS NULL AND converted_at IS NULL)
    OR
    (status IN ('approved', 'rejected') AND selected_revision_id IS NOT NULL AND decision_by_user_id IS NOT NULL AND decision_at IS NOT NULL AND converted_at IS NULL)
    OR
    (status = 'converted' AND selected_revision_id IS NOT NULL AND decision_by_user_id IS NOT NULL AND decision_at IS NOT NULL AND converted_at IS NOT NULL)
)
SQL);

        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->string('number', 64);
            $table->string('series_code', 64);
            $table->unsignedBigInteger('sequence_value');
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('source_quote_id');
            $table->unsignedBigInteger('source_quote_revision_id');
            $table->date('order_date');
            $table->char('currency_code', 3);
            $table->decimal('document_discount_rate', 9, 6);
            $table->decimal('base_net_total', 20, 6);
            $table->decimal('line_discount_total', 20, 6);
            $table->decimal('document_discount_total', 20, 6);
            $table->decimal('net_total', 20, 6);
            $table->decimal('tax_total', 20, 6);
            $table->decimal('gross_total', 20, 6);
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'sales_orders_company_id_id_unique');
            $table->unique(['company_id', 'number'], 'sales_orders_company_number_unique');
            $table->unique(['company_id', 'series_code', 'sequence_value'], 'sales_orders_company_series_sequence_unique');
            $table->unique(['company_id', 'source_quote_id'], 'sales_orders_source_quote_unique');
            $table->unique(['company_id', 'source_quote_revision_id'], 'sales_orders_source_revision_unique');
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'source_quote_id'])
                ->references(['company_id', 'id'])->on('quotes')->restrictOnDelete();
            $table->foreign(['company_id', 'source_quote_id', 'source_quote_revision_id'], 'sales_orders_source_revision_fk')
                ->references(['company_id', 'quote_id', 'id'])->on('quote_revisions')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['company_id', 'status', 'order_date'], 'sales_orders_company_status_date_index');
        });

        DB::statement("ALTER TABLE sales_orders ADD CONSTRAINT sales_orders_status_check CHECK (status = 'draft')");
        DB::statement("ALTER TABLE sales_orders ADD CONSTRAINT sales_orders_series_code_canonical_check CHECK (series_code = lower(btrim(series_code)) AND series_code ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE sales_orders ADD CONSTRAINT sales_orders_document_discount_rate_check CHECK (document_discount_rate >= 0 AND document_discount_rate <= 100)');
        DB::statement('ALTER TABLE sales_orders ADD CONSTRAINT sales_orders_totals_nonnegative_check CHECK (base_net_total >= 0 AND line_discount_total >= 0 AND document_discount_total >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE sales_orders ADD CONSTRAINT sales_orders_total_reconciliation_check CHECK (base_net_total - line_discount_total - document_discount_total = net_total AND net_total + tax_total = gross_total)');

        Schema::create('sales_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('source_quote_revision_line_id');
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('product_id');
            $table->string('product_code', 64);
            $table->string('product_name', 200);
            $table->string('description', 200);
            $table->decimal('quantity', 20, 6);
            $table->string('price_basis', 8);
            $table->decimal('unit_price', 20, 6);
            $table->decimal('line_discount_rate', 9, 6);
            $table->unsignedBigInteger('tax_id');
            $table->string('tax_code', 32);
            $table->decimal('tax_rate', 9, 6);
            $table->unsignedBigInteger('tax_zero_reason_id')->nullable();
            $table->string('tax_zero_reason_code', 32)->nullable();
            $table->decimal('base_net', 20, 6);
            $table->decimal('line_discount_net', 20, 6);
            $table->decimal('document_discount_net', 20, 6);
            $table->decimal('net_total', 20, 6);
            $table->decimal('tax_total', 20, 6);
            $table->decimal('gross_total', 20, 6);
            $table->timestampsTz();

            $table->foreign(['company_id', 'sales_order_id'])
                ->references(['company_id', 'id'])->on('sales_orders')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_id'])
                ->references(['company_id', 'id'])->on('taxes')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_zero_reason_id'])
                ->references(['company_id', 'id'])->on('tax_zero_reasons')->restrictOnDelete();
            $table->unique(['company_id', 'sales_order_id', 'position'], 'sales_order_lines_position_unique');
            $table->unique(['company_id', 'source_quote_revision_line_id'], 'sales_order_lines_source_revision_line_unique');
        });

        DB::statement("ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_price_basis_check CHECK (price_basis IN ('net', 'gross'))");
        DB::statement('ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_discount_rate_check CHECK (line_discount_rate >= 0 AND line_discount_rate <= 100)');
        DB::statement('ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_tax_rate_check CHECK (tax_rate >= 0 AND tax_rate <= 100)');
        DB::statement('ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_amounts_nonnegative_check CHECK (base_net >= 0 AND line_discount_net >= 0 AND document_discount_net >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_total_reconciliation_check CHECK (base_net - line_discount_net - document_discount_net = net_total AND net_total + tax_total = gross_total)');
        DB::statement('ALTER TABLE sales_order_lines ADD CONSTRAINT sales_order_lines_zero_reason_shape_check CHECK ((tax_rate = 0 AND tax_zero_reason_id IS NOT NULL AND tax_zero_reason_code IS NOT NULL) OR (tax_rate > 0 AND tax_zero_reason_id IS NULL AND tax_zero_reason_code IS NULL))');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_quote_approval_lifecycle()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    commercial_changed boolean;
BEGIN
    commercial_changed := ROW(
        NEW.company_id, NEW.account_id, NEW.number, NEW.series_code, NEW.sequence_value,
        NEW.quote_date, NEW.valid_until, NEW.currency_code, NEW.document_discount_rate,
        NEW.base_net_total, NEW.line_discount_total, NEW.document_discount_total,
        NEW.net_total, NEW.tax_total, NEW.gross_total, NEW.note
    ) IS DISTINCT FROM ROW(
        OLD.company_id, OLD.account_id, OLD.number, OLD.series_code, OLD.sequence_value,
        OLD.quote_date, OLD.valid_until, OLD.currency_code, OLD.document_discount_rate,
        OLD.base_net_total, OLD.line_discount_total, OLD.document_discount_total,
        OLD.net_total, OLD.tax_total, OLD.gross_total, OLD.note
    );

    IF OLD.status = 'draft' THEN
        IF NEW.status = 'draft' THEN
            IF NEW.selected_revision_id IS NOT NULL OR NEW.decision_by_user_id IS NOT NULL OR NEW.decision_at IS NOT NULL OR NEW.converted_at IS NOT NULL THEN
                RAISE EXCEPTION 'draft quote cannot carry approval metadata' USING ERRCODE = '23514';
            END IF;
            RETURN NEW;
        END IF;

        IF NEW.status = 'cancelled' THEN
            IF commercial_changed OR NEW.selected_revision_id IS NOT NULL OR NEW.decision_by_user_id IS NOT NULL OR NEW.decision_at IS NOT NULL OR NEW.converted_at IS NOT NULL THEN
                RAISE EXCEPTION 'invalid quote cancellation transition' USING ERRCODE = '23514';
            END IF;
            RETURN NEW;
        END IF;

        IF NEW.status IN ('approved', 'rejected') THEN
            IF commercial_changed OR NEW.selected_revision_id IS NULL OR NEW.decision_by_user_id IS NULL OR NEW.decision_at IS NULL OR NEW.converted_at IS NOT NULL THEN
                RAISE EXCEPTION 'invalid quote decision transition' USING ERRCODE = '23514';
            END IF;
            RETURN NEW;
        END IF;

        RAISE EXCEPTION 'invalid quote lifecycle transition' USING ERRCODE = '23514';
    END IF;

    IF OLD.status = 'approved' AND NEW.status = 'converted' THEN
        IF commercial_changed
            OR NEW.selected_revision_id IS DISTINCT FROM OLD.selected_revision_id
            OR NEW.decision_by_user_id IS DISTINCT FROM OLD.decision_by_user_id
            OR NEW.decision_at IS DISTINCT FROM OLD.decision_at
            OR NEW.converted_at IS NULL
            OR NOT EXISTS (
                SELECT 1 FROM sales_orders so
                WHERE so.company_id = OLD.company_id
                  AND so.source_quote_id = OLD.id
                  AND so.source_quote_revision_id = OLD.selected_revision_id
            ) THEN
            RAISE EXCEPTION 'invalid quote conversion transition' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'finalized quote lifecycle is immutable' USING ERRCODE = '55000';
END;
$$
SQL);
        DB::statement('CREATE TRIGGER quotes_approval_lifecycle_guard BEFORE UPDATE ON quotes FOR EACH ROW EXECUTE FUNCTION mars_guard_quote_approval_lifecycle()');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_quote_line_finalization()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    quote_company bigint;
    quote_key bigint;
    quote_status text;
BEGIN
    quote_company := COALESCE(NEW.company_id, OLD.company_id);
    quote_key := COALESCE(NEW.quote_id, OLD.quote_id);

    SELECT status INTO quote_status
    FROM quotes
    WHERE company_id = quote_company AND id = quote_key;

    IF quote_status IS NULL THEN
        RAISE EXCEPTION 'quote line parent not found' USING ERRCODE = '23503';
    END IF;
    IF quote_status <> 'draft' THEN
        RAISE EXCEPTION 'finalized quote lines are immutable' USING ERRCODE = '55000';
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$
SQL);
        DB::statement('CREATE TRIGGER quote_lines_finalization_guard BEFORE INSERT OR UPDATE OR DELETE ON quote_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_quote_line_finalization()');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_order_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    quote_status text;
    selected_revision bigint;
BEGIN
    SELECT status, selected_revision_id INTO quote_status, selected_revision
    FROM quotes
    WHERE company_id = NEW.company_id AND id = NEW.source_quote_id
    FOR SHARE;

    IF quote_status <> 'approved' OR selected_revision IS DISTINCT FROM NEW.source_quote_revision_id THEN
        RAISE EXCEPTION 'sales order requires the approved selected quote revision' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$
SQL);
        DB::statement('CREATE TRIGGER sales_orders_insert_guard BEFORE INSERT ON sales_orders FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_order_insert()');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_order_line_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    order_revision bigint;
    line_revision bigint;
BEGIN
    SELECT source_quote_revision_id INTO order_revision
    FROM sales_orders
    WHERE company_id = NEW.company_id AND id = NEW.sales_order_id;

    SELECT revision_id INTO line_revision
    FROM quote_revision_lines
    WHERE company_id = NEW.company_id AND id = NEW.source_quote_revision_line_id;

    IF order_revision IS NULL OR line_revision IS NULL OR order_revision IS DISTINCT FROM line_revision THEN
        RAISE EXCEPTION 'sales order line source revision does not match order lineage' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$
SQL);
        DB::statement('CREATE TRIGGER sales_order_lines_insert_guard BEFORE INSERT ON sales_order_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_order_line_insert()');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_prevent_sales_order_source_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'quote-converted sales order source snapshot is immutable' USING ERRCODE = '55000';
END;
$$
SQL);
        DB::statement('CREATE TRIGGER sales_orders_source_immutable BEFORE UPDATE OR DELETE ON sales_orders FOR EACH ROW EXECUTE FUNCTION mars_prevent_sales_order_source_mutation()');
        DB::statement('CREATE TRIGGER sales_order_lines_source_immutable BEFORE UPDATE OR DELETE ON sales_order_lines FOR EACH ROW EXECUTE FUNCTION mars_prevent_sales_order_source_mutation()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_order_lines_source_immutable ON sales_order_lines');
        DB::statement('DROP TRIGGER IF EXISTS sales_orders_source_immutable ON sales_orders');
        DB::statement('DROP TRIGGER IF EXISTS sales_order_lines_insert_guard ON sales_order_lines');
        DB::statement('DROP TRIGGER IF EXISTS sales_orders_insert_guard ON sales_orders');
        DB::statement('DROP TRIGGER IF EXISTS quote_lines_finalization_guard ON quote_lines');
        DB::statement('DROP TRIGGER IF EXISTS quotes_approval_lifecycle_guard ON quotes');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_sales_order_source_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_order_line_insert()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_order_insert()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_quote_line_finalization()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_quote_approval_lifecycle()');

        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');

        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropForeign('quotes_selected_revision_fk');
        });
        DB::statement('ALTER TABLE quotes DROP CONSTRAINT quotes_decision_shape_check');
        DB::table('quotes')
            ->whereIn('status', ['approved', 'rejected', 'converted'])
            ->update(['status' => 'cancelled']);
        DB::statement('ALTER TABLE quotes DROP CONSTRAINT quotes_status_check');
        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_status_check CHECK (status IN ('draft', 'cancelled'))");
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropForeign(['decision_by_user_id']);
            $table->dropColumn(['selected_revision_id', 'decision_by_user_id', 'decision_at', 'decision_note', 'converted_at']);
        });

        Schema::table('quote_revision_lines', function (Blueprint $table): void {
            $table->dropUnique('quote_revision_lines_company_revision_id_unique');
        });
        Schema::table('quote_revisions', function (Blueprint $table): void {
            $table->dropUnique('quote_revisions_company_quote_id_unique');
        });

        $permissionId = DB::table('permissions')->where('key', 'quotes.approve')->value('id');
        if (is_int($permissionId)) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        }
        DB::table('permissions')->where('key', 'quotes.approve')->delete();
    }
};
