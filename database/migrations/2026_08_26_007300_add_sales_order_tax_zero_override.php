<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->boolean('tax_is_zeroed')->default(false);
        });

        DB::statement(<<<'SQL'
ALTER TABLE sales_order_lines
ADD CONSTRAINT sales_order_lines_tax_zero_override_shape_check
CHECK (
    tax_is_zeroed = FALSE
    OR (
        tax_rate = 0
        AND tax_zero_reason_id IS NOT NULL
        AND tax_zero_reason_code IS NOT NULL
    )
)
SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sales_order_lines DROP CONSTRAINT sales_order_lines_tax_zero_override_shape_check');

        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->dropColumn('tax_is_zeroed');
        });
    }
};
