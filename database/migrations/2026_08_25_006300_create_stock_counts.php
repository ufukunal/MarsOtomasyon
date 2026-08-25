<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->string('operation_key', 64);
            $table->string('status', 16)->default('draft');
            $table->timestampTz('started_at');
            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])
                ->on('warehouses')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])
                ->on('warehouse_locations')
                ->restrictOnDelete();
            $table->unique(['company_id', 'id'], 'stock_counts_company_id_id_unique');
            $table->unique(['company_id', 'operation_key'], 'stock_counts_company_operation_unique');
            $table->index(['company_id', 'status', 'started_at'], 'stock_counts_company_status_started_index');
        });

        DB::statement("ALTER TABLE stock_counts ADD CONSTRAINT stock_counts_status_check CHECK (status IN ('draft', 'posted'))");
        DB::statement("ALTER TABLE stock_counts ADD CONSTRAINT stock_counts_posted_shape_check CHECK ((status = 'draft' AND posted_at IS NULL) OR (status = 'posted' AND posted_at IS NOT NULL))");
        DB::statement('ALTER TABLE stock_counts ADD CONSTRAINT stock_counts_operation_key_check CHECK (operation_key = btrim(operation_key) AND char_length(operation_key) > 0)');

        Schema::create('stock_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('stock_count_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('expected_quantity', 20, 6)->default(0);
            $table->decimal('expected_unit_cost', 20, 6)->default(0);
            $table->decimal('expected_value', 20, 6)->default(0);
            $table->decimal('counted_quantity', 20, 6)->default(0);
            $table->decimal('valuation_unit_cost', 20, 6)->nullable();
            $table->unsignedBigInteger('adjustment_movement_id')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'stock_count_id'])
                ->references(['company_id', 'id'])
                ->on('stock_counts')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
            $table->foreign('adjustment_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
            $table->unique(['company_id', 'stock_count_id', 'product_id'], 'stock_count_lines_product_unique');
            $table->index(['company_id', 'stock_count_id', 'id'], 'stock_count_lines_count_index');
        });

        DB::statement('ALTER TABLE stock_count_lines ADD COLUMN variance_quantity numeric(20,6) GENERATED ALWAYS AS (counted_quantity - expected_quantity) STORED');
        DB::statement('ALTER TABLE stock_count_lines ADD CONSTRAINT stock_count_lines_quantity_check CHECK (expected_quantity >= 0 AND counted_quantity >= 0)');
        DB::statement('ALTER TABLE stock_count_lines ADD CONSTRAINT stock_count_lines_cost_check CHECK (expected_unit_cost >= 0 AND expected_value >= 0 AND (valuation_unit_cost IS NULL OR valuation_unit_cost > 0))');
        DB::statement('ALTER TABLE stock_count_lines ADD CONSTRAINT stock_count_lines_snapshot_value_check CHECK ((expected_quantity = 0 AND expected_unit_cost = 0 AND expected_value = 0) OR (expected_quantity > 0 AND expected_unit_cost > 0 AND expected_value > 0))');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION mars_guard_stock_count_line()
            RETURNS trigger AS $$
            DECLARE
                parent_count stock_counts%ROWTYPE;
                movement stock_movements%ROWTYPE;
                expected_source_id text;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'stock count lines cannot be deleted';
                END IF;

                SELECT * INTO parent_count
                FROM stock_counts
                WHERE id = NEW.stock_count_id
                  AND company_id = NEW.company_id
                FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'stock count parent does not belong to company';
                END IF;

                IF parent_count.status <> 'draft' THEN
                    RAISE EXCEPTION 'posted stock count lines are immutable';
                END IF;

                IF TG_OP = 'UPDATE' AND (
                    NEW.company_id <> OLD.company_id OR
                    NEW.stock_count_id <> OLD.stock_count_id OR
                    NEW.product_id <> OLD.product_id OR
                    NEW.expected_quantity <> OLD.expected_quantity OR
                    NEW.expected_unit_cost <> OLD.expected_unit_cost OR
                    NEW.expected_value <> OLD.expected_value OR
                    OLD.adjustment_movement_id IS NOT NULL OR
                    (NEW.adjustment_movement_id IS NOT NULL AND OLD.adjustment_movement_id IS NOT NULL)
                ) THEN
                    RAISE EXCEPTION 'stock count snapshot identity is immutable';
                END IF;

                IF NEW.adjustment_movement_id IS NOT NULL THEN
                    SELECT * INTO movement
                    FROM stock_movements
                    WHERE id = NEW.adjustment_movement_id;

                    expected_source_id := 'count-' || NEW.stock_count_id::text || '-line-' || NEW.id::text;

                    IF NOT FOUND OR
                       movement.company_id <> NEW.company_id OR
                       movement.product_id <> NEW.product_id OR
                       movement.warehouse_id <> parent_count.warehouse_id OR
                       movement.location_id <> parent_count.location_id OR
                       movement.source_type <> 'inventory.stock_count' OR
                       movement.source_id <> expected_source_id OR
                       movement.effect_type <> 'inventory.count_adjustment' OR
                       movement.quantity_delta <> NEW.variance_quantity OR
                       (NEW.variance_quantity > 0 AND movement.movement_type <> 'adjustment_in') OR
                       (NEW.variance_quantity < 0 AND movement.movement_type <> 'adjustment_out') OR
                       NEW.variance_quantity = 0 THEN
                        RAISE EXCEPTION 'stock count adjustment movement does not match line variance';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER stock_count_lines_guard_trigger
            BEFORE INSERT OR UPDATE OR DELETE ON stock_count_lines
            FOR EACH ROW EXECUTE FUNCTION mars_guard_stock_count_line();

            CREATE OR REPLACE FUNCTION mars_guard_stock_count()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'stock counts cannot be deleted';
                END IF;

                IF NEW.company_id <> OLD.company_id OR
                   NEW.warehouse_id <> OLD.warehouse_id OR
                   NEW.location_id <> OLD.location_id OR
                   NEW.operation_key <> OLD.operation_key OR
                   NEW.started_at <> OLD.started_at THEN
                    RAISE EXCEPTION 'stock count scope and identity are immutable';
                END IF;

                IF OLD.status = 'posted' THEN
                    RAISE EXCEPTION 'posted stock counts are immutable';
                END IF;

                IF NEW.status = 'posted' THEN
                    IF NEW.posted_at IS NULL THEN
                        RAISE EXCEPTION 'posted stock count requires posted_at';
                    END IF;

                    IF EXISTS (
                        SELECT 1
                        FROM stock_count_lines
                        WHERE stock_count_id = NEW.id
                          AND company_id = NEW.company_id
                          AND ((variance_quantity <> 0 AND adjustment_movement_id IS NULL)
                            OR (variance_quantity = 0 AND adjustment_movement_id IS NOT NULL))
                    ) THEN
                        RAISE EXCEPTION 'stock count variance reconciliation is incomplete';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER stock_counts_guard_trigger
            BEFORE UPDATE OR DELETE ON stock_counts
            FOR EACH ROW EXECUTE FUNCTION mars_guard_stock_count();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS stock_counts_guard_trigger ON stock_counts');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_stock_count()');
        DB::statement('DROP TRIGGER IF EXISTS stock_count_lines_guard_trigger ON stock_count_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_stock_count_line()');
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
    }
};
