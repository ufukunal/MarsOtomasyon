<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('warehouse_slips', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('slip_date');
            $table->string('direction', 3);
            $table->string('reason', 30);
            $table->string('status', 12)->default('draft');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['slip_date', 'status']);
            $table->index(['location_id', 'direction']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE warehouse_slips
             ADD CONSTRAINT warehouse_slips_direction_valid
             CHECK (direction IN ('in','out'))"
        );
        DB::connection('period')->statement(
            "ALTER TABLE warehouse_slips
             ADD CONSTRAINT warehouse_slips_status_valid
             CHECK (status IN ('draft','posted','cancelled'))"
        );

        Schema::connection('period')->create('warehouse_slip_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_slip_id')->constrained('warehouse_slips')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['warehouse_slip_id', 'product_id']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE warehouse_slip_lines
             ADD CONSTRAINT warehouse_slip_lines_quantity_positive
             CHECK (quantity > 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE warehouse_slip_lines
             ADD CONSTRAINT warehouse_slip_lines_unit_cost_nonnegative
             CHECK (unit_cost IS NULL OR unit_cost >= 0)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('warehouse_slip_lines');
        Schema::connection('period')->dropIfExists('warehouse_slips');
    }
};
