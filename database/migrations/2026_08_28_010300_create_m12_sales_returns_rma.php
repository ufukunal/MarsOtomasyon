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
                'key' => 'sales_returns.view',
                'name' => 'Satış iadesi / RMA görüntüleme',
                'description' => 'Kesinleşmiş satış faturası lineage üzerinden satış iadelerini ve RMA durumlarını görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'sales_returns.manage',
                'name' => 'Satış iadesi / RMA yönetimi',
                'description' => 'RMA oluşturma, yetkilendirme, fiziksel kabul kontrolü ve stok/cari etkilerini atomik tamamlama yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('sales_invoice_lines', function (Blueprint $table): void {
            $table->unique(['company_id', 'id'], 'sales_invoice_lines_company_id_id_m12_unique');
        });

        Schema::create('sales_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sales_invoice_id');
            $table->unsignedBigInteger('account_id');
            $table->string('number', 64);
            $table->string('series_code', 64);
            $table->unsignedBigInteger('sequence_value');
            $table->string('status', 16)->default('draft');
            $table->date('return_date');
            $table->char('currency_code', 3);
            $table->decimal('requested_net_total', 20, 6);
            $table->decimal('requested_tax_total', 20, 6);
            $table->decimal('requested_gross_total', 20, 6);
            $table->decimal('credited_net_total', 20, 6)->default(0);
            $table->decimal('credited_tax_total', 20, 6)->default(0);
            $table->decimal('credited_gross_total', 20, 6)->default(0);
            $table->timestampTz('authorized_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'sales_returns_company_id_id_unique');
            $table->unique(['company_id', 'number'], 'sales_returns_company_number_unique');
            $table->unique(['company_id', 'series_code', 'sequence_value'], 'sales_returns_company_series_sequence_unique');
            $table->foreign(['company_id', 'sales_invoice_id'])
                ->references(['company_id', 'id'])->on('sales_invoices')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['company_id', 'status', 'return_date'], 'sales_returns_company_status_date_index');
            $table->index(['company_id', 'sales_invoice_id'], 'sales_returns_company_invoice_index');
        });

        DB::statement("ALTER TABLE sales_returns ADD CONSTRAINT sales_returns_status_check CHECK (status IN ('draft', 'authorized', 'received', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE sales_returns ADD CONSTRAINT sales_returns_series_code_canonical_check CHECK (series_code = lower(btrim(series_code)) AND series_code ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE sales_returns ADD CONSTRAINT sales_returns_requested_totals_check CHECK (requested_net_total >= 0 AND requested_tax_total >= 0 AND requested_gross_total >= 0 AND requested_net_total + requested_tax_total = requested_gross_total)');
        DB::statement('ALTER TABLE sales_returns ADD CONSTRAINT sales_returns_credited_totals_check CHECK (credited_net_total >= 0 AND credited_tax_total >= 0 AND credited_gross_total >= 0 AND credited_net_total + credited_tax_total = credited_gross_total AND credited_gross_total <= requested_gross_total)');
        DB::statement(<<<'SQL'
ALTER TABLE sales_returns ADD CONSTRAINT sales_returns_lifecycle_timestamps_check CHECK (
    (status = 'draft' AND authorized_at IS NULL AND received_at IS NULL AND completed_at IS NULL AND cancelled_at IS NULL)
    OR (status = 'authorized' AND authorized_at IS NOT NULL AND received_at IS NULL AND completed_at IS NULL AND cancelled_at IS NULL)
    OR (status = 'received' AND authorized_at IS NOT NULL AND received_at IS NOT NULL AND completed_at IS NULL AND cancelled_at IS NULL)
    OR (status = 'completed' AND authorized_at IS NOT NULL AND received_at IS NOT NULL AND completed_at IS NOT NULL AND cancelled_at IS NULL)
    OR (status = 'cancelled' AND received_at IS NULL AND completed_at IS NULL AND cancelled_at IS NOT NULL)
)
SQL);

        Schema::create('sales_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sales_return_id');
            $table->unsignedBigInteger('sales_invoice_id');
            $table->unsignedBigInteger('sales_invoice_line_id');
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->string('product_code', 64);
            $table->string('product_name', 200);
            $table->string('reason_code', 64);
            $table->text('condition_notes')->nullable();
            $table->decimal('quantity', 20, 6);
            $table->decimal('accepted_quantity', 20, 6)->default(0);
            $table->decimal('rejected_quantity', 20, 6)->default(0);
            $table->decimal('restock_quantity', 20, 6)->default(0);
            $table->decimal('requested_net', 20, 6);
            $table->decimal('requested_tax', 20, 6);
            $table->decimal('requested_gross', 20, 6);
            $table->decimal('credited_net', 20, 6)->default(0);
            $table->decimal('credited_tax', 20, 6)->default(0);
            $table->decimal('credited_gross', 20, 6)->default(0);
            $table->decimal('unit_cost', 20, 6)->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'sales_return_lines_company_id_id_unique');
            $table->unique(['company_id', 'sales_return_id', 'position'], 'sales_return_lines_position_unique');
            $table->unique(['company_id', 'sales_return_id', 'sales_invoice_line_id'], 'sales_return_lines_source_unique');
            $table->foreign(['company_id', 'sales_return_id'])
                ->references(['company_id', 'id'])->on('sales_returns')->restrictOnDelete();
            $table->foreign(['company_id', 'sales_invoice_id'])
                ->references(['company_id', 'id'])->on('sales_invoices')->restrictOnDelete();
            $table->foreign(['company_id', 'sales_invoice_line_id'], 'sales_return_lines_source_line_fk')
                ->references(['company_id', 'id'])->on('sales_invoice_lines')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'], 'sales_return_lines_location_fk')
                ->references(['company_id', 'warehouse_id', 'id'])->on('warehouse_locations')->restrictOnDelete();
            $table->index(['company_id', 'sales_invoice_line_id'], 'sales_return_lines_source_line_index');
        });

        DB::statement('ALTER TABLE sales_return_lines ADD CONSTRAINT sales_return_lines_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE sales_return_lines ADD CONSTRAINT sales_return_lines_quantity_check CHECK (quantity > 0 AND accepted_quantity >= 0 AND rejected_quantity >= 0 AND restock_quantity >= 0 AND accepted_quantity + rejected_quantity <= quantity AND restock_quantity <= accepted_quantity)');
        DB::statement("ALTER TABLE sales_return_lines ADD CONSTRAINT sales_return_lines_reason_code_check CHECK (reason_code = lower(btrim(reason_code)) AND reason_code ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE sales_return_lines ADD CONSTRAINT sales_return_lines_requested_totals_check CHECK (requested_net >= 0 AND requested_tax >= 0 AND requested_gross >= 0 AND requested_net + requested_tax = requested_gross)');
        DB::statement('ALTER TABLE sales_return_lines ADD CONSTRAINT sales_return_lines_credited_totals_check CHECK (credited_net >= 0 AND credited_tax >= 0 AND credited_gross >= 0 AND credited_net + credited_tax = credited_gross AND credited_gross <= requested_gross)');
        DB::statement('ALTER TABLE sales_return_lines ADD CONSTRAINT sales_return_lines_unit_cost_check CHECK (unit_cost IS NULL OR unit_cost > 0)');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_return_insert()
RETURNS trigger AS $$
DECLARE
    source_invoice sales_invoices%ROWTYPE;
BEGIN
    SELECT * INTO source_invoice
    FROM sales_invoices
    WHERE company_id = NEW.company_id AND id = NEW.sales_invoice_id
    FOR SHARE;

    IF NOT FOUND OR source_invoice.status <> 'finalized' THEN
        RAISE EXCEPTION 'sales return requires finalized source sales invoice' USING ERRCODE = '23514';
    END IF;
    IF NEW.account_id IS DISTINCT FROM source_invoice.account_id
       OR NEW.currency_code IS DISTINCT FROM source_invoice.currency_code THEN
        RAISE EXCEPTION 'sales return account/currency must match source invoice' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_returns_insert_guard
BEFORE INSERT ON sales_returns
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_return_insert();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_return_line_source()
RETURNS trigger AS $$
DECLARE
    parent_invoice_id bigint;
    source_line sales_invoice_lines%ROWTYPE;
    expected_net numeric(20,6);
    expected_tax numeric(20,6);
    expected_gross numeric(20,6);
BEGIN
    SELECT sales_invoice_id INTO parent_invoice_id
    FROM sales_returns
    WHERE company_id = NEW.company_id AND id = NEW.sales_return_id;

    IF parent_invoice_id IS NULL OR NEW.sales_invoice_id IS DISTINCT FROM parent_invoice_id THEN
        RAISE EXCEPTION 'sales return line invoice lineage does not match parent' USING ERRCODE = '23514';
    END IF;

    SELECT * INTO source_line
    FROM sales_invoice_lines
    WHERE company_id = NEW.company_id
      AND sales_invoice_id = NEW.sales_invoice_id
      AND id = NEW.sales_invoice_line_id
    FOR SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'sales return source invoice line not found' USING ERRCODE = '23514';
    END IF;

    IF NEW.product_id IS DISTINCT FROM source_line.product_id
       OR NEW.warehouse_id IS DISTINCT FROM source_line.warehouse_id
       OR NEW.location_id IS DISTINCT FROM source_line.location_id
       OR NEW.product_code IS DISTINCT FROM source_line.product_code
       OR NEW.product_name IS DISTINCT FROM source_line.product_name
       OR NEW.quantity > source_line.quantity THEN
        RAISE EXCEPTION 'sales return physical source snapshot mismatch' USING ERRCODE = '23514';
    END IF;

    expected_net := round(source_line.net_total * NEW.quantity / source_line.quantity, 6);
    expected_tax := round(source_line.tax_total * NEW.quantity / source_line.quantity, 6);
    expected_gross := expected_net + expected_tax;
    IF NEW.requested_net IS DISTINCT FROM expected_net
       OR NEW.requested_tax IS DISTINCT FROM expected_tax
       OR NEW.requested_gross IS DISTINCT FROM expected_gross THEN
        RAISE EXCEPTION 'sales return requested commercial snapshot mismatch' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_return_lines_source_guard
BEFORE INSERT OR UPDATE ON sales_return_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_return_line_source();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_return_line_lifecycle()
RETURNS trigger AS $$
DECLARE
    parent_status text;
BEGIN
    IF TG_OP = 'INSERT' THEN
        SELECT status INTO parent_status FROM sales_returns WHERE company_id = NEW.company_id AND id = NEW.sales_return_id;
        IF parent_status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'sales return lines can only be inserted into draft return' USING ERRCODE = '55000';
        END IF;
        RETURN NEW;
    END IF;

    SELECT status INTO parent_status FROM sales_returns WHERE company_id = OLD.company_id AND id = OLD.sales_return_id;
    IF TG_OP = 'DELETE' THEN
        IF parent_status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'authorized sales return lines are immutable' USING ERRCODE = '55000';
        END IF;
        RETURN OLD;
    END IF;

    IF parent_status = 'draft' THEN
        RETURN NEW;
    END IF;
    IF parent_status = 'authorized' THEN
        IF (to_jsonb(NEW) - 'accepted_quantity' - 'rejected_quantity' - 'restock_quantity' - 'condition_notes' - 'credited_net' - 'credited_tax' - 'credited_gross' - 'unit_cost' - 'updated_at')
           IS DISTINCT FROM
           (to_jsonb(OLD) - 'accepted_quantity' - 'rejected_quantity' - 'restock_quantity' - 'condition_notes' - 'credited_net' - 'credited_tax' - 'credited_gross' - 'unit_cost' - 'updated_at') THEN
            RAISE EXCEPTION 'authorized sales return inspection may only change inspection fields' USING ERRCODE = '55000';
        END IF;
        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'received/completed/cancelled sales return lines are immutable' USING ERRCODE = '55000';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_return_lines_lifecycle_guard
BEFORE INSERT OR UPDATE OR DELETE ON sales_return_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_return_line_lifecycle();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_return_lifecycle()
RETURNS trigger AS $$
DECLARE
    line_row record;
    committed_quantity numeric(20,6);
    source_quantity numeric(20,6);
    requested_net numeric(20,6);
    requested_tax numeric(20,6);
    requested_gross numeric(20,6);
    credited_net numeric(20,6);
    credited_tax numeric(20,6);
    credited_gross numeric(20,6);
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'sales returns cannot be deleted' USING ERRCODE = '55000';
    END IF;
    IF OLD.status IN ('completed', 'cancelled') THEN
        RAISE EXCEPTION 'completed/cancelled sales return is immutable' USING ERRCODE = '55000';
    END IF;
    IF NEW.company_id IS DISTINCT FROM OLD.company_id
       OR NEW.sales_invoice_id IS DISTINCT FROM OLD.sales_invoice_id
       OR NEW.account_id IS DISTINCT FROM OLD.account_id
       OR NEW.number IS DISTINCT FROM OLD.number
       OR NEW.series_code IS DISTINCT FROM OLD.series_code
       OR NEW.sequence_value IS DISTINCT FROM OLD.sequence_value
       OR NEW.currency_code IS DISTINCT FROM OLD.currency_code THEN
        RAISE EXCEPTION 'sales return document identity is immutable' USING ERRCODE = '55000';
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'draft' THEN
        RETURN NEW;
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'authorized' THEN
        IF NEW.authorized_at IS NULL THEN
            RAISE EXCEPTION 'sales return authorization timestamp is required' USING ERRCODE = '23514';
        END IF;
        IF (to_jsonb(NEW) - 'status' - 'authorized_at' - 'updated_at') IS DISTINCT FROM
           (to_jsonb(OLD) - 'status' - 'authorized_at' - 'updated_at') THEN
            RAISE EXCEPTION 'sales return authorization may only change lifecycle fields' USING ERRCODE = '23514';
        END IF;

        SELECT COALESCE(sum(requested_net),0), COALESCE(sum(requested_tax),0), COALESCE(sum(requested_gross),0)
          INTO requested_net, requested_tax, requested_gross
        FROM sales_return_lines WHERE company_id = OLD.company_id AND sales_return_id = OLD.id;
        IF requested_gross <= 0
           OR OLD.requested_net_total IS DISTINCT FROM requested_net
           OR OLD.requested_tax_total IS DISTINCT FROM requested_tax
           OR OLD.requested_gross_total IS DISTINCT FROM requested_gross THEN
            RAISE EXCEPTION 'sales return requested header totals must reconcile to lines' USING ERRCODE = '23514';
        END IF;

        FOR line_row IN
            SELECT id, sales_invoice_line_id, quantity
            FROM sales_return_lines
            WHERE company_id = OLD.company_id AND sales_return_id = OLD.id
            ORDER BY id
        LOOP
            SELECT quantity INTO source_quantity
            FROM sales_invoice_lines
            WHERE company_id = OLD.company_id AND id = line_row.sales_invoice_line_id
            FOR UPDATE;

            SELECT COALESCE(sum(other_line.quantity), 0)
            INTO committed_quantity
            FROM sales_return_lines AS other_line
            INNER JOIN sales_returns AS other_return
              ON other_return.company_id = other_line.company_id
             AND other_return.id = other_line.sales_return_id
            WHERE other_line.company_id = OLD.company_id
              AND other_line.sales_invoice_line_id = line_row.sales_invoice_line_id
              AND other_return.id <> OLD.id
              AND other_return.status IN ('authorized', 'received', 'completed');

            IF source_quantity IS NULL OR committed_quantity + line_row.quantity > source_quantity THEN
                RAISE EXCEPTION 'sales return quantity exceeds remaining invoice line return capacity' USING ERRCODE = '23514';
            END IF;
        END LOOP;
        RETURN NEW;
    END IF;

    IF OLD.status = 'authorized' AND NEW.status = 'received' THEN
        IF NEW.received_at IS NULL THEN
            RAISE EXCEPTION 'sales return receipt timestamp is required' USING ERRCODE = '23514';
        END IF;
        SELECT COALESCE(sum(credited_net),0), COALESCE(sum(credited_tax),0), COALESCE(sum(credited_gross),0)
          INTO credited_net, credited_tax, credited_gross
        FROM sales_return_lines
        WHERE company_id = OLD.company_id AND sales_return_id = OLD.id;

        IF EXISTS (
            SELECT 1 FROM sales_return_lines
            WHERE company_id = OLD.company_id AND sales_return_id = OLD.id
              AND (accepted_quantity + rejected_quantity <> quantity
                   OR restock_quantity > accepted_quantity
                   OR (restock_quantity > 0 AND unit_cost IS NULL))
        ) THEN
            RAISE EXCEPTION 'sales return receipt requires complete accepted/rejected inspection and restock cost' USING ERRCODE = '23514';
        END IF;
        IF NEW.credited_net_total IS DISTINCT FROM credited_net
           OR NEW.credited_tax_total IS DISTINCT FROM credited_tax
           OR NEW.credited_gross_total IS DISTINCT FROM credited_gross THEN
            RAISE EXCEPTION 'sales return credited header totals must reconcile to lines' USING ERRCODE = '23514';
        END IF;
        IF (to_jsonb(NEW) - 'status' - 'received_at' - 'credited_net_total' - 'credited_tax_total' - 'credited_gross_total' - 'updated_at') IS DISTINCT FROM
           (to_jsonb(OLD) - 'status' - 'received_at' - 'credited_net_total' - 'credited_tax_total' - 'credited_gross_total' - 'updated_at') THEN
            RAISE EXCEPTION 'sales return receipt may only change receipt and credited total fields' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF OLD.status = 'received' AND NEW.status = 'completed' THEN
        IF NEW.completed_at IS NULL THEN
            RAISE EXCEPTION 'sales return completion timestamp is required' USING ERRCODE = '23514';
        END IF;
        IF (to_jsonb(NEW) - 'status' - 'completed_at' - 'updated_at') IS DISTINCT FROM
           (to_jsonb(OLD) - 'status' - 'completed_at' - 'updated_at') THEN
            RAISE EXCEPTION 'sales return completion may only change lifecycle fields' USING ERRCODE = '23514';
        END IF;

        IF OLD.credited_gross_total > 0 AND NOT EXISTS (
            SELECT 1 FROM account_transactions AS transaction
            WHERE transaction.company_id = OLD.company_id
              AND transaction.account_id = OLD.account_id
              AND transaction.posting_date = OLD.return_date
              AND transaction.currency_code = OLD.currency_code
              AND transaction.signed_amount = -OLD.credited_gross_total
              AND transaction.source_type = 'sales_return'
              AND transaction.source_id = OLD.id::text
              AND transaction.effect_type = 'account.sales_return'
              AND transaction.reversal_of_transaction_id IS NULL
        ) THEN
            RAISE EXCEPTION 'sales return completion requires exact customer account credit' USING ERRCODE = '23514';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM sales_return_lines AS line
            WHERE line.company_id = OLD.company_id
              AND line.sales_return_id = OLD.id
              AND line.restock_quantity > 0
              AND NOT EXISTS (
                  SELECT 1 FROM stock_movements AS movement
                  WHERE movement.company_id = line.company_id
                    AND movement.source_type = 'sales_return_line'
                    AND movement.source_id = line.id::text
                    AND movement.effect_type = 'stock.in'
                    AND movement.movement_type = 'sales_return_in'
                    AND movement.product_id = line.product_id
                    AND movement.warehouse_id = line.warehouse_id
                    AND movement.location_id = line.location_id
                    AND movement.quantity_delta = line.restock_quantity
                    AND movement.unit_cost = line.unit_cost
              )
        ) THEN
            RAISE EXCEPTION 'sales return completion requires exact restock movements' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF OLD.status IN ('draft', 'authorized') AND NEW.status = 'cancelled' THEN
        IF NEW.cancelled_at IS NULL OR NEW.received_at IS NOT NULL OR NEW.completed_at IS NOT NULL THEN
            RAISE EXCEPTION 'sales return cancellation lifecycle timestamps are invalid' USING ERRCODE = '23514';
        END IF;
        IF (to_jsonb(NEW) - 'status' - 'cancelled_at' - 'updated_at') IS DISTINCT FROM
           (to_jsonb(OLD) - 'status' - 'cancelled_at' - 'updated_at') THEN
            RAISE EXCEPTION 'sales return cancellation may only change lifecycle fields' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'invalid sales return lifecycle transition' USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER sales_returns_lifecycle_guard
BEFORE UPDATE OR DELETE ON sales_returns
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_return_lifecycle();
SQL);

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_direction_check');
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_type_check
CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'dispatch_out', 'invoice_out', 'goods_receipt_in', 'purchase_return_out', 'sales_return_in'))
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_direction_check
CHECK (
    (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in', 'goods_receipt_in', 'sales_return_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
    OR
    (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out', 'purchase_return_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_sales_return_source_check
CHECK (
    movement_type <> 'sales_return_in'
    OR (
        source_type = 'sales_return_line'
        AND effect_type = 'stock.in'
        AND source_id ~ '^[1-9][0-9]*$'
        AND reversal_of_movement_id IS NULL
    )
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_return_stock_in()
RETURNS trigger AS $$
DECLARE
    source_line sales_return_lines%ROWTYPE;
    parent_status text;
BEGIN
    IF NEW.movement_type <> 'sales_return_in' THEN
        RETURN NEW;
    END IF;

    SELECT * INTO source_line
    FROM sales_return_lines
    WHERE company_id = NEW.company_id AND id = CAST(NEW.source_id AS bigint)
    FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'sales_return_in source line not found' USING ERRCODE = '23514';
    END IF;

    SELECT status INTO parent_status
    FROM sales_returns
    WHERE company_id = source_line.company_id AND id = source_line.sales_return_id
    FOR SHARE;

    IF parent_status IS DISTINCT FROM 'received'
       OR source_line.restock_quantity <= 0
       OR source_line.unit_cost IS NULL
       OR NEW.product_id IS DISTINCT FROM source_line.product_id
       OR NEW.warehouse_id IS DISTINCT FROM source_line.warehouse_id
       OR NEW.location_id IS DISTINCT FROM source_line.location_id
       OR NEW.quantity_delta IS DISTINCT FROM source_line.restock_quantity
       OR NEW.unit_cost IS DISTINCT FROM source_line.unit_cost THEN
        RAISE EXCEPTION 'sales_return_in must exactly match received RMA restock decision' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER stock_movements_sales_return_in_guard
BEFORE INSERT ON stock_movements
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_return_stock_in();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_return_account_credit()
RETURNS trigger AS $$
DECLARE
    source_return sales_returns%ROWTYPE;
BEGIN
    IF NEW.source_type <> 'sales_return' AND NEW.effect_type <> 'account.sales_return' THEN
        RETURN NEW;
    END IF;
    IF NEW.source_type <> 'sales_return'
       OR NEW.effect_type <> 'account.sales_return'
       OR NEW.source_id !~ '^[1-9][0-9]*$'
       OR NEW.reversal_of_transaction_id IS NOT NULL THEN
        RAISE EXCEPTION 'sales return account credit source identity is invalid' USING ERRCODE = '23514';
    END IF;

    SELECT * INTO source_return
    FROM sales_returns
    WHERE company_id = NEW.company_id AND id = CAST(NEW.source_id AS bigint)
    FOR SHARE;
    IF NOT FOUND
       OR source_return.status <> 'received'
       OR source_return.credited_gross_total <= 0
       OR NEW.account_id IS DISTINCT FROM source_return.account_id
       OR NEW.posting_date IS DISTINCT FROM source_return.return_date
       OR NEW.currency_code IS DISTINCT FROM source_return.currency_code
       OR NEW.signed_amount IS DISTINCT FROM -source_return.credited_gross_total THEN
        RAISE EXCEPTION 'sales return customer account credit must exactly match received RMA' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER account_transactions_sales_return_guard
BEFORE INSERT ON account_transactions
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_return_account_credit();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS account_transactions_sales_return_guard ON account_transactions');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_return_account_credit()');
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_sales_return_in_guard ON stock_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_return_stock_in()');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_sales_return_source_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_direction_check');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'dispatch_out', 'invoice_out', 'goods_receipt_in', 'purchase_return_out'))");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_direction_check CHECK ((movement_type IN ('opening_in', 'adjustment_in', 'transfer_in', 'goods_receipt_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0) OR (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out', 'purchase_return_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0))");

        DB::statement('DROP TRIGGER IF EXISTS sales_returns_lifecycle_guard ON sales_returns');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_return_lifecycle()');
        DB::statement('DROP TRIGGER IF EXISTS sales_return_lines_lifecycle_guard ON sales_return_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_return_line_lifecycle()');
        DB::statement('DROP TRIGGER IF EXISTS sales_return_lines_source_guard ON sales_return_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_return_line_source()');
        DB::statement('DROP TRIGGER IF EXISTS sales_returns_insert_guard ON sales_returns');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_return_insert()');

        Schema::dropIfExists('sales_return_lines');
        Schema::dropIfExists('sales_returns');
        Schema::table('sales_invoice_lines', function (Blueprint $table): void {
            $table->dropUnique('sales_invoice_lines_company_id_id_m12_unique');
        });
        DB::table('permissions')->whereIn('key', ['sales_returns.view', 'sales_returns.manage'])->delete();
    }
};
