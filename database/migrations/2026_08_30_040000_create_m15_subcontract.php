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
        DB::table('permissions')->insertOrIgnore([
            ['key' => 'subcontract.view', 'name' => 'Fason görüntüleme', 'description' => 'Fason sipariş, custody, fire/eksik, mamul kabul, teknik dosya ve raporlarını görüntüleme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'subcontract.manage', 'name' => 'Fason yönetimi', 'description' => 'Fason sipariş oluşturma, malzeme gönderme, fire/eksik, mamul kabul ve reconciliation yönetimi.', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('subcontract_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('supplier_account_id');
            $table->unsignedBigInteger('output_product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->string('order_no', 64);
            $table->string('status', 24)->default('draft');
            $table->decimal('planned_output_quantity', 20, 6);
            $table->decimal('sent_value', 20, 6)->default(0);
            $table->decimal('loss_value', 20, 6)->default(0);
            $table->decimal('received_output_quantity', 20, 6)->default(0);
            $table->decimal('received_output_value', 20, 6)->default(0);
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'subcontract_orders_company_id_id_unique');
            $table->unique(['company_id', 'order_no'], 'subcontract_orders_company_order_unique');
            $table->foreign(['company_id', 'supplier_account_id'])->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'output_product_id'])->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])->references(['company_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'])->references(['company_id', 'warehouse_id', 'id'])->on('warehouse_locations')->restrictOnDelete();
            $table->index(['company_id', 'status', 'id'], 'subcontract_orders_company_status_index');
        });
        DB::statement("ALTER TABLE subcontract_orders ADD CONSTRAINT subcontract_orders_status_check CHECK (status IN ('draft','in_progress','completed'))");
        DB::statement('ALTER TABLE subcontract_orders ADD CONSTRAINT subcontract_orders_quantity_check CHECK (planned_output_quantity > 0 AND sent_value >= 0 AND loss_value >= 0 AND received_output_quantity >= 0 AND received_output_value >= 0)');

        Schema::create('subcontract_order_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('subcontract_order_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('planned_quantity', 20, 6);
            $table->decimal('sent_quantity', 20, 6)->default(0);
            $table->decimal('sent_value', 20, 6)->default(0);
            $table->decimal('consumed_quantity', 20, 6)->default(0);
            $table->decimal('consumed_value', 20, 6)->default(0);
            $table->decimal('loss_quantity', 20, 6)->default(0);
            $table->decimal('loss_value', 20, 6)->default(0);
            $table->unsignedBigInteger('send_stock_movement_id')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'subcontract_materials_company_id_id_unique');
            $table->foreign(['company_id', 'subcontract_order_id'])->references(['company_id', 'id'])->on('subcontract_orders')->cascadeOnDelete();
            $table->foreign(['company_id', 'product_id'])->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign('send_stock_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
            $table->unique(['subcontract_order_id', 'product_id'], 'subcontract_materials_order_product_unique');
        });
        DB::statement('ALTER TABLE subcontract_order_materials ADD CONSTRAINT subcontract_materials_quantity_check CHECK (planned_quantity > 0 AND sent_quantity >= 0 AND sent_quantity <= planned_quantity AND consumed_quantity >= 0 AND loss_quantity >= 0 AND consumed_quantity + loss_quantity <= sent_quantity)');
        DB::statement('ALTER TABLE subcontract_order_materials ADD CONSTRAINT subcontract_materials_value_check CHECK (sent_value >= 0 AND consumed_value >= 0 AND loss_value >= 0 AND consumed_value + loss_value <= sent_value)');

        Schema::create('subcontract_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('subcontract_order_id');
            $table->string('operation_key', 64);
            $table->decimal('output_quantity', 20, 6);
            $table->decimal('carrying_value', 20, 6);
            $table->jsonb('consumption_payload');
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->foreign(['company_id', 'subcontract_order_id'])->references(['company_id', 'id'])->on('subcontract_orders')->restrictOnDelete();
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
            $table->unique(['company_id', 'operation_key'], 'subcontract_receipts_company_operation_unique');
            $table->index(['company_id', 'subcontract_order_id', 'occurred_at'], 'subcontract_receipts_order_index');
        });
        DB::statement('ALTER TABLE subcontract_receipts ADD CONSTRAINT subcontract_receipts_positive_check CHECK (output_quantity > 0 AND carrying_value > 0)');

        Schema::create('subcontract_losses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('subcontract_order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('operation_key', 64);
            $table->string('loss_type', 16);
            $table->decimal('quantity', 20, 6);
            $table->decimal('carrying_value', 20, 6);
            $table->string('note', 240)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->foreign(['company_id', 'subcontract_order_id'])->references(['company_id', 'id'])->on('subcontract_orders')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->unique(['company_id', 'operation_key'], 'subcontract_losses_company_operation_unique');
        });
        DB::statement("ALTER TABLE subcontract_losses ADD CONSTRAINT subcontract_losses_type_check CHECK (loss_type IN ('fire','missing'))");
        DB::statement('ALTER TABLE subcontract_losses ADD CONSTRAINT subcontract_losses_positive_check CHECK (quantity > 0 AND carrying_value > 0)');

        Schema::create('subcontract_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('subcontract_order_id');
            $table->string('event_type', 64);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'subcontract_order_id'])->references(['company_id', 'id'])->on('subcontract_orders')->restrictOnDelete();
            $table->index(['company_id', 'subcontract_order_id', 'id'], 'subcontract_events_order_index');
        });

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_type_check
CHECK (movement_type IN (
    'opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out',
    'dispatch_out', 'invoice_out', 'goods_receipt_in', 'purchase_return_out', 'sales_return_in',
    'production_material_out', 'production_loss_out', 'production_receipt_in',
    'subcontract_send_out', 'subcontract_receipt_in'
))
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_events');
        Schema::dropIfExists('subcontract_losses');
        Schema::dropIfExists('subcontract_receipts');
        Schema::dropIfExists('subcontract_order_materials');
        Schema::dropIfExists('subcontract_orders');

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT stock_movements_type_check');
        DB::statement(<<<'SQL'
ALTER TABLE stock_movements
ADD CONSTRAINT stock_movements_type_check
CHECK (movement_type IN (
    'opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out',
    'dispatch_out', 'invoice_out', 'goods_receipt_in', 'purchase_return_out', 'sales_return_in',
    'production_material_out', 'production_loss_out', 'production_receipt_in'
))
SQL);

        $ids = DB::table('permissions')->whereIn('key', ['subcontract.view', 'subcontract.manage'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
