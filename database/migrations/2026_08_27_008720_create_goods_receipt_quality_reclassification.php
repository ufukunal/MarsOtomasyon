<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_quality_effects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('goods_receipt_line_id');
            $table->string('disposition', 16);
            $table->decimal('quantity', 20, 6);
            $table->string('note', 1000)->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'goods_receipt_id'])
                ->references(['company_id', 'id'])->on('goods_receipts')->restrictOnDelete();
            $table->foreign(
                ['company_id', 'goods_receipt_id', 'goods_receipt_line_id'],
                'goods_receipt_quality_effects_line_fk',
            )->references(['company_id', 'goods_receipt_id', 'id'])->on('goods_receipt_lines')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(
                ['company_id', 'goods_receipt_line_id', 'disposition'],
                'goods_receipt_quality_effects_line_disposition_index',
            );
        });

        DB::statement("ALTER TABLE goods_receipt_quality_effects ADD CONSTRAINT goods_receipt_quality_effects_disposition_check CHECK (disposition IN ('accepted', 'rejected'))");
        DB::statement('ALTER TABLE goods_receipt_quality_effects ADD CONSTRAINT goods_receipt_quality_effects_quantity_positive_check CHECK (quantity > 0)');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_goods_receipt_quality_effect()
RETURNS trigger AS $$
DECLARE
    source_line goods_receipt_lines%ROWTYPE;
    parent_status text;
    already_reclassified numeric(20, 6);
BEGIN
    IF TG_OP <> 'INSERT' THEN
        RAISE EXCEPTION 'goods receipt quality effects are append-only' USING ERRCODE = '55000';
    END IF;

    SELECT * INTO source_line
    FROM goods_receipt_lines
    WHERE company_id = NEW.company_id
      AND goods_receipt_id = NEW.goods_receipt_id
      AND id = NEW.goods_receipt_line_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'goods receipt quality source line not found' USING ERRCODE = '23503';
    END IF;

    SELECT status INTO parent_status
    FROM goods_receipts
    WHERE company_id = NEW.company_id
      AND id = NEW.goods_receipt_id
    FOR SHARE;

    IF parent_status IS DISTINCT FROM 'finalized' THEN
        RAISE EXCEPTION 'quality reclassification requires finalized goods receipt' USING ERRCODE = '23514';
    END IF;

    SELECT COALESCE(SUM(quantity), 0)
      INTO already_reclassified
    FROM goods_receipt_quality_effects
    WHERE company_id = NEW.company_id
      AND goods_receipt_id = NEW.goods_receipt_id
      AND goods_receipt_line_id = NEW.goods_receipt_line_id;

    IF already_reclassified + NEW.quantity > source_line.pending_quantity THEN
        RAISE EXCEPTION 'quality reclassification exceeds pending custody' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER goods_receipt_quality_effects_guard
BEFORE INSERT OR UPDATE OR DELETE ON goods_receipt_quality_effects
FOR EACH ROW EXECUTE FUNCTION mars_guard_goods_receipt_quality_effect();
SQL);

        DB::unprepared(<<<'SQL'
CREATE VIEW goods_receipt_line_quality AS
WITH quality AS (
    SELECT
        company_id,
        goods_receipt_id,
        goods_receipt_line_id,
        COALESCE(SUM(quantity) FILTER (WHERE disposition = 'accepted'), 0)::numeric(20, 6) AS accepted_delta,
        COALESCE(SUM(quantity) FILTER (WHERE disposition = 'rejected'), 0)::numeric(20, 6) AS rejected_delta,
        COALESCE(SUM(quantity), 0)::numeric(20, 6) AS reclassified_quantity
    FROM goods_receipt_quality_effects
    GROUP BY company_id, goods_receipt_id, goods_receipt_line_id
)
SELECT
    line.company_id,
    line.goods_receipt_id,
    line.id AS goods_receipt_line_id,
    line.received_quantity::numeric(20, 6) AS received_quantity,
    line.accepted_quantity::numeric(20, 6) AS original_accepted_quantity,
    line.pending_quantity::numeric(20, 6) AS original_pending_quantity,
    line.rejected_quantity::numeric(20, 6) AS original_rejected_quantity,
    (line.accepted_quantity + COALESCE(quality.accepted_delta, 0))::numeric(20, 6) AS accepted_quantity,
    (line.pending_quantity - COALESCE(quality.reclassified_quantity, 0))::numeric(20, 6) AS pending_quantity,
    (line.rejected_quantity + COALESCE(quality.rejected_delta, 0))::numeric(20, 6) AS rejected_quantity
FROM goods_receipt_lines AS line
LEFT JOIN quality
  ON quality.company_id = line.company_id
 AND quality.goods_receipt_id = line.goods_receipt_id
 AND quality.goods_receipt_line_id = line.id
SQL);

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_goods_receipt_source_check');
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_goods_receipt_source_check
CHECK (
    movement_type <> 'goods_receipt_in'
    OR (
        source_type IN ('goods_receipt_line', 'goods_receipt_quality_effect')
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
    quality_effect goods_receipt_quality_effects%ROWTYPE;
    parent_status text;
BEGIN
    IF NEW.movement_type <> 'goods_receipt_in' THEN
        RETURN NEW;
    END IF;

    IF NEW.source_type = 'goods_receipt_line' THEN
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
    END IF;

    IF NEW.source_type = 'goods_receipt_quality_effect' THEN
        SELECT * INTO quality_effect
        FROM goods_receipt_quality_effects
        WHERE company_id = NEW.company_id
          AND id = CAST(NEW.source_id AS bigint)
        FOR SHARE;

        IF NOT FOUND OR quality_effect.disposition <> 'accepted' THEN
            RAISE EXCEPTION 'goods_receipt_in quality source must be an accepted quality effect' USING ERRCODE = '23514';
        END IF;

        SELECT * INTO source_line
        FROM goods_receipt_lines
        WHERE company_id = quality_effect.company_id
          AND goods_receipt_id = quality_effect.goods_receipt_id
          AND id = quality_effect.goods_receipt_line_id
        FOR SHARE;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'goods_receipt_in quality source line not found' USING ERRCODE = '23514';
        END IF;

        SELECT status INTO parent_status
        FROM goods_receipts
        WHERE company_id = quality_effect.company_id
          AND id = quality_effect.goods_receipt_id
        FOR SHARE;

        IF parent_status IS DISTINCT FROM 'finalized'
           OR NEW.product_id IS DISTINCT FROM source_line.product_id
           OR NEW.warehouse_id IS DISTINCT FROM source_line.warehouse_id
           OR NEW.location_id IS DISTINCT FROM source_line.location_id
           OR NEW.quantity_delta IS DISTINCT FROM quality_effect.quantity
           OR NEW.unit_cost IS DISTINCT FROM source_line.provisional_unit_cost THEN
            RAISE EXCEPTION 'goods_receipt_in must exactly match accepted quality effect quantity and custody scope' USING ERRCODE = '23514';
        END IF;

        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'goods_receipt_in source type is not authorized' USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_goods_receipt_quality_commit()
RETURNS trigger AS $$
DECLARE
    source_line goods_receipt_lines%ROWTYPE;
BEGIN
    SELECT * INTO source_line
    FROM goods_receipt_lines
    WHERE company_id = NEW.company_id
      AND goods_receipt_id = NEW.goods_receipt_id
      AND id = NEW.goods_receipt_line_id;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'quality commit source line not found' USING ERRCODE = '23503';
    END IF;

    IF NEW.disposition = 'accepted' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM stock_movements
            WHERE company_id = NEW.company_id
              AND source_type = 'goods_receipt_quality_effect'
              AND source_id = NEW.id::text
              AND effect_type = 'stock.in'
              AND movement_type = 'goods_receipt_in'
              AND product_id = source_line.product_id
              AND warehouse_id = source_line.warehouse_id
              AND location_id = source_line.location_id
              AND quantity_delta = NEW.quantity
              AND unit_cost = source_line.provisional_unit_cost
              AND reversal_of_movement_id IS NULL
        ) THEN
            RAISE EXCEPTION 'accepted quality effect requires exact stock in' USING ERRCODE = '23514';
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM purchase_order_line_progress_effects
            WHERE company_id = NEW.company_id
              AND purchase_order_id = source_line.purchase_order_id
              AND purchase_order_line_id = source_line.purchase_order_line_id
              AND progress_type = 'received'
              AND quantity_delta = NEW.quantity
              AND source_type = 'goods_receipt_quality_effect'
              AND source_id = NEW.id::text
              AND effect_type = 'progress.receive'
        ) THEN
            RAISE EXCEPTION 'accepted quality effect requires exact purchase order receive progress' USING ERRCODE = '23514';
        END IF;
    ELSE
        IF EXISTS (
            SELECT 1 FROM stock_movements
            WHERE company_id = NEW.company_id
              AND source_type = 'goods_receipt_quality_effect'
              AND source_id = NEW.id::text
        ) OR EXISTS (
            SELECT 1 FROM purchase_order_line_progress_effects
            WHERE company_id = NEW.company_id
              AND source_type = 'goods_receipt_quality_effect'
              AND source_id = NEW.id::text
        ) THEN
            RAISE EXCEPTION 'rejected quality effect cannot create stock or receive progress' USING ERRCODE = '23514';
        END IF;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM audit_entries
        WHERE company_id = NEW.company_id
          AND action = 'goods_receipts.quality.reclassified'
          AND target_type = 'goods_receipt'
          AND target_id = NEW.goods_receipt_id::text
          AND metadata->>'quality_effect_id' = NEW.id::text
    ) THEN
        RAISE EXCEPTION 'quality reclassification requires immutable audit evidence' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER goods_receipt_quality_commit_guard
AFTER INSERT ON goods_receipt_quality_effects
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_goods_receipt_quality_commit();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS goods_receipt_line_quality');
        DB::statement('DROP TRIGGER IF EXISTS goods_receipt_quality_commit_guard ON goods_receipt_quality_effects');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_goods_receipt_quality_commit()');

        DB::statement('DROP TRIGGER IF EXISTS stock_movements_immutable_trigger ON stock_movements');
        DB::statement("UPDATE stock_movements SET movement_type = 'adjustment_in' WHERE movement_type = 'goods_receipt_in' AND source_type = 'goods_receipt_quality_effect'");
        DB::unprepared(<<<'SQL'
CREATE TRIGGER stock_movements_immutable_trigger
BEFORE UPDATE OR DELETE ON stock_movements
FOR EACH ROW EXECUTE FUNCTION mars_prevent_stock_movement_mutation();
SQL);

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_goods_receipt_source_check');
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
SQL);

        DB::statement('DROP TRIGGER IF EXISTS goods_receipt_quality_effects_guard ON goods_receipt_quality_effects');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_goods_receipt_quality_effect()');
        Schema::dropIfExists('goods_receipt_quality_effects');
    }
};
