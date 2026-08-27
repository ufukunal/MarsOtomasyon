<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER goods_receipts_finalization_immediate_guard
BEFORE UPDATE OF status ON goods_receipts
FOR EACH ROW EXECUTE FUNCTION mars_guard_goods_receipt_finalization_commit();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS goods_receipts_finalization_immediate_guard ON goods_receipts');

        // The previous schema has no goods_receipt_in movement type. Preserve the
        // physical/value effect while mapping historical rows to its legacy
        // generic inbound equivalent before the older constraint is restored.
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_immutable_trigger ON stock_movements');
        DB::statement("UPDATE stock_movements SET movement_type = 'adjustment_in' WHERE movement_type = 'goods_receipt_in'");
        DB::unprepared(<<<'SQL'
CREATE TRIGGER stock_movements_immutable_trigger
BEFORE UPDATE OR DELETE ON stock_movements
FOR EACH ROW EXECUTE FUNCTION mars_prevent_stock_movement_mutation();
SQL);
    }
};
