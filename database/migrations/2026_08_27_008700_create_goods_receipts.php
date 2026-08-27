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
                'key' => 'goods_receipts.view',
                'name' => 'Mal kabul görüntüleme',
                'description' => 'Aktif şirkette mal kabul belgelerini ve custody dağılımını görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'goods_receipts.manage',
                'name' => 'Mal kabul yönetimi',
                'description' => 'Mal kabul oluşturma, düzenleme ve fiziksel stok girişini kesinleştirme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('account_id');
            $table->string('number', 64);
            $table->string('series_code', 64);
            $table->unsignedBigInteger('sequence_value');
            $table->string('status', 16)->default('draft');
            $table->date('receipt_date');
            $table->text('note')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'goods_receipts_company_id_id_unique');
            $table->unique(['company_id', 'number'], 'goods_receipts_company_number_unique');
            $table->unique(['company_id', 'series_code', 'sequence_value'], 'goods_receipts_company_series_sequence_unique');
            $table->foreign(['company_id', 'purchase_order_id'])
                ->references(['company_id', 'id'])->on('purchase_orders')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->index(['company_id', 'status', 'receipt_date'], 'goods_receipts_company_status_date_index');
            $table->index(['company_id', 'purchase_order_id'], 'goods_receipts_company_purchase_order_index');
        });

        DB::statement("ALTER TABLE goods_receipts ADD CONSTRAINT goods_receipts_status_check CHECK (status IN ('draft', 'finalized'))");
        DB::statement("ALTER TABLE goods_receipts ADD CONSTRAINT goods_receipts_series_code_canonical_check CHECK (series_code = lower(btrim(series_code)) AND series_code ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE goods_receipts ADD CONSTRAINT goods_receipts_lifecycle_timestamp_check CHECK ((status = 'draft' AND finalized_at IS NULL) OR (status = 'finalized' AND finalized_at IS NOT NULL))");

        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('purchase_order_line_id');
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->string('product_code', 64);
            $table->string('product_name', 200);
            $table->decimal('received_quantity', 20, 6);
            $table->decimal('accepted_quantity', 20, 6);
            $table->decimal('pending_quantity', 20, 6);
            $table->decimal('rejected_quantity', 20, 6);
            $table->decimal('provisional_unit_cost', 20, 6);
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'goods_receipt_id', 'id'], 'goods_receipt_lines_company_receipt_id_unique');
            $table->unique(['company_id', 'goods_receipt_id', 'position'], 'goods_receipt_lines_position_unique');
            $table->foreign(['company_id', 'goods_receipt_id'])
                ->references(['company_id', 'id'])->on('goods_receipts')->restrictOnDelete();
            $table->foreign(
                ['company_id', 'purchase_order_id', 'purchase_order_line_id'],
                'goods_receipt_lines_purchase_order_line_fk',
            )->references(['company_id', 'purchase_order_id', 'id'])->on('purchase_order_lines')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])->on('warehouse_locations')->restrictOnDelete();
            $table->index(['company_id', 'purchase_order_line_id'], 'goods_receipt_lines_company_purchase_line_index');
        });

        DB::statement('ALTER TABLE goods_receipt_lines ADD CONSTRAINT goods_receipt_lines_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE goods_receipt_lines ADD CONSTRAINT goods_receipt_lines_received_positive_check CHECK (received_quantity > 0)');
        DB::statement('ALTER TABLE goods_receipt_lines ADD CONSTRAINT goods_receipt_lines_custody_nonnegative_check CHECK (accepted_quantity >= 0 AND pending_quantity >= 0 AND rejected_quantity >= 0)');
        DB::statement('ALTER TABLE goods_receipt_lines ADD CONSTRAINT goods_receipt_lines_custody_reconciliation_check CHECK (accepted_quantity + pending_quantity + rejected_quantity = received_quantity)');
        DB::statement('ALTER TABLE goods_receipt_lines ADD CONSTRAINT goods_receipt_lines_provisional_cost_check CHECK (provisional_unit_cost >= 0 AND (accepted_quantity = 0 OR provisional_unit_cost > 0))');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_goods_receipt_source()
RETURNS trigger AS $$
DECLARE
    source_account_id bigint;
BEGIN
    SELECT account_id INTO source_account_id
    FROM purchase_orders
    WHERE company_id = NEW.company_id
      AND id = NEW.purchase_order_id
    FOR SHARE;

    IF source_account_id IS NULL THEN
        RAISE EXCEPTION 'goods receipt source purchase order not found' USING ERRCODE = '23503';
    END IF;

    IF NEW.account_id IS DISTINCT FROM source_account_id THEN
        RAISE EXCEPTION 'goods receipt supplier must match source purchase order supplier' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER goods_receipts_source_guard
BEFORE INSERT OR UPDATE OF company_id, purchase_order_id, account_id ON goods_receipts
FOR EACH ROW EXECUTE FUNCTION mars_guard_goods_receipt_source();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_goods_receipt_line_scope()
RETURNS trigger AS $$
DECLARE
    parent_purchase_order_id bigint;
    parent_status text;
    source_line purchase_order_lines%ROWTYPE;
    location_warehouse_id bigint;
    expected_unit_cost numeric(20, 6);
BEGIN
    SELECT purchase_order_id, status
      INTO parent_purchase_order_id, parent_status
    FROM goods_receipts
    WHERE company_id = NEW.company_id
      AND id = NEW.goods_receipt_id
    FOR SHARE;

    IF parent_purchase_order_id IS NULL OR parent_status IS NULL THEN
        RAISE EXCEPTION 'goods receipt line parent not found' USING ERRCODE = '23503';
    END IF;

    IF parent_status <> 'draft' THEN
        RAISE EXCEPTION 'goods receipt lines require draft parent' USING ERRCODE = '23514';
    END IF;

    IF NEW.purchase_order_id IS DISTINCT FROM parent_purchase_order_id THEN
        RAISE EXCEPTION 'goods receipt line purchase order must match parent receipt' USING ERRCODE = '23514';
    END IF;

    SELECT * INTO source_line
    FROM purchase_order_lines
    WHERE company_id = NEW.company_id
      AND purchase_order_id = NEW.purchase_order_id
      AND id = NEW.purchase_order_line_id
    FOR SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'goods receipt source purchase order line not found' USING ERRCODE = '23503';
    END IF;

    expected_unit_cost := CAST(source_line.net_total / source_line.quantity AS numeric(20, 6));

    IF NEW.product_id IS DISTINCT FROM source_line.product_id
       OR NEW.product_code IS DISTINCT FROM source_line.product_code
       OR NEW.product_name IS DISTINCT FROM source_line.product_name
       OR NEW.provisional_unit_cost IS DISTINCT FROM expected_unit_cost THEN
        RAISE EXCEPTION 'goods receipt line identity/cost must match source purchase order line' USING ERRCODE = '23514';
    END IF;

    IF NEW.accepted_quantity > 0 AND expected_unit_cost <= 0 THEN
        RAISE EXCEPTION 'accepted goods receipt quantity requires positive provisional unit cost' USING ERRCODE = '23514';
    END IF;

    SELECT warehouse_id INTO location_warehouse_id
    FROM warehouse_locations
    WHERE company_id = NEW.company_id
      AND warehouse_id = NEW.warehouse_id
      AND id = NEW.location_id
      AND is_active = true
    FOR SHARE;

    IF location_warehouse_id IS NULL OR location_warehouse_id IS DISTINCT FROM NEW.warehouse_id THEN
        RAISE EXCEPTION 'goods receipt location must belong to selected active warehouse' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER goods_receipt_lines_scope_guard
BEFORE INSERT OR UPDATE ON goods_receipt_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_goods_receipt_line_scope();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_goods_receipt_mutation()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'goods receipts cannot be deleted' USING ERRCODE = '55000';
    END IF;

    IF OLD.status = 'finalized' THEN
        RAISE EXCEPTION 'finalized goods receipt is immutable' USING ERRCODE = '55000';
    END IF;

    IF NEW.company_id IS DISTINCT FROM OLD.company_id
       OR NEW.purchase_order_id IS DISTINCT FROM OLD.purchase_order_id
       OR NEW.account_id IS DISTINCT FROM OLD.account_id
       OR NEW.number IS DISTINCT FROM OLD.number
       OR NEW.series_code IS DISTINCT FROM OLD.series_code
       OR NEW.sequence_value IS DISTINCT FROM OLD.sequence_value THEN
        RAISE EXCEPTION 'goods receipt document identity is immutable' USING ERRCODE = '55000';
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'draft' THEN
        IF NEW.finalized_at IS NOT NULL THEN
            RAISE EXCEPTION 'draft goods receipt cannot have finalized_at' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'finalized' THEN
        IF NEW.finalized_at IS NULL
           OR NEW.receipt_date IS DISTINCT FROM OLD.receipt_date
           OR NEW.note IS DISTINCT FROM OLD.note THEN
            RAISE EXCEPTION 'goods receipt finalization may only change lifecycle fields' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'invalid goods receipt lifecycle transition' USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER goods_receipts_mutation_guard
BEFORE UPDATE OR DELETE ON goods_receipts
FOR EACH ROW EXECUTE FUNCTION mars_guard_goods_receipt_mutation();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_goods_receipt_line_mutation()
RETURNS trigger AS $$
DECLARE
    parent_status text;
BEGIN
    IF TG_OP = 'DELETE' THEN
        SELECT status INTO parent_status
        FROM goods_receipts
        WHERE company_id = OLD.company_id AND id = OLD.goods_receipt_id;

        IF parent_status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'finalized goods receipt lines are immutable' USING ERRCODE = '55000';
        END IF;

        RETURN OLD;
    END IF;

    SELECT status INTO parent_status
    FROM goods_receipts
    WHERE company_id = NEW.company_id AND id = NEW.goods_receipt_id;

    IF parent_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'goods receipt lines require draft parent' USING ERRCODE = '55000';
    END IF;

    IF TG_OP = 'UPDATE' AND (
        NEW.company_id IS DISTINCT FROM OLD.company_id
        OR NEW.goods_receipt_id IS DISTINCT FROM OLD.goods_receipt_id
        OR NEW.purchase_order_id IS DISTINCT FROM OLD.purchase_order_id
        OR NEW.purchase_order_line_id IS DISTINCT FROM OLD.purchase_order_line_id
    ) THEN
        RAISE EXCEPTION 'goods receipt line source identity is immutable' USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER goods_receipt_lines_mutation_guard
BEFORE INSERT OR UPDATE OR DELETE ON goods_receipt_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_goods_receipt_line_mutation();
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
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_goods_receipt_source_check
CHECK (
    movement_type <> 'goods_receipt_in'
    OR (
        source_type = 'goods_receipt_line'
        AND effect_type = 'stock.in'
        AND source_id ~ '^[1-9][0-9]*$'
        AND reversal_of_movement_id IS NULL
    )
)
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_goods_receipt_stock_in()
RETURNS trigger AS $$
DECLARE
    source_line goods_receipt_lines%ROWTYPE;
    parent_status text;
BEGIN
    IF NEW.movement_type <> 'goods_receipt_in' THEN
        RETURN NEW;
    END IF;

    SELECT * INTO source_line
    FROM goods_receipt_lines
    WHERE company_id = NEW.company_id
      AND id = CAST(NEW.source_id AS bigint)
    FOR SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'goods_receipt_in source line not found' USING ERRCODE = '23514';
    END IF;

    SELECT status INTO parent_status
    FROM goods_receipts
    WHERE company_id = source_line.company_id
      AND id = source_line.goods_receipt_id
    FOR SHARE;

    IF parent_status IS DISTINCT FROM 'draft'
       OR source_line.accepted_quantity <= 0
       OR NEW.product_id IS DISTINCT FROM source_line.product_id
       OR NEW.warehouse_id IS DISTINCT FROM source_line.warehouse_id
       OR NEW.location_id IS DISTINCT FROM source_line.location_id
       OR NEW.quantity_delta IS DISTINCT FROM source_line.accepted_quantity
       OR NEW.unit_cost IS DISTINCT FROM source_line.provisional_unit_cost THEN
        RAISE EXCEPTION 'goods_receipt_in must exactly match accepted quantity and custody scope of a draft goods receipt line' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER stock_movements_goods_receipt_in_guard
BEFORE INSERT ON stock_movements
FOR EACH ROW EXECUTE FUNCTION mars_guard_goods_receipt_stock_in();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_goods_receipt_finalization_commit()
RETURNS trigger AS $$
DECLARE
    line_record goods_receipt_lines%ROWTYPE;
BEGIN
    IF OLD.status <> 'draft' OR NEW.status <> 'finalized' THEN
        RETURN NEW;
    END IF;

    FOR line_record IN
        SELECT * FROM goods_receipt_lines
        WHERE company_id = NEW.company_id
          AND goods_receipt_id = NEW.id
    LOOP
        IF line_record.accepted_quantity > 0 THEN
            IF NOT EXISTS (
                SELECT 1
                FROM stock_movements
                WHERE company_id = NEW.company_id
                  AND source_type = 'goods_receipt_line'
                  AND source_id = line_record.id::text
                  AND effect_type = 'stock.in'
                  AND movement_type = 'goods_receipt_in'
                  AND product_id = line_record.product_id
                  AND warehouse_id = line_record.warehouse_id
                  AND location_id = line_record.location_id
                  AND quantity_delta = line_record.accepted_quantity
                  AND unit_cost = line_record.provisional_unit_cost
                  AND reversal_of_movement_id IS NULL
            ) THEN
                RAISE EXCEPTION 'finalized goods receipt requires exact accepted stock in effect' USING ERRCODE = '23514';
            END IF;

            IF NOT EXISTS (
                SELECT 1
                FROM purchase_order_line_progress_effects
                WHERE company_id = NEW.company_id
                  AND purchase_order_id = line_record.purchase_order_id
                  AND purchase_order_line_id = line_record.purchase_order_line_id
                  AND progress_type = 'received'
                  AND quantity_delta = line_record.accepted_quantity
                  AND source_type = 'goods_receipt_line'
                  AND source_id = line_record.id::text
                  AND effect_type = 'progress.receive'
            ) THEN
                RAISE EXCEPTION 'finalized goods receipt requires exact purchase order receive progress' USING ERRCODE = '23514';
            END IF;
        ELSE
            IF EXISTS (
                SELECT 1 FROM stock_movements
                WHERE company_id = NEW.company_id
                  AND source_type = 'goods_receipt_line'
                  AND source_id = line_record.id::text
                  AND effect_type = 'stock.in'
            ) OR EXISTS (
                SELECT 1 FROM purchase_order_line_progress_effects
                WHERE company_id = NEW.company_id
                  AND source_type = 'goods_receipt_line'
                  AND source_id = line_record.id::text
                  AND effect_type = 'progress.receive'
            ) THEN
                RAISE EXCEPTION 'non-accepted goods receipt custody cannot create stock/progress effect' USING ERRCODE = '23514';
            END IF;
        END IF;
    END LOOP;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER goods_receipts_finalization_commit_guard
AFTER UPDATE ON goods_receipts
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_goods_receipt_finalization_commit();
SQL);

        DB::unprepared(<<<'SQL'
CREATE VIEW goods_receipt_line_custody AS
SELECT
    company_id,
    goods_receipt_id,
    id AS goods_receipt_line_id,
    purchase_order_id,
    purchase_order_line_id,
    product_id,
    warehouse_id,
    location_id,
    received_quantity,
    accepted_quantity,
    pending_quantity,
    rejected_quantity
FROM goods_receipt_lines
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS goods_receipt_line_custody');
        DB::statement('DROP TRIGGER IF EXISTS goods_receipts_finalization_commit_guard ON goods_receipts');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_goods_receipt_finalization_commit()');
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_goods_receipt_in_guard ON stock_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_goods_receipt_stock_in()');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_goods_receipt_source_check');

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_direction_check');
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_type_check
CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'dispatch_out', 'invoice_out'))
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_direction_check
CHECK (
    (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
    OR
    (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
)
SQL);

        DB::statement('DROP TRIGGER IF EXISTS goods_receipt_lines_mutation_guard ON goods_receipt_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_goods_receipt_line_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS goods_receipts_mutation_guard ON goods_receipts');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_goods_receipt_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS goods_receipt_lines_scope_guard ON goods_receipt_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_goods_receipt_line_scope()');
        DB::statement('DROP TRIGGER IF EXISTS goods_receipts_source_guard ON goods_receipts');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_goods_receipt_source()');

        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');

        foreach (['goods_receipts.view', 'goods_receipts.manage'] as $key) {
            $permissionId = DB::table('permissions')->where('key', $key)->value('id');
            if (is_int($permissionId)) {
                DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            }
        }
        DB::table('permissions')->whereIn('key', ['goods_receipts.view', 'goods_receipts.manage'])->delete();
    }
};
