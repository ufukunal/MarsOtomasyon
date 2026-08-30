<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('code', 64);
            $table->string('name', 160);
            $table->decimal('output_quantity', 20, 6);
            $table->boolean('is_active')->default(true);
            $table->string('note', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'production_recipes_company_id_id_unique');
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
            $table->index(['company_id', 'product_id', 'is_active'], 'production_recipes_company_product_active_index');
        });

        DB::statement('CREATE UNIQUE INDEX production_recipes_company_code_lower_unique ON production_recipes (company_id, lower(code))');
        DB::statement('ALTER TABLE production_recipes ADD CONSTRAINT production_recipes_code_not_blank_check CHECK (char_length(btrim(code)) > 0)');
        DB::statement('ALTER TABLE production_recipes ADD CONSTRAINT production_recipes_name_not_blank_check CHECK (char_length(btrim(name)) > 0)');
        DB::statement('ALTER TABLE production_recipes ADD CONSTRAINT production_recipes_output_quantity_positive_check CHECK (output_quantity > 0)');

        Schema::create('production_recipe_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedBigInteger('material_product_id');
            $table->decimal('quantity_per_batch', 20, 6);
            $table->timestampsTz();

            $table->foreign(['company_id', 'recipe_id'])
                ->references(['company_id', 'id'])
                ->on('production_recipes')
                ->cascadeOnDelete();
            $table->foreign(['company_id', 'material_product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
            $table->unique(['recipe_id', 'material_product_id'], 'production_recipe_lines_recipe_material_unique');
        });

        DB::statement('ALTER TABLE production_recipe_lines ADD CONSTRAINT production_recipe_lines_quantity_positive_check CHECK (quantity_per_batch > 0)');

        Schema::create('production_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->string('order_no', 64);
            $table->string('status', 32)->default('draft');
            $table->decimal('planned_quantity', 20, 6);
            $table->decimal('material_cost', 20, 6)->default(0);
            $table->decimal('loss_cost', 20, 6)->default(0);
            $table->decimal('output_quantity', 20, 6)->default(0);
            $table->decimal('output_unit_cost', 20, 6)->default(0);
            $table->decimal('output_value', 20, 6)->default(0);
            $table->unsignedBigInteger('output_stock_movement_id')->nullable();
            $table->timestampTz('material_issued_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'production_orders_company_id_id_unique');
            $table->foreign(['company_id', 'recipe_id'])
                ->references(['company_id', 'id'])
                ->on('production_recipes')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])
                ->on('warehouses')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])
                ->on('warehouse_locations')
                ->restrictOnDelete();
            $table->foreign('output_stock_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
            $table->unique('output_stock_movement_id', 'production_orders_output_stock_movement_unique');
            $table->index(['company_id', 'status', 'id'], 'production_orders_company_status_index');
        });

        DB::statement('CREATE UNIQUE INDEX production_orders_company_order_no_lower_unique ON production_orders (company_id, lower(order_no))');
        DB::statement('ALTER TABLE production_orders ADD CONSTRAINT production_orders_order_no_not_blank_check CHECK (char_length(btrim(order_no)) > 0)');
        DB::statement("ALTER TABLE production_orders ADD CONSTRAINT production_orders_status_check CHECK (status IN ('draft', 'in_progress', 'received', 'completed'))");
        DB::statement('ALTER TABLE production_orders ADD CONSTRAINT production_orders_planned_quantity_positive_check CHECK (planned_quantity > 0)');
        DB::statement('ALTER TABLE production_orders ADD CONSTRAINT production_orders_cost_non_negative_check CHECK (material_cost >= 0 AND loss_cost >= 0 AND output_quantity >= 0 AND output_unit_cost >= 0 AND output_value >= 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE production_orders
            ADD CONSTRAINT production_orders_lifecycle_check
            CHECK (
                (status = 'draft' AND material_issued_at IS NULL AND received_at IS NULL AND completed_at IS NULL AND output_stock_movement_id IS NULL)
                OR
                (status = 'in_progress' AND material_issued_at IS NOT NULL AND received_at IS NULL AND completed_at IS NULL AND output_stock_movement_id IS NULL)
                OR
                (status = 'received' AND material_issued_at IS NOT NULL AND received_at IS NOT NULL AND completed_at IS NULL AND output_stock_movement_id IS NOT NULL)
                OR
                (status = 'completed' AND material_issued_at IS NOT NULL AND received_at IS NOT NULL AND completed_at IS NOT NULL AND output_stock_movement_id IS NOT NULL)
            )
            SQL);

        Schema::create('production_order_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('production_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->decimal('required_quantity', 20, 6);
            $table->decimal('issued_quantity', 20, 6)->default(0);
            $table->decimal('issued_value', 20, 6)->default(0);
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'production_order_id'])
                ->references(['company_id', 'id'])
                ->on('production_orders')
                ->cascadeOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])
                ->on('warehouses')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])
                ->on('warehouse_locations')
                ->restrictOnDelete();
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
            $table->unique(['production_order_id', 'product_id'], 'production_order_materials_order_product_unique');
            $table->unique('stock_movement_id', 'production_order_materials_stock_movement_unique');
        });

        DB::statement('ALTER TABLE production_order_materials ADD CONSTRAINT production_order_materials_quantity_check CHECK (required_quantity > 0 AND issued_quantity >= 0 AND issued_quantity <= required_quantity)');
        DB::statement('ALTER TABLE production_order_materials ADD CONSTRAINT production_order_materials_value_check CHECK (issued_value >= 0)');
        DB::statement('ALTER TABLE production_order_materials ADD CONSTRAINT production_order_materials_posting_check CHECK ((stock_movement_id IS NULL AND issued_quantity = 0 AND issued_value = 0) OR (stock_movement_id IS NOT NULL AND issued_quantity = required_quantity AND issued_value > 0))');

        Schema::create('production_losses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('production_order_id');
            $table->string('operation_key', 64);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->string('loss_type', 16);
            $table->decimal('quantity', 20, 6);
            $table->decimal('carrying_value', 20, 6)->default(0);
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->string('note', 240)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'production_order_id'])
                ->references(['company_id', 'id'])
                ->on('production_orders')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])
                ->on('warehouses')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])
                ->on('warehouse_locations')
                ->restrictOnDelete();
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
            $table->unique(['company_id', 'operation_key'], 'production_losses_company_operation_unique');
            $table->unique('stock_movement_id', 'production_losses_stock_movement_unique');
            $table->index(['company_id', 'production_order_id', 'id'], 'production_losses_order_index');
        });

        DB::statement("ALTER TABLE production_losses ADD CONSTRAINT production_losses_type_check CHECK (loss_type IN ('fire', 'missing'))");
        DB::statement('ALTER TABLE production_losses ADD CONSTRAINT production_losses_quantity_positive_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE production_losses ADD CONSTRAINT production_losses_carrying_value_positive_check CHECK (stock_movement_id IS NULL OR carrying_value > 0)');
        DB::statement('ALTER TABLE production_losses ADD CONSTRAINT production_losses_operation_key_check CHECK (char_length(btrim(operation_key)) > 0 AND operation_key = btrim(operation_key))');

        Schema::create('production_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('production_order_id');
            $table->string('event_type', 64);
            $table->jsonb('payload');
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'production_order_id'])
                ->references(['company_id', 'id'])
                ->on('production_orders')
                ->cascadeOnDelete();
            $table->index(['company_id', 'production_order_id', 'id'], 'production_events_order_index');
        });

        DB::statement("ALTER TABLE production_events ADD CONSTRAINT production_events_type_canonical_check CHECK (event_type ~ '^[a-z0-9]+([._-][a-z0-9]+)*$')");

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_direction_check');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_type_check
            CHECK (movement_type IN (
                'opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out',
                'dispatch_out', 'invoice_out', 'goods_receipt_in', 'purchase_return_out', 'sales_return_in',
                'production_material_out', 'production_loss_out', 'production_receipt_in'
            ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_direction_check
            CHECK (
                (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in', 'goods_receipt_in', 'sales_return_in', 'production_receipt_in')
                    AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                OR
                (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out', 'purchase_return_out', 'production_material_out', 'production_loss_out')
                    AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
            )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION mars_prevent_production_event_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'production_events is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER production_events_immutable_trigger
            BEFORE UPDATE OR DELETE ON production_events
            FOR EACH ROW EXECUTE FUNCTION mars_prevent_production_event_mutation();

            CREATE OR REPLACE FUNCTION mars_guard_production_order_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'production order deletion is not allowed';
                END IF;
                IF OLD.status = 'completed' THEN
                    RAISE EXCEPTION 'completed production order is immutable';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER production_orders_mutation_guard_trigger
            BEFORE UPDATE OR DELETE ON production_orders
            FOR EACH ROW EXECUTE FUNCTION mars_guard_production_order_mutation();

            CREATE OR REPLACE FUNCTION mars_guard_posted_production_row()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.stock_movement_id IS NOT NULL THEN
                    RAISE EXCEPTION 'posted production row is immutable';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER production_order_materials_posted_immutable_trigger
            BEFORE UPDATE OR DELETE ON production_order_materials
            FOR EACH ROW EXECUTE FUNCTION mars_guard_posted_production_row();

            CREATE TRIGGER production_losses_posted_immutable_trigger
            BEFORE UPDATE OR DELETE ON production_losses
            FOR EACH ROW EXECUTE FUNCTION mars_guard_posted_production_row();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS production_losses_posted_immutable_trigger ON production_losses');
        DB::statement('DROP TRIGGER IF EXISTS production_order_materials_posted_immutable_trigger ON production_order_materials');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_posted_production_row()');
        DB::statement('DROP TRIGGER IF EXISTS production_orders_mutation_guard_trigger ON production_orders');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_production_order_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS production_events_immutable_trigger ON production_events');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_production_event_mutation()');

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_direction_check');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_type_check
            CHECK (movement_type IN (
                'opening_in', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out',
                'dispatch_out', 'invoice_out', 'goods_receipt_in', 'purchase_return_out', 'sales_return_in'
            ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_direction_check
            CHECK (
                (movement_type IN ('opening_in', 'adjustment_in', 'transfer_in', 'goods_receipt_in', 'sales_return_in')
                    AND quantity_delta > 0 AND value_delta > 0 AND unit_cost > 0)
                OR
                (movement_type IN ('adjustment_out', 'transfer_out', 'dispatch_out', 'invoice_out', 'purchase_return_out')
                    AND quantity_delta < 0 AND value_delta <= 0 AND unit_cost >= 0)
            )
            SQL);

        Schema::dropIfExists('production_events');
        Schema::dropIfExists('production_losses');
        Schema::dropIfExists('production_order_materials');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('production_recipe_lines');
        Schema::dropIfExists('production_recipes');
    }
};
