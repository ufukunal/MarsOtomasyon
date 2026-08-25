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
            $table->string('source_type', 64)->nullable()->after('request_fingerprint');
            $table->string('source_id', 255)->nullable()->after('source_type');
            $table->string('effect_type', 64)->nullable()->after('source_id');
        });

        DB::statement('ALTER TABLE stock_movements DISABLE TRIGGER stock_movements_immutable_trigger');
        DB::statement(<<<'SQL'
            UPDATE stock_movements
            SET source_type = 'inventory.manual_stock',
                source_id = operation_key,
                effect_type = CASE movement_type
                    WHEN 'opening_in' THEN 'inventory.opening_in'
                    WHEN 'adjustment_in' THEN 'inventory.adjustment_in'
                    WHEN 'adjustment_out' THEN 'inventory.adjustment_out'
                    ELSE 'inventory.legacy_stock_effect'
                END
            WHERE source_type IS NULL
            SQL);
        DB::statement('ALTER TABLE stock_movements ENABLE TRIGGER stock_movements_immutable_trigger');

        DB::statement('ALTER TABLE stock_movements ALTER COLUMN source_type SET NOT NULL');
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN source_id SET NOT NULL');
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN effect_type SET NOT NULL');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_direction_check');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out'))");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_direction_check CHECK ((movement_type IN ('opening_in', 'adjustment_in', 'transfer_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0) OR (movement_type IN ('adjustment_out', 'transfer_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0))");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_source_type_canonical_check CHECK (source_type ~ '^[a-z0-9]+([._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_effect_type_canonical_check CHECK (effect_type ~ '^[a-z0-9]+([._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_source_id_not_blank_check CHECK (char_length(btrim(source_id)) > 0 AND source_id = btrim(source_id))");

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unique(
                ['company_id', 'source_type', 'source_id', 'effect_type'],
                'stock_movements_source_effect_unique',
            );
            $table->index(
                ['company_id', 'source_type', 'source_id'],
                'stock_movements_source_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropUnique('stock_movements_source_effect_unique');
            $table->dropIndex('stock_movements_source_lookup_index');
        });

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_source_type_canonical_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_effect_type_canonical_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_source_id_not_blank_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_direction_check');
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM stock_movements
                    WHERE movement_type IN ('transfer_in', 'transfer_out')
                ) THEN
                    ALTER TABLE stock_movements
                        ADD CONSTRAINT stock_movements_type_check
                        CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out'));
                    ALTER TABLE stock_movements
                        ADD CONSTRAINT stock_movements_direction_check
                        CHECK (
                            (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                            OR
                            (movement_type IN ('adjustment_out', 'transfer_out') AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
                        );
                ELSE
                    ALTER TABLE stock_movements
                        ADD CONSTRAINT stock_movements_type_check
                        CHECK (movement_type IN ('opening_in', 'adjustment_in', 'adjustment_out'));
                    ALTER TABLE stock_movements
                        ADD CONSTRAINT stock_movements_direction_check
                        CHECK (
                            (movement_type IN ('opening_in', 'adjustment_in') AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                            OR
                            (movement_type = 'adjustment_out' AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
                        );
                END IF;
            END $$;
            SQL);

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn(['source_type', 'source_id', 'effect_type']);
        });
    }
};
