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
                'key' => 'purchase_orders.view',
                'name' => 'Satınalma siparişi görüntüleme',
                'description' => 'Aktif şirkette satınalma siparişlerini listeleme ve detay görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'purchase_orders.manage',
                'name' => 'Satınalma siparişi yönetimi',
                'description' => 'Aktif şirkette satınalma siparişi oluşturma ve progress başlamadan düzenleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->string('number', 64);
            $table->string('series_code', 64);
            $table->unsignedBigInteger('sequence_value');
            $table->string('status', 16)->default('draft');
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

            $table->unique(['company_id', 'id'], 'purchase_orders_company_id_id_unique');
            $table->unique(['company_id', 'number'], 'purchase_orders_company_number_unique');
            $table->unique(['company_id', 'series_code', 'sequence_value'], 'purchase_orders_company_series_sequence_unique');
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['company_id', 'status', 'order_date'], 'purchase_orders_company_status_date_index');
        });

        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status = 'draft')");
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_series_code_canonical_check CHECK (series_code = lower(btrim(series_code)) AND series_code ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_document_discount_rate_check CHECK (document_discount_rate >= 0 AND document_discount_rate <= 100)');
        DB::statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_totals_nonnegative_check CHECK (base_net_total >= 0 AND line_discount_total >= 0 AND document_discount_total >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_total_reconciliation_check CHECK (base_net_total - line_discount_total - document_discount_total = net_total AND net_total + tax_total = gross_total)');

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('purchase_order_id');
            $table->uuid('logical_line_key');
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
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

            $table->unique(['company_id', 'purchase_order_id', 'id'], 'purchase_order_lines_company_order_id_unique');
            $table->unique(['company_id', 'purchase_order_id', 'position'], 'purchase_order_lines_position_unique');
            $table->unique(['company_id', 'purchase_order_id', 'logical_line_key'], 'purchase_order_lines_logical_key_unique');
            $table->foreign(['company_id', 'purchase_order_id'])
                ->references(['company_id', 'id'])->on('purchase_orders')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['company_id', 'location_id'])
                ->references(['company_id', 'id'])->on('warehouse_locations')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_id'])
                ->references(['company_id', 'id'])->on('taxes')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_zero_reason_id'])
                ->references(['company_id', 'id'])->on('tax_zero_reasons')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_price_basis_check CHECK (price_basis IN ('net', 'gross'))");
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_discount_rate_check CHECK (line_discount_rate >= 0 AND line_discount_rate <= 100)');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_tax_rate_check CHECK (tax_rate >= 0 AND tax_rate <= 100)');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_amounts_nonnegative_check CHECK (base_net >= 0 AND line_discount_net >= 0 AND document_discount_net >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_total_reconciliation_check CHECK (base_net - line_discount_net - document_discount_net = net_total AND net_total + tax_total = gross_total)');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_allocation_shape_check CHECK ((warehouse_id IS NULL AND location_id IS NULL) OR (warehouse_id IS NOT NULL AND location_id IS NOT NULL))');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_zero_reason_shape_check CHECK ((tax_rate = 0 AND tax_zero_reason_id IS NOT NULL AND tax_zero_reason_code IS NOT NULL) OR (tax_rate > 0 AND tax_zero_reason_id IS NULL AND tax_zero_reason_code IS NULL))');

        Schema::create('purchase_order_line_progress_effects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('purchase_order_line_id');
            $table->string('progress_type', 16);
            $table->decimal('quantity_delta', 20, 6);
            $table->char('operation_key', 64);
            $table->char('request_fingerprint', 64);
            $table->string('source_type', 100);
            $table->string('source_id', 255);
            $table->string('effect_type', 100);
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'purchase_order_id'], 'purchase_order_progress_effects_order_fk')
                ->references(['company_id', 'id'])->on('purchase_orders')->restrictOnDelete();
            $table->foreign(
                ['company_id', 'purchase_order_id', 'purchase_order_line_id'],
                'purchase_order_progress_effects_line_fk',
            )->references(['company_id', 'purchase_order_id', 'id'])->on('purchase_order_lines')->restrictOnDelete();
            $table->unique('operation_key', 'purchase_order_progress_effects_operation_unique');
            $table->unique(
                ['company_id', 'source_type', 'source_id', 'effect_type'],
                'purchase_order_progress_effects_source_unique',
            );
            $table->index(
                ['company_id', 'purchase_order_id', 'purchase_order_line_id', 'progress_type'],
                'purchase_order_progress_effects_line_type_index',
            );
        });

        DB::statement("ALTER TABLE purchase_order_line_progress_effects ADD CONSTRAINT purchase_order_progress_effects_type_check CHECK (progress_type IN ('received', 'invoiced', 'cancelled'))");
        DB::statement('ALTER TABLE purchase_order_line_progress_effects ADD CONSTRAINT purchase_order_progress_effects_quantity_check CHECK (quantity_delta <> 0)');
        DB::statement("ALTER TABLE purchase_order_line_progress_effects ADD CONSTRAINT purchase_order_progress_effects_operation_key_check CHECK (operation_key ~ '^[a-f0-9]{64}$')");
        DB::statement("ALTER TABLE purchase_order_line_progress_effects ADD CONSTRAINT purchase_order_progress_effects_request_fingerprint_check CHECK (request_fingerprint ~ '^[a-f0-9]{64}$')");
        DB::statement("ALTER TABLE purchase_order_line_progress_effects ADD CONSTRAINT purchase_order_progress_effects_source_type_check CHECK (source_type ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE purchase_order_line_progress_effects ADD CONSTRAINT purchase_order_progress_effects_effect_type_check CHECK (effect_type ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE purchase_order_line_progress_effects ADD CONSTRAINT purchase_order_progress_effects_source_id_check CHECK (source_id <> '' AND source_id = btrim(source_id))");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_supplier()
RETURNS trigger AS $$
DECLARE
    account_type text;
    account_status text;
BEGIN
    SELECT type, status INTO account_type, account_status
    FROM accounts
    WHERE company_id = NEW.company_id AND id = NEW.account_id
    FOR SHARE;

    IF account_type IS NULL OR account_status IS NULL THEN
        RAISE EXCEPTION 'purchase order supplier account not found' USING ERRCODE = '23503';
    END IF;

    IF account_status <> 'active' OR account_type NOT IN ('supplier', 'mixed') THEN
        RAISE EXCEPTION 'purchase order requires active supplier or mixed account' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER purchase_orders_supplier_guard
BEFORE INSERT OR UPDATE OF company_id, account_id ON purchase_orders
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_order_supplier();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_line_scope()
RETURNS trigger AS $$
DECLARE
    parent_status text;
    location_warehouse bigint;
BEGIN
    SELECT status INTO parent_status
    FROM purchase_orders
    WHERE company_id = NEW.company_id AND id = NEW.purchase_order_id;

    IF parent_status IS NULL THEN
        RAISE EXCEPTION 'purchase order line parent not found' USING ERRCODE = '23503';
    END IF;

    IF parent_status <> 'draft' THEN
        RAISE EXCEPTION 'purchase order lines require draft parent' USING ERRCODE = '23514';
    END IF;

    IF NEW.location_id IS NOT NULL THEN
        SELECT warehouse_id INTO location_warehouse
        FROM warehouse_locations
        WHERE company_id = NEW.company_id AND id = NEW.location_id;

        IF location_warehouse IS NULL OR location_warehouse IS DISTINCT FROM NEW.warehouse_id THEN
            RAISE EXCEPTION 'purchase order line location must belong to selected warehouse' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER purchase_order_lines_scope_guard
BEFORE INSERT OR UPDATE ON purchase_order_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_order_line_scope();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_progress_effect()
RETURNS trigger AS $$
DECLARE
    ordered numeric(20, 6);
    received numeric(20, 6);
    invoiced numeric(20, 6);
    cancelled numeric(20, 6);
BEGIN
    IF TG_OP <> 'INSERT' THEN
        RAISE EXCEPTION 'purchase order progress effects are append-only' USING ERRCODE = '55000';
    END IF;

    SELECT quantity INTO ordered
    FROM purchase_order_lines
    WHERE company_id = NEW.company_id
      AND purchase_order_id = NEW.purchase_order_id
      AND id = NEW.purchase_order_line_id
    FOR UPDATE;

    IF ordered IS NULL THEN
        RAISE EXCEPTION 'purchase order progress line not found' USING ERRCODE = '23503';
    END IF;

    SELECT
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'received'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'invoiced'), 0),
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'cancelled'), 0)
    INTO received, invoiced, cancelled
    FROM purchase_order_line_progress_effects
    WHERE company_id = NEW.company_id
      AND purchase_order_id = NEW.purchase_order_id
      AND purchase_order_line_id = NEW.purchase_order_line_id;

    IF NEW.progress_type = 'received' THEN
        received := received + NEW.quantity_delta;
    ELSIF NEW.progress_type = 'invoiced' THEN
        invoiced := invoiced + NEW.quantity_delta;
    ELSE
        cancelled := cancelled + NEW.quantity_delta;
    END IF;

    IF received < 0 OR invoiced < 0 OR cancelled < 0 THEN
        RAISE EXCEPTION 'purchase order net progress cannot be negative' USING ERRCODE = '23514';
    END IF;

    IF received + cancelled > ordered THEN
        RAISE EXCEPTION 'purchase order receipt progress exceeds ordered quantity' USING ERRCODE = '23514';
    END IF;

    IF invoiced + cancelled > ordered THEN
        RAISE EXCEPTION 'purchase order invoice progress exceeds ordered quantity' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER purchase_order_progress_effects_guard
BEFORE INSERT OR UPDATE OR DELETE ON purchase_order_line_progress_effects
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_order_progress_effect();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_mutation()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'purchase orders cannot be deleted' USING ERRCODE = '55000';
    END IF;

    IF OLD.status <> 'draft' OR NEW.status <> 'draft' THEN
        RAISE EXCEPTION 'only draft purchase orders are mutable' USING ERRCODE = '55000';
    END IF;

    IF NEW.company_id IS DISTINCT FROM OLD.company_id
        OR NEW.number IS DISTINCT FROM OLD.number
        OR NEW.series_code IS DISTINCT FROM OLD.series_code
        OR NEW.sequence_value IS DISTINCT FROM OLD.sequence_value THEN
        RAISE EXCEPTION 'purchase order document identity is immutable' USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER purchase_orders_mutation_guard
BEFORE UPDATE OR DELETE ON purchase_orders
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_order_mutation();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_line_after_progress()
RETURNS trigger AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM purchase_order_line_progress_effects
        WHERE company_id = OLD.company_id
          AND purchase_order_id = OLD.purchase_order_id
          AND purchase_order_line_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'purchase order line is immutable after progress starts' USING ERRCODE = '55000';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    IF NEW.company_id IS DISTINCT FROM OLD.company_id
        OR NEW.purchase_order_id IS DISTINCT FROM OLD.purchase_order_id
        OR NEW.logical_line_key IS DISTINCT FROM OLD.logical_line_key THEN
        RAISE EXCEPTION 'purchase order line identity is immutable' USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER purchase_order_lines_progress_guard
BEFORE UPDATE OR DELETE ON purchase_order_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_order_line_after_progress();
SQL);

        DB::unprepared(<<<'SQL'
CREATE VIEW purchase_order_line_progress AS
WITH progress AS (
    SELECT
        company_id,
        purchase_order_id,
        purchase_order_line_id,
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'received'), 0)::numeric(20, 6) AS net_received_quantity,
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'invoiced'), 0)::numeric(20, 6) AS net_invoiced_quantity,
        COALESCE(SUM(quantity_delta) FILTER (WHERE progress_type = 'cancelled'), 0)::numeric(20, 6) AS cancelled_quantity
    FROM purchase_order_line_progress_effects
    GROUP BY company_id, purchase_order_id, purchase_order_line_id
)
SELECT
    line.company_id,
    line.purchase_order_id,
    line.id AS purchase_order_line_id,
    line.quantity::numeric(20, 6) AS ordered_quantity,
    COALESCE(progress.net_received_quantity, 0)::numeric(20, 6) AS net_received_quantity,
    COALESCE(progress.net_invoiced_quantity, 0)::numeric(20, 6) AS net_invoiced_quantity,
    COALESCE(progress.cancelled_quantity, 0)::numeric(20, 6) AS cancelled_quantity,
    (
        line.quantity
        - COALESCE(progress.cancelled_quantity, 0)
        - COALESCE(progress.net_received_quantity, 0)
    )::numeric(20, 6) AS receive_remaining_quantity,
    (
        line.quantity
        - COALESCE(progress.cancelled_quantity, 0)
        - COALESCE(progress.net_invoiced_quantity, 0)
    )::numeric(20, 6) AS invoice_remaining_quantity
FROM purchase_order_lines AS line
LEFT JOIN progress
  ON progress.company_id = line.company_id
 AND progress.purchase_order_id = line.purchase_order_id
 AND progress.purchase_order_line_id = line.id
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS purchase_order_line_progress');
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_lines_progress_guard ON purchase_order_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_order_line_after_progress()');
        DB::statement('DROP TRIGGER IF EXISTS purchase_orders_mutation_guard ON purchase_orders');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_order_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_progress_effects_guard ON purchase_order_line_progress_effects');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_order_progress_effect()');
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_lines_scope_guard ON purchase_order_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_order_line_scope()');
        DB::statement('DROP TRIGGER IF EXISTS purchase_orders_supplier_guard ON purchase_orders');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_purchase_order_supplier()');

        Schema::dropIfExists('purchase_order_line_progress_effects');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');

        foreach (['purchase_orders.view', 'purchase_orders.manage'] as $key) {
            $permissionId = DB::table('permissions')->where('key', $key)->value('id');
            if (is_int($permissionId)) {
                DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            }
        }
        DB::table('permissions')->whereIn('key', ['purchase_orders.view', 'purchase_orders.manage'])->delete();
    }
};
