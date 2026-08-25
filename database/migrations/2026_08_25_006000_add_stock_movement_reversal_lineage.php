<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('reversal_of_movement_id')->nullable()->after('effect_type');
            $table->foreign('reversal_of_movement_id')
                ->references('id')
                ->on('stock_movements')
                ->restrictOnDelete();
            $table->unique('reversal_of_movement_id', 'stock_movements_one_reversal_unique');
        });

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_direction_check');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_type_check
            CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'reversal_in', 'reversal_out'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_direction_check
            CHECK (
                (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in', 'reversal_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                OR
                (movement_type IN ('adjustment_out', 'transfer_out', 'reversal_out') AND quantity_delta < 0 AND value_delta < 0 AND unit_cost > 0)
            )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION mars_enforce_stock_movement_reversal()
            RETURNS trigger AS $$
            DECLARE
                original_record stock_movements%ROWTYPE;
            BEGIN
                IF NEW.movement_type IN ('reversal_in', 'reversal_out') AND NEW.reversal_of_movement_id IS NULL THEN
                    RAISE EXCEPTION 'stock reversal requires original movement lineage'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.reversal_of_movement_id IS NULL THEN
                    RETURN NEW;
                END IF;

                SELECT *
                  INTO original_record
                  FROM stock_movements
                 WHERE id = NEW.reversal_of_movement_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'stock reversal target does not exist'
                        USING ERRCODE = '23503';
                END IF;

                IF original_record.reversal_of_movement_id IS NOT NULL THEN
                    RAISE EXCEPTION 'a stock reversal cannot itself be reversed'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.company_id <> original_record.company_id
                   OR NEW.product_id <> original_record.product_id
                   OR NEW.warehouse_id <> original_record.warehouse_id
                   OR NEW.location_id <> original_record.location_id
                   OR NEW.quantity_delta <> -original_record.quantity_delta
                   OR NEW.value_delta <> -original_record.value_delta
                   OR NEW.unit_cost <> original_record.unit_cost
                   OR (original_record.quantity_delta > 0 AND NEW.movement_type <> 'reversal_out')
                   OR (original_record.quantity_delta < 0 AND NEW.movement_type <> 'reversal_in') THEN
                    RAISE EXCEPTION 'stock reversal must exactly negate original quantity and carrying value in the same stock scope'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER stock_movements_reversal_guard
            BEFORE INSERT ON stock_movements
            FOR EACH ROW EXECUTE FUNCTION mars_enforce_stock_movement_reversal();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_reversal_guard ON stock_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_enforce_stock_movement_reversal()');

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_direction_check');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_type_check
            CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_direction_check
            CHECK (
                (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                OR
                (movement_type IN ('adjustment_out', 'transfer_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
            )
            SQL);

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropUnique('stock_movements_one_reversal_unique');
            $table->dropForeign(['reversal_of_movement_id']);
            $table->dropColumn('reversal_of_movement_id');
        });
    }
};
