<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insert([
            [
                'key' => 'sales_orders.view',
                'name' => 'Satış siparişi görüntüleme',
                'description' => 'Aktif şirkette satış siparişlerini listeleme ve detay görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'sales_orders.manage',
                'name' => 'Satış siparişi yönetimi',
                'description' => 'Aktif şirkette manuel taslak satış siparişi oluşturma ve düzenleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::statement('DROP TRIGGER IF EXISTS sales_order_lines_source_immutable ON sales_order_lines');
        DB::statement('DROP TRIGGER IF EXISTS sales_orders_source_immutable ON sales_orders');
        DB::statement('DROP TRIGGER IF EXISTS sales_order_lines_insert_guard ON sales_order_lines');
        DB::statement('DROP TRIGGER IF EXISTS sales_orders_insert_guard ON sales_orders');

        DB::statement('ALTER TABLE sales_orders ALTER COLUMN source_quote_id DROP NOT NULL');
        DB::statement('ALTER TABLE sales_orders ALTER COLUMN source_quote_revision_id DROP NOT NULL');
        DB::statement('ALTER TABLE sales_order_lines ALTER COLUMN source_quote_revision_line_id DROP NOT NULL');
        DB::statement(<<<'SQL'
ALTER TABLE sales_orders ADD CONSTRAINT sales_orders_source_shape_check CHECK (
    (source_quote_id IS NULL AND source_quote_revision_id IS NULL)
    OR
    (source_quote_id IS NOT NULL AND source_quote_revision_id IS NOT NULL)
)
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_order_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    quote_status text;
    selected_revision bigint;
BEGIN
    IF NEW.source_quote_id IS NULL AND NEW.source_quote_revision_id IS NULL THEN
        RETURN NEW;
    END IF;

    IF NEW.source_quote_id IS NULL OR NEW.source_quote_revision_id IS NULL THEN
        RAISE EXCEPTION 'sales order quote lineage must be complete' USING ERRCODE = '23514';
    END IF;

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
    order_status text;
    line_revision bigint;
BEGIN
    SELECT source_quote_revision_id, status INTO order_revision, order_status
    FROM sales_orders
    WHERE company_id = NEW.company_id AND id = NEW.sales_order_id;

    IF order_status IS NULL THEN
        RAISE EXCEPTION 'sales order line parent not found' USING ERRCODE = '23503';
    END IF;

    IF order_revision IS NULL THEN
        IF NEW.source_quote_revision_line_id IS NOT NULL THEN
            RAISE EXCEPTION 'manual sales order line cannot carry quote revision lineage' USING ERRCODE = '23514';
        END IF;
        IF order_status <> 'draft' THEN
            RAISE EXCEPTION 'manual sales order lines require draft parent' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF NEW.source_quote_revision_line_id IS NULL THEN
        RAISE EXCEPTION 'quote-converted sales order line requires source revision line' USING ERRCODE = '23514';
    END IF;

    SELECT revision_id INTO line_revision
    FROM quote_revision_lines
    WHERE company_id = NEW.company_id AND id = NEW.source_quote_revision_line_id;

    IF line_revision IS NULL OR order_revision IS DISTINCT FROM line_revision THEN
        RAISE EXCEPTION 'sales order line source revision does not match order lineage' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$
SQL);
        DB::statement('CREATE TRIGGER sales_order_lines_insert_guard BEFORE INSERT ON sales_order_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_order_line_insert()');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_order_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'sales orders cannot be deleted' USING ERRCODE = '55000';
    END IF;

    IF OLD.source_quote_id IS NOT NULL OR OLD.source_quote_revision_id IS NOT NULL THEN
        RAISE EXCEPTION 'quote-converted sales order source snapshot is immutable' USING ERRCODE = '55000';
    END IF;

    IF NEW.source_quote_id IS NOT NULL OR NEW.source_quote_revision_id IS NOT NULL THEN
        RAISE EXCEPTION 'manual sales order cannot acquire quote lineage' USING ERRCODE = '23514';
    END IF;

    IF OLD.status <> 'draft' OR NEW.status <> 'draft' THEN
        RAISE EXCEPTION 'only manual draft sales orders are mutable' USING ERRCODE = '55000';
    END IF;

    IF NEW.company_id IS DISTINCT FROM OLD.company_id
        OR NEW.number IS DISTINCT FROM OLD.number
        OR NEW.series_code IS DISTINCT FROM OLD.series_code
        OR NEW.sequence_value IS DISTINCT FROM OLD.sequence_value THEN
        RAISE EXCEPTION 'sales order document identity is immutable' USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$
SQL);
        DB::statement('CREATE TRIGGER sales_orders_source_immutable BEFORE UPDATE OR DELETE ON sales_orders FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_order_mutation()');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_order_line_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    parent_source_revision bigint;
    parent_status text;
BEGIN
    SELECT source_quote_revision_id, status INTO parent_source_revision, parent_status
    FROM sales_orders
    WHERE company_id = OLD.company_id AND id = OLD.sales_order_id;

    IF parent_status IS NULL THEN
        RAISE EXCEPTION 'sales order line parent not found' USING ERRCODE = '23503';
    END IF;

    IF parent_source_revision IS NOT NULL THEN
        RAISE EXCEPTION 'quote-converted sales order source snapshot is immutable' USING ERRCODE = '55000';
    END IF;

    IF parent_status <> 'draft' THEN
        RAISE EXCEPTION 'only manual draft sales order lines are mutable' USING ERRCODE = '55000';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    IF NEW.company_id IS DISTINCT FROM OLD.company_id
        OR NEW.sales_order_id IS DISTINCT FROM OLD.sales_order_id
        OR NEW.source_quote_revision_line_id IS NOT NULL THEN
        RAISE EXCEPTION 'manual sales order line identity/lineage is immutable' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$
SQL);
        DB::statement('CREATE TRIGGER sales_order_lines_source_immutable BEFORE UPDATE OR DELETE ON sales_order_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_order_line_mutation()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_order_lines_source_immutable ON sales_order_lines');
        DB::statement('DROP TRIGGER IF EXISTS sales_orders_source_immutable ON sales_orders');
        DB::statement('DROP TRIGGER IF EXISTS sales_order_lines_insert_guard ON sales_order_lines');
        DB::statement('DROP TRIGGER IF EXISTS sales_orders_insert_guard ON sales_orders');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_order_line_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_order_mutation()');

        DB::table('sales_order_lines')->whereNull('source_quote_revision_line_id')->delete();
        DB::table('sales_orders')->whereNull('source_quote_id')->delete();

        DB::statement('ALTER TABLE sales_orders DROP CONSTRAINT IF EXISTS sales_orders_source_shape_check');
        DB::statement('ALTER TABLE sales_orders ALTER COLUMN source_quote_id SET NOT NULL');
        DB::statement('ALTER TABLE sales_orders ALTER COLUMN source_quote_revision_id SET NOT NULL');
        DB::statement('ALTER TABLE sales_order_lines ALTER COLUMN source_quote_revision_line_id SET NOT NULL');

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

        foreach (['sales_orders.view', 'sales_orders.manage'] as $key) {
            $permissionId = DB::table('permissions')->where('key', $key)->value('id');
            if (is_int($permissionId)) {
                DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            }
        }
        DB::table('permissions')->whereIn('key', ['sales_orders.view', 'sales_orders.manage'])->delete();
    }
};
