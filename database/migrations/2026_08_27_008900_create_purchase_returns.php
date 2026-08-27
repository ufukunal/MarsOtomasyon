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
                'key' => 'purchase_returns.view',
                'name' => 'Satınalma iadesi görüntüleme',
                'description' => 'Kesinleşmiş mal kabul ve alış faturası lineage üzerinden satınalma iadelerini görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'purchase_returns.manage',
                'name' => 'Satınalma iadesi yönetimi',
                'description' => 'Satınalma iadesi oluşturma ve stok/cari etkisini atomik kesinleştirme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('account_id');
            $table->string('number', 64);
            $table->string('series_code', 64);
            $table->unsignedBigInteger('sequence_value');
            $table->string('status', 16)->default('draft');
            $table->timestampTz('finalized_at')->nullable();
            $table->date('return_date');
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

            $table->unique(['company_id', 'id'], 'purchase_returns_company_id_id_unique');
            $table->unique(['company_id', 'number'], 'purchase_returns_company_number_unique');
            $table->unique(['company_id', 'series_code', 'sequence_value'], 'purchase_returns_company_series_sequence_unique');
            $table->foreign(['company_id', 'purchase_order_id'])
                ->references(['company_id', 'id'])->on('purchase_orders')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['company_id', 'status', 'return_date'], 'purchase_returns_company_status_date_index');
            $table->index(['company_id', 'purchase_order_id'], 'purchase_returns_company_purchase_order_index');
        });

        DB::statement("ALTER TABLE purchase_returns ADD CONSTRAINT purchase_returns_status_check CHECK (status IN ('draft', 'finalized'))");
        DB::statement("ALTER TABLE purchase_returns ADD CONSTRAINT purchase_returns_series_code_canonical_check CHECK (series_code = lower(btrim(series_code)) AND series_code ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE purchase_returns ADD CONSTRAINT purchase_returns_document_discount_rate_check CHECK (document_discount_rate >= 0 AND document_discount_rate <= 100)');
        DB::statement('ALTER TABLE purchase_returns ADD CONSTRAINT purchase_returns_totals_nonnegative_check CHECK (base_net_total >= 0 AND line_discount_total >= 0 AND document_discount_total >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE purchase_returns ADD CONSTRAINT purchase_returns_total_reconciliation_check CHECK (base_net_total - line_discount_total - document_discount_total = net_total AND net_total + tax_total = gross_total)');
        DB::statement("ALTER TABLE purchase_returns ADD CONSTRAINT purchase_returns_lifecycle_timestamp_check CHECK ((status = 'draft' AND finalized_at IS NULL) OR (status = 'finalized' AND finalized_at IS NOT NULL))");

        Schema::create('purchase_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('purchase_return_id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('purchase_order_line_id');
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('goods_receipt_line_id');
            $table->unsignedBigInteger('supplier_invoice_id');
            $table->unsignedBigInteger('supplier_invoice_line_id');
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
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
            $table->boolean('tax_is_zeroed')->default(false);
            $table->unsignedBigInteger('tax_zero_reason_id')->nullable();
            $table->string('tax_zero_reason_code', 32)->nullable();
            $table->decimal('base_net', 20, 6);
            $table->decimal('line_discount_net', 20, 6);
            $table->decimal('document_discount_net', 20, 6);
            $table->decimal('net_total', 20, 6);
            $table->decimal('tax_total', 20, 6);
            $table->decimal('gross_total', 20, 6);
            $table->timestampsTz();

            $table->unique(['company_id', 'purchase_return_id', 'id'], 'purchase_return_lines_company_return_id_unique');
            $table->unique(['company_id', 'purchase_return_id', 'position'], 'purchase_return_lines_position_unique');
            $table->unique(['company_id', 'purchase_return_id', 'goods_receipt_line_id', 'supplier_invoice_line_id'], 'purchase_return_lines_source_pair_unique');
            $table->foreign(['company_id', 'purchase_return_id'])
                ->references(['company_id', 'id'])->on('purchase_returns')->restrictOnDelete();
            $table->foreign(['company_id', 'purchase_order_id', 'purchase_order_line_id'])
                ->references(['company_id', 'purchase_order_id', 'id'])->on('purchase_order_lines')->restrictOnDelete();
            $table->foreign(['company_id', 'goods_receipt_id', 'goods_receipt_line_id'])
                ->references(['company_id', 'goods_receipt_id', 'id'])->on('goods_receipt_lines')->restrictOnDelete();
            $table->foreign(['company_id', 'supplier_invoice_id', 'supplier_invoice_line_id'])
                ->references(['company_id', 'supplier_invoice_id', 'id'])->on('supplier_invoice_lines')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])->on('warehouse_locations')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_id'])
                ->references(['company_id', 'id'])->on('taxes')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_zero_reason_id'])
                ->references(['company_id', 'id'])->on('tax_zero_reasons')->restrictOnDelete();
            $table->index(['company_id', 'goods_receipt_line_id'], 'purchase_return_lines_goods_receipt_line_index');
            $table->index(['company_id', 'supplier_invoice_line_id'], 'purchase_return_lines_supplier_invoice_line_index');
        });

        DB::statement("ALTER TABLE purchase_return_lines ADD CONSTRAINT purchase_return_lines_price_basis_check CHECK (price_basis IN ('net', 'gross'))");
        DB::statement('ALTER TABLE purchase_return_lines ADD CONSTRAINT purchase_return_lines_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE purchase_return_lines ADD CONSTRAINT purchase_return_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchase_return_lines ADD CONSTRAINT purchase_return_lines_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE purchase_return_lines ADD CONSTRAINT purchase_return_lines_discount_rate_check CHECK (line_discount_rate >= 0 AND line_discount_rate <= 100)');
        DB::statement('ALTER TABLE purchase_return_lines ADD CONSTRAINT purchase_return_lines_tax_rate_check CHECK (tax_rate >= 0 AND tax_rate <= 100)');
        DB::statement('ALTER TABLE purchase_return_lines ADD CONSTRAINT purchase_return_lines_amounts_nonnegative_check CHECK (base_net >= 0 AND line_discount_net >= 0 AND document_discount_net >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE purchase_return_lines ADD CONSTRAINT purchase_return_lines_total_reconciliation_check CHECK (base_net - line_discount_net - document_discount_net = net_total AND net_total + tax_total = gross_total)');
        DB::statement('ALTER TABLE purchase_return_lines ADD CONSTRAINT purchase_return_lines_zero_reason_shape_check CHECK ((tax_rate = 0 AND tax_zero_reason_id IS NOT NULL AND tax_zero_reason_code IS NOT NULL) OR (tax_rate > 0 AND tax_zero_reason_id IS NULL AND tax_zero_reason_code IS NULL))');

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

CREATE TRIGGER purchase_returns_source_guard
BEFORE INSERT OR UPDATE OF company_id, purchase_order_id, account_id, currency_code, document_discount_rate ON purchase_returns
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_return_source();
SQL);

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
    accepted_quantity numeric(20, 6);
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

    SELECT accepted_quantity INTO accepted_quantity
    FROM goods_receipt_line_quality
    WHERE company_id = NEW.company_id AND goods_receipt_line_id = NEW.goods_receipt_line_id;
    IF accepted_quantity IS NULL OR accepted_quantity <= 0 OR NEW.quantity > accepted_quantity THEN
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

CREATE TRIGGER purchase_return_lines_source_guard
BEFORE INSERT OR UPDATE ON purchase_return_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_return_line_source();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_return_lifecycle()
RETURNS trigger AS $$
DECLARE
    account_effect_id bigint;
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'purchase returns cannot be deleted' USING ERRCODE = '55000';
    END IF;
    IF OLD.status = 'finalized' THEN
        RAISE EXCEPTION 'finalized purchase return is immutable' USING ERRCODE = '55000';
    END IF;
    IF NEW.company_id IS DISTINCT FROM OLD.company_id
       OR NEW.purchase_order_id IS DISTINCT FROM OLD.purchase_order_id
       OR NEW.account_id IS DISTINCT FROM OLD.account_id
       OR NEW.number IS DISTINCT FROM OLD.number
       OR NEW.series_code IS DISTINCT FROM OLD.series_code
       OR NEW.sequence_value IS DISTINCT FROM OLD.sequence_value THEN
        RAISE EXCEPTION 'purchase return document identity is immutable' USING ERRCODE = '55000';
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'draft' THEN
        IF NEW.finalized_at IS NOT NULL THEN
            RAISE EXCEPTION 'draft purchase return cannot have finalized_at' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'finalized' THEN
        IF NEW.finalized_at IS NULL THEN
            RAISE EXCEPTION 'purchase return finalization timestamp is required' USING ERRCODE = '23514';
        END IF;
        IF (to_jsonb(NEW) - 'status' - 'finalized_at' - 'updated_at') IS DISTINCT FROM
           (to_jsonb(OLD) - 'status' - 'finalized_at' - 'updated_at') THEN
            RAISE EXCEPTION 'purchase return finalization may only change lifecycle fields' USING ERRCODE = '23514';
        END IF;

        SELECT transaction.id INTO account_effect_id
        FROM account_transactions AS transaction
        WHERE transaction.company_id = OLD.company_id
          AND transaction.account_id = OLD.account_id
          AND transaction.posting_date = OLD.return_date
          AND transaction.currency_code = OLD.currency_code
          AND transaction.signed_amount = OLD.gross_total
          AND transaction.source_type = 'purchase_return'
          AND transaction.source_id = OLD.id::text
          AND transaction.effect_type = 'account.purchase_return'
          AND transaction.reversal_of_transaction_id IS NULL;
        IF account_effect_id IS NULL THEN
            RAISE EXCEPTION 'purchase return finalization requires exact supplier account correction' USING ERRCODE = '23514';
        END IF;

        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'invalid purchase return lifecycle transition' USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER purchase_returns_lifecycle_guard
BEFORE UPDATE OR DELETE ON purchase_returns
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_return_lifecycle();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_return_line_lifecycle()
RETURNS trigger AS $$
DECLARE
    parent_status text;
BEGIN
    IF TG_OP IN ('UPDATE', 'DELETE') THEN
        SELECT status INTO parent_status
        FROM purchase_returns
        WHERE company_id = OLD.company_id AND id = OLD.purchase_return_id;
        IF parent_status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'finalized purchase return lines are immutable' USING ERRCODE = '55000';
        END IF;
    END IF;

    IF TG_OP IN ('INSERT', 'UPDATE') THEN
        SELECT status INTO parent_status
        FROM purchase_returns
        WHERE company_id = NEW.company_id AND id = NEW.purchase_return_id;
        IF parent_status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'purchase return lines require draft parent' USING ERRCODE = '55000';
        END IF;
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER purchase_return_lines_lifecycle_guard
BEFORE INSERT OR UPDATE OR DELETE ON purchase_return_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_return_line_lifecycle();
SQL);

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_direction_check');
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_type_check
CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'dispatch_out', 'invoice_out', 'goods_receipt_in', 'purchase_return_out'))
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_direction_check
CHECK (
    (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in', 'goods_receipt_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
    OR
    (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out', 'purchase_return_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_purchase_return_source_check
CHECK (
    movement_type <> 'purchase_return_out'
    OR (
        source_type = 'purchase_return_line'
        AND effect_type = 'stock.out'
        AND source_id ~ '^[1-9][0-9]*$'
        AND reversal_of_movement_id IS NULL
    )
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_return_stock_out()
RETURNS trigger AS $$
DECLARE
    source_line purchase_return_lines%ROWTYPE;
    parent_status text;
BEGIN
    IF NEW.movement_type <> 'purchase_return_out' THEN
        RETURN NEW;
    END IF;

    SELECT * INTO source_line
    FROM purchase_return_lines
    WHERE company_id = NEW.company_id AND id = CAST(NEW.source_id AS bigint)
    FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'purchase_return_out source line not found' USING ERRCODE = '23514';
    END IF;

    SELECT status INTO parent_status
    FROM purchase_returns
    WHERE company_id = source_line.company_id AND id = source_line.purchase_return_id
    FOR SHARE;

    IF parent_status IS DISTINCT FROM 'draft'
       OR NEW.product_id IS DISTINCT FROM source_line.product_id
       OR NEW.warehouse_id IS DISTINCT FROM source_line.warehouse_id
       OR NEW.location_id IS DISTINCT FROM source_line.location_id
       OR NEW.quantity_delta IS DISTINCT FROM -source_line.quantity THEN
        RAISE EXCEPTION 'purchase_return_out must exactly match draft purchase return physical lineage' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER stock_movements_purchase_return_out_guard
BEFORE INSERT ON stock_movements
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_return_stock_out();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_return_finalization_commit()
RETURNS trigger AS $$
DECLARE
    line_count integer;
    sum_base numeric(20, 6);
    sum_line_discount numeric(20, 6);
    sum_document_discount numeric(20, 6);
    sum_net numeric(20, 6);
    sum_tax numeric(20, 6);
    sum_gross numeric(20, 6);
BEGIN
    IF OLD.status <> 'draft' OR NEW.status <> 'finalized' THEN
        RETURN NEW;
    END IF;

    SELECT COUNT(*),
           COALESCE(SUM(base_net), 0),
           COALESCE(SUM(line_discount_net), 0),
           COALESCE(SUM(document_discount_net), 0),
           COALESCE(SUM(net_total), 0),
           COALESCE(SUM(tax_total), 0),
           COALESCE(SUM(gross_total), 0)
      INTO line_count, sum_base, sum_line_discount, sum_document_discount, sum_net, sum_tax, sum_gross
    FROM purchase_return_lines
    WHERE company_id = NEW.company_id AND purchase_return_id = NEW.id;

    IF line_count = 0
       OR sum_base IS DISTINCT FROM NEW.base_net_total
       OR sum_line_discount IS DISTINCT FROM NEW.line_discount_total
       OR sum_document_discount IS DISTINCT FROM NEW.document_discount_total
       OR sum_net IS DISTINCT FROM NEW.net_total
       OR sum_tax IS DISTINCT FROM NEW.tax_total
       OR sum_gross IS DISTINCT FROM NEW.gross_total THEN
        RAISE EXCEPTION 'purchase return header totals must exactly reconcile to lines' USING ERRCODE = '23514';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM purchase_return_lines AS line
        WHERE line.company_id = NEW.company_id
          AND line.purchase_return_id = NEW.id
          AND NOT EXISTS (
              SELECT 1 FROM stock_movements AS movement
              WHERE movement.company_id = line.company_id
                AND movement.source_type = 'purchase_return_line'
                AND movement.source_id = line.id::text
                AND movement.effect_type = 'stock.out'
                AND movement.movement_type = 'purchase_return_out'
                AND movement.product_id = line.product_id
                AND movement.warehouse_id = line.warehouse_id
                AND movement.location_id = line.location_id
                AND movement.quantity_delta = -line.quantity
                AND movement.reversal_of_movement_id IS NULL
          )
    ) THEN
        RAISE EXCEPTION 'finalized purchase return requires exact stock out for every line' USING ERRCODE = '23514';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM (
            SELECT line.company_id, line.goods_receipt_line_id, SUM(line.quantity)::numeric(20, 6) AS returned_quantity
            FROM purchase_return_lines AS line
            INNER JOIN purchase_returns AS purchase_return
              ON purchase_return.company_id = line.company_id
             AND purchase_return.id = line.purchase_return_id
            WHERE purchase_return.status = 'finalized'
            GROUP BY line.company_id, line.goods_receipt_line_id
        ) AS returned
        LEFT JOIN goods_receipt_line_quality AS quality
          ON quality.company_id = returned.company_id
         AND quality.goods_receipt_line_id = returned.goods_receipt_line_id
        WHERE returned.company_id = NEW.company_id
          AND (quality.accepted_quantity IS NULL OR returned.returned_quantity > quality.accepted_quantity)
    ) THEN
        RAISE EXCEPTION 'finalized purchase return exceeds accepted goods receipt physical lineage' USING ERRCODE = '23514';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM (
            SELECT line.company_id, line.supplier_invoice_line_id, SUM(line.quantity)::numeric(20, 6) AS returned_quantity
            FROM purchase_return_lines AS line
            INNER JOIN purchase_returns AS purchase_return
              ON purchase_return.company_id = line.company_id
             AND purchase_return.id = line.purchase_return_id
            WHERE purchase_return.status = 'finalized'
            GROUP BY line.company_id, line.supplier_invoice_line_id
        ) AS returned
        INNER JOIN supplier_invoice_lines AS source_line
          ON source_line.company_id = returned.company_id
         AND source_line.id = returned.supplier_invoice_line_id
        WHERE returned.company_id = NEW.company_id
          AND returned.returned_quantity > source_line.quantity
    ) THEN
        RAISE EXCEPTION 'finalized purchase return exceeds supplier invoice financial lineage' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER purchase_returns_finalization_commit_guard
AFTER UPDATE ON purchase_returns
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_return_finalization_commit();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS purchase_returns_finalization_commit_guard ON purchase_returns');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_return_finalization_commit()');
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_purchase_return_out_guard ON stock_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_return_stock_out()');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_purchase_return_source_check');

        DB::statement('DROP TRIGGER IF EXISTS stock_movements_immutable_trigger ON stock_movements');
        DB::statement("UPDATE stock_movements SET movement_type = 'adjustment_out' WHERE movement_type = 'purchase_return_out'");
        DB::unprepared(<<<'SQL'
CREATE TRIGGER stock_movements_immutable_trigger
BEFORE UPDATE OR DELETE ON stock_movements
FOR EACH ROW EXECUTE FUNCTION mars_prevent_stock_movement_mutation();
SQL);

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_direction_check');
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_type_check
CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'dispatch_out', 'invoice_out', 'goods_receipt_in'))
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_direction_check
CHECK (
    (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in', 'goods_receipt_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
    OR
    (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
)
SQL);

        DB::statement('DROP TRIGGER IF EXISTS purchase_return_lines_lifecycle_guard ON purchase_return_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_return_line_lifecycle()');
        DB::statement('DROP TRIGGER IF EXISTS purchase_returns_lifecycle_guard ON purchase_returns');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_return_lifecycle()');
        DB::statement('DROP TRIGGER IF EXISTS purchase_return_lines_source_guard ON purchase_return_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_return_line_source()');
        DB::statement('DROP TRIGGER IF EXISTS purchase_returns_source_guard ON purchase_returns');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_return_source()');

        Schema::dropIfExists('purchase_return_lines');
        Schema::dropIfExists('purchase_returns');

        foreach (['purchase_returns.view', 'purchase_returns.manage'] as $key) {
            $permissionId = DB::table('permissions')->where('key', $key)->value('id');
            if (is_int($permissionId)) {
                DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }
    }
};
