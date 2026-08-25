<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! class_exists('AddStockAvailabilityProjection20260825', false)) {
    final class AddStockAvailabilityProjection20260825 extends Migration
    {
        public function up(): void
        {
            Schema::table('stock_balances', function (Blueprint $table): void {
                $table->decimal('reserved_quantity', 20, 6)->default(0)->after('quantity');
                $table->decimal('blocked_quantity', 20, 6)->default(0)->after('reserved_quantity');
            });

            DB::statement(<<<'SQL'
                ALTER TABLE stock_balances
                ADD COLUMN available_quantity numeric(20,6)
                GENERATED ALWAYS AS (quantity - reserved_quantity - blocked_quantity) STORED
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE stock_balances
                ADD CONSTRAINT stock_balances_reserved_non_negative_check
                CHECK (reserved_quantity >= 0)
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE stock_balances
                ADD CONSTRAINT stock_balances_blocked_non_negative_check
                CHECK (blocked_quantity >= 0)
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE stock_balances
                ADD CONSTRAINT stock_balances_allocation_within_physical_check
                CHECK (reserved_quantity + blocked_quantity <= quantity)
                SQL);

            Schema::table('stock_balances', function (Blueprint $table): void {
                $table->index(
                    ['company_id', 'product_id', 'available_quantity'],
                    'stock_balances_company_product_available_index',
                );
            });
        }

        public function down(): void
        {
            Schema::table('stock_balances', function (Blueprint $table): void {
                $table->dropIndex('stock_balances_company_product_available_index');
            });

            DB::statement('ALTER TABLE stock_balances DROP CONSTRAINT IF EXISTS stock_balances_allocation_within_physical_check');
            DB::statement('ALTER TABLE stock_balances DROP CONSTRAINT IF EXISTS stock_balances_blocked_non_negative_check');
            DB::statement('ALTER TABLE stock_balances DROP CONSTRAINT IF EXISTS stock_balances_reserved_non_negative_check');
            DB::statement('ALTER TABLE stock_balances DROP COLUMN IF EXISTS available_quantity');

            Schema::table('stock_balances', function (Blueprint $table): void {
                $table->dropColumn(['reserved_quantity', 'blocked_quantity']);
            });
        }
    }
}

return new AddStockAvailabilityProjection20260825;
