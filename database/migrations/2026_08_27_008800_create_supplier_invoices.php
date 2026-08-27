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
                'key' => 'supplier_invoices.view',
                'name' => 'Alış faturası görüntüleme',
                'description' => 'Aktif şirkette alış faturalarını ve satınalma siparişi faturalama ilerlemesini görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'supplier_invoices.manage',
                'name' => 'Alış faturası yönetimi',
                'description' => 'Alış faturası taslağı oluşturma, düzenleme ve cari etkisini kesinleştirme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('supplier_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('account_id');
            $table->string('number', 64);
            $table->string('series_code', 64);
            $table->unsignedBigInteger('sequence_value');
            $table->string('status', 16)->default('draft');
            $table->timestampTz('finalized_at')->nullable();
            $table->date('invoice_date');
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

            $table->unique(['company_id', 'id'], 'supplier_invoices_company_id_id_unique');
            $table->unique(['company_id', 'number'], 'supplier_invoices_company_number_unique');
            $table->unique(['company_id', 'series_code', 'sequence_value'], 'supplier_invoices_company_series_sequence_unique');
            $table->foreign(['company_id', 'purchase_order_id'])
                ->references(['company_id', 'id'])->on('purchase_orders')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['company_id', 'status', 'invoice_date'], 'supplier_invoices_company_status_date_index');
            $table->index(['company_id', 'purchase_order_id'], 'supplier_invoices_company_purchase_order_index');
        });

        DB::statement("ALTER TABLE supplier_invoices ADD CONSTRAINT supplier_invoices_status_check CHECK (status IN ('draft', 'finalized'))");
        DB::statement("ALTER TABLE supplier_invoices ADD CONSTRAINT supplier_invoices_series_code_canonical_check CHECK (series_code = lower(btrim(series_code)) AND series_code ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE supplier_invoices ADD CONSTRAINT supplier_invoices_document_discount_rate_check CHECK (document_discount_rate >= 0 AND document_discount_rate <= 100)');
        DB::statement('ALTER TABLE supplier_invoices ADD CONSTRAINT supplier_invoices_totals_nonnegative_check CHECK (base_net_total >= 0 AND line_discount_total >= 0 AND document_discount_total >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE supplier_invoices ADD CONSTRAINT supplier_invoices_total_reconciliation_check CHECK (base_net_total - line_discount_total - document_discount_total = net_total AND net_total + tax_total = gross_total)');
        DB::statement("ALTER TABLE supplier_invoices ADD CONSTRAINT supplier_invoices_lifecycle_timestamp_check CHECK ((status = 'draft' AND finalized_at IS NULL) OR (status = 'finalized' AND finalized_at IS NOT NULL))");

        Schema::create('supplier_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('supplier_invoice_id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('purchase_order_line_id');
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

            $table->unique(['company_id', 'supplier_invoice_id', 'id'], 'supplier_invoice_lines_company_invoice_id_unique');
            $table->unique(['company_id', 'supplier_invoice_id', 'position'], 'supplier_invoice_lines_position_unique');
            $table->unique(['company_id', 'supplier_invoice_id', 'purchase_order_line_id'], 'supplier_invoice_lines_source_unique');
            $table->foreign(['company_id', 'supplier_invoice_id'])
                ->references(['company_id', 'id'])->on('supplier_invoices')->restrictOnDelete();
            $table->foreign(
                ['company_id', 'purchase_order_id', 'purchase_order_line_id'],
                'supplier_invoice_lines_purchase_order_line_fk',
            )->references(['company_id', 'purchase_order_id', 'id'])->on('purchase_order_lines')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_id'])
                ->references(['company_id', 'id'])->on('taxes')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_zero_reason_id'])
                ->references(['company_id', 'id'])->on('tax_zero_reasons')->restrictOnDelete();
            $table->index(['company_id', 'purchase_order_line_id'], 'supplier_invoice_lines_purchase_line_index');
        });

        DB::statement("ALTER TABLE supplier_invoice_lines ADD CONSTRAINT supplier_invoice_lines_price_basis_check CHECK (price_basis IN ('net', 'gross'))");
        DB::statement('ALTER TABLE supplier_invoice_lines ADD CONSTRAINT supplier_invoice_lines_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE supplier_invoice_lines ADD CONSTRAINT supplier_invoice_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE supplier_invoice_lines ADD CONSTRAINT supplier_invoice_lines_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE supplier_invoice_lines ADD CONSTRAINT supplier_invoice_lines_discount_rate_check CHECK (line_discount_rate >= 0 AND line_discount_rate <= 100)');
        DB::statement('ALTER TABLE supplier_invoice_lines ADD CONSTRAINT supplier_invoice_lines_tax_rate_check CHECK (tax_rate >= 0 AND tax_rate <= 100)');
        DB::statement('ALTER TABLE supplier_invoice_lines ADD CONSTRAINT supplier_invoice_lines_amounts_nonnegative_check CHECK (base_net >= 0 AND line_discount_net >= 0 AND document_discount_net >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE supplier_invoice_lines ADD CONSTRAINT supplier_invoice_lines_total_reconciliation_check CHECK (base_net - line_discount_net - document_discount_net = net_total AND net_total + tax_total = gross_total)');
        DB::statement('ALTER TABLE supplier_invoice_lines ADD CONSTRAINT supplier_invoice_lines_zero_reason_shape_check CHECK ((tax_rate = 0 AND tax_zero_reason_id IS NOT NULL AND tax_zero_reason_code IS NOT NULL) OR (tax_rate > 0 AND tax_zero_reason_id IS NULL AND tax_zero_reason_code IS NULL))');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_supplier_invoice_source()
RETURNS trigger AS $$
DECLARE
    source_order purchase_orders%ROWTYPE;
    supplier_type text;
    supplier_status text;
BEGIN
    SELECT * INTO source_order
    FROM purchase_orders
    WHERE company_id = NEW.company_id
      AND id = NEW.purchase_order_id
    FOR SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'supplier invoice source purchase order not found' USING ERRCODE = '23503';
    END IF;

    IF NEW.account_id IS DISTINCT FROM source_order.account_id
       OR NEW.currency_code IS DISTINCT FROM source_order.currency_code
       OR NEW.document_discount_rate IS DISTINCT FROM source_order.document_discount_rate THEN
        RAISE EXCEPTION 'supplier invoice supplier/currency/discount must match source purchase order' USING ERRCODE = '23514';
    END IF;

    SELECT type, status INTO supplier_type, supplier_status
    FROM accounts
    WHERE company_id = NEW.company_id AND id = NEW.account_id
    FOR SHARE;

    IF supplier_status IS DISTINCT FROM 'active' OR supplier_type NOT IN ('supplier', 'mixed') THEN
        RAISE EXCEPTION 'supplier invoice requires active supplier or mixed account' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER supplier_invoices_source_guard
BEFORE INSERT OR UPDATE OF company_id, purchase_order_id, account_id, currency_code, document_discount_rate ON supplier_invoices
FOR EACH ROW EXECUTE FUNCTION mars_guard_supplier_invoice_source();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_supplier_invoice_line_source()
RETURNS trigger AS $$
DECLARE
    parent_order_id bigint;
    parent_status text;
    source_line purchase_order_lines%ROWTYPE;
BEGIN
    SELECT purchase_order_id, status
      INTO parent_order_id, parent_status
    FROM supplier_invoices
    WHERE company_id = NEW.company_id AND id = NEW.supplier_invoice_id
    FOR SHARE;

    IF parent_order_id IS NULL OR parent_status IS NULL THEN
        RAISE EXCEPTION 'supplier invoice line parent not found' USING ERRCODE = '23503';
    END IF;
    IF parent_status <> 'draft' THEN
        RAISE EXCEPTION 'supplier invoice lines require draft parent' USING ERRCODE = '55000';
    END IF;
    IF NEW.purchase_order_id IS DISTINCT FROM parent_order_id THEN
        RAISE EXCEPTION 'supplier invoice line purchase order must match parent' USING ERRCODE = '23514';
    END IF;

    SELECT * INTO source_line
    FROM purchase_order_lines
    WHERE company_id = NEW.company_id
      AND purchase_order_id = NEW.purchase_order_id
      AND id = NEW.purchase_order_line_id
    FOR SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'supplier invoice source purchase order line not found' USING ERRCODE = '23503';
    END IF;

    IF NEW.product_id IS DISTINCT FROM source_line.product_id
       OR NEW.product_code IS DISTINCT FROM source_line.product_code
       OR NEW.product_name IS DISTINCT FROM source_line.product_name
       OR NEW.description IS DISTINCT FROM source_line.description
       OR NEW.price_basis IS DISTINCT FROM source_line.price_basis
       OR NEW.unit_price IS DISTINCT FROM source_line.unit_price
       OR NEW.line_discount_rate IS DISTINCT FROM source_line.line_discount_rate
       OR NEW.tax_id IS DISTINCT FROM source_line.tax_id
       OR NEW.tax_code IS DISTINCT FROM source_line.tax_code
       OR NEW.tax_rate IS DISTINCT FROM source_line.tax_rate
       OR NEW.tax_is_zeroed IS DISTINCT FROM source_line.tax_is_zeroed
       OR NEW.tax_zero_reason_id IS DISTINCT FROM source_line.tax_zero_reason_id
       OR NEW.tax_zero_reason_code IS DISTINCT FROM source_line.tax_zero_reason_code THEN
        RAISE EXCEPTION 'supplier invoice line commercial identity must match source purchase order line' USING ERRCODE = '23514';
    END IF;

    IF NEW.quantity > source_line.quantity THEN
        RAISE EXCEPTION 'supplier invoice line quantity cannot exceed source ordered quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER supplier_invoice_lines_source_guard
BEFORE INSERT OR UPDATE ON supplier_invoice_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_supplier_invoice_line_source();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_supplier_invoice_lifecycle()
RETURNS trigger AS $$
DECLARE
    account_effect_id bigint;
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'supplier invoices cannot be deleted' USING ERRCODE = '55000';
    END IF;

    IF OLD.status = 'finalized' THEN
        RAISE EXCEPTION 'finalized supplier invoice is immutable' USING ERRCODE = '55000';
    END IF;

    IF NEW.company_id IS DISTINCT FROM OLD.company_id
       OR NEW.purchase_order_id IS DISTINCT FROM OLD.purchase_order_id
       OR NEW.account_id IS DISTINCT FROM OLD.account_id
       OR NEW.number IS DISTINCT FROM OLD.number
       OR NEW.series_code IS DISTINCT FROM OLD.series_code
       OR NEW.sequence_value IS DISTINCT FROM OLD.sequence_value THEN
        RAISE EXCEPTION 'supplier invoice document identity is immutable' USING ERRCODE = '55000';
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'draft' THEN
        IF NEW.finalized_at IS NOT NULL THEN
            RAISE EXCEPTION 'draft supplier invoice cannot have finalized_at' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'finalized' THEN
        IF NEW.finalized_at IS NULL THEN
            RAISE EXCEPTION 'supplier invoice finalization timestamp is required' USING ERRCODE = '23514';
        END IF;

        IF (to_jsonb(NEW) - 'status' - 'finalized_at' - 'updated_at') IS DISTINCT FROM
           (to_jsonb(OLD) - 'status' - 'finalized_at' - 'updated_at') THEN
            RAISE EXCEPTION 'supplier invoice finalization may only change lifecycle fields' USING ERRCODE = '23514';
        END IF;

        SELECT transaction.id INTO account_effect_id
        FROM account_transactions AS transaction
        WHERE transaction.company_id = OLD.company_id
          AND transaction.account_id = OLD.account_id
          AND transaction.posting_date = OLD.invoice_date
          AND transaction.currency_code = OLD.currency_code
          AND transaction.signed_amount = -OLD.gross_total
          AND transaction.source_type = 'supplier_invoice'
          AND transaction.source_id = OLD.id::text
          AND transaction.effect_type = 'account.supplier_invoice'
          AND transaction.reversal_of_transaction_id IS NULL;

        IF account_effect_id IS NULL THEN
            RAISE EXCEPTION 'supplier invoice finalization requires exact account effect' USING ERRCODE = '23514';
        END IF;

        IF EXISTS (
            SELECT 1 FROM stock_movements
            WHERE company_id = OLD.company_id
              AND (
                  (source_type = 'supplier_invoice' AND source_id = OLD.id::text)
                  OR (
                      source_type = 'supplier_invoice_line'
                      AND source_id IN (
                          SELECT line.id::text FROM supplier_invoice_lines AS line
                          WHERE line.company_id = OLD.company_id AND line.supplier_invoice_id = OLD.id
                      )
                  )
              )
        ) THEN
            RAISE EXCEPTION 'supplier invoice must not create a second stock in/out effect' USING ERRCODE = '23514';
        END IF;

        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'invalid supplier invoice lifecycle transition' USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER supplier_invoices_lifecycle_guard
BEFORE UPDATE OR DELETE ON supplier_invoices
FOR EACH ROW EXECUTE FUNCTION mars_guard_supplier_invoice_lifecycle();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_supplier_invoice_line_lifecycle()
RETURNS trigger AS $$
DECLARE
    parent_status text;
BEGIN
    IF TG_OP IN ('UPDATE', 'DELETE') THEN
        SELECT status INTO parent_status
        FROM supplier_invoices
        WHERE company_id = OLD.company_id AND id = OLD.supplier_invoice_id;
        IF parent_status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'finalized supplier invoice lines are immutable' USING ERRCODE = '55000';
        END IF;
    END IF;

    IF TG_OP IN ('INSERT', 'UPDATE') THEN
        SELECT status INTO parent_status
        FROM supplier_invoices
        WHERE company_id = NEW.company_id AND id = NEW.supplier_invoice_id;
        IF parent_status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'supplier invoice lines require draft parent' USING ERRCODE = '55000';
        END IF;
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER supplier_invoice_lines_lifecycle_guard
BEFORE INSERT OR UPDATE OR DELETE ON supplier_invoice_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_supplier_invoice_line_lifecycle();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_supplier_invoice_finalization_commit()
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
    FROM supplier_invoice_lines
    WHERE company_id = NEW.company_id AND supplier_invoice_id = NEW.id;

    IF line_count = 0
       OR sum_base IS DISTINCT FROM NEW.base_net_total
       OR sum_line_discount IS DISTINCT FROM NEW.line_discount_total
       OR sum_document_discount IS DISTINCT FROM NEW.document_discount_total
       OR sum_net IS DISTINCT FROM NEW.net_total
       OR sum_tax IS DISTINCT FROM NEW.tax_total
       OR sum_gross IS DISTINCT FROM NEW.gross_total THEN
        RAISE EXCEPTION 'supplier invoice header totals must exactly reconcile to lines' USING ERRCODE = '23514';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM supplier_invoice_lines AS line
        WHERE line.company_id = NEW.company_id
          AND line.supplier_invoice_id = NEW.id
          AND NOT EXISTS (
              SELECT 1
              FROM purchase_order_line_progress_effects AS effect
              WHERE effect.company_id = line.company_id
                AND effect.purchase_order_id = line.purchase_order_id
                AND effect.purchase_order_line_id = line.purchase_order_line_id
                AND effect.progress_type = 'invoiced'
                AND effect.quantity_delta = line.quantity
                AND effect.source_type = 'supplier_invoice_line'
                AND effect.source_id = line.id::text
                AND effect.effect_type = 'progress.invoice'
          )
    ) THEN
        RAISE EXCEPTION 'finalized supplier invoice requires exact purchase order invoice progress' USING ERRCODE = '23514';
    END IF;

    IF EXISTS (
        SELECT 1 FROM stock_movements
        WHERE company_id = NEW.company_id
          AND (
              (source_type = 'supplier_invoice' AND source_id = NEW.id::text)
              OR (
                  source_type = 'supplier_invoice_line'
                  AND source_id IN (
                      SELECT line.id::text FROM supplier_invoice_lines AS line
                      WHERE line.company_id = NEW.company_id AND line.supplier_invoice_id = NEW.id
                  )
              )
          )
    ) THEN
        RAISE EXCEPTION 'finalized supplier invoice cannot create stock movement' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER supplier_invoice_finalization_commit_guard
AFTER UPDATE ON supplier_invoices
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_supplier_invoice_finalization_commit();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS supplier_invoice_finalization_commit_guard ON supplier_invoices');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_supplier_invoice_finalization_commit()');
        DB::statement('DROP TRIGGER IF EXISTS supplier_invoice_lines_lifecycle_guard ON supplier_invoice_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_supplier_invoice_line_lifecycle()');
        DB::statement('DROP TRIGGER IF EXISTS supplier_invoices_lifecycle_guard ON supplier_invoices');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_supplier_invoice_lifecycle()');
        DB::statement('DROP TRIGGER IF EXISTS supplier_invoice_lines_source_guard ON supplier_invoice_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_supplier_invoice_line_source()');
        DB::statement('DROP TRIGGER IF EXISTS supplier_invoices_source_guard ON supplier_invoices');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_supplier_invoice_source()');

        Schema::dropIfExists('supplier_invoice_lines');
        Schema::dropIfExists('supplier_invoices');

        foreach (['supplier_invoices.view', 'supplier_invoices.manage'] as $key) {
            $permissionId = DB::table('permissions')->where('key', $key)->value('id');
            if (is_int($permissionId)) {
                DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }
    }
};
