<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('period')->statement(
            'ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_kind_phase1_valid'
        );
        DB::connection('period')->statement(
            "ALTER TABLE locations ADD CONSTRAINT locations_kind_phase8_valid
             CHECK (kind IN ('warehouse','branch','vehicle','subcontractor'))"
        );
        DB::connection('period')->statement(
            "ALTER TABLE locations ADD CONSTRAINT locations_subcontractor_shape_valid CHECK (
                (kind = 'subcontractor' AND subcontractor_contact_id IS NOT NULL AND plate IS NULL AND is_default = false)
                OR
                (kind <> 'subcontractor' AND subcontractor_contact_id IS NULL)
            )"
        );

        Schema::connection('period')->create('production_recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('number', 40);
            $table->unsignedSmallInteger('revision_no');
            $table->decimal('output_quantity', 18, 3);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'revision_no']);
            $table->index(['product_id', 'is_active']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE production_recipes ADD CONSTRAINT production_recipes_output_positive CHECK (output_quantity > 0)'
        );
        DB::connection('period')->statement(
            'CREATE UNIQUE INDEX production_recipes_one_active_per_product
             ON production_recipes (product_id) WHERE is_active = true'
        );

        Schema::connection('period')->create('production_recipe_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_recipe_id')->constrained('production_recipes')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('base_quantity', 18, 3);
            $table->decimal('conversion_factor', 18, 6);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['production_recipe_id', 'component_product_id'], 'production_recipe_component_unique');
        });

        DB::connection('period')->statement(
            'ALTER TABLE production_recipe_lines ADD CONSTRAINT production_recipe_lines_values_positive
             CHECK (quantity > 0 AND base_quantity > 0 AND conversion_factor > 0)'
        );

        Schema::connection('period')->create('production_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->date('document_date');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('recipe_id')->constrained('production_recipes')->restrictOnDelete();
            $table->unsignedSmallInteger('recipe_revision_no');
            $table->decimal('planned_quantity', 18, 3);
            $table->decimal('completed_quantity', 18, 3)->default(0);
            $table->decimal('cancelled_quantity', 18, 3)->default(0);
            $table->string('production_type', 20)->default('internal');
            $table->foreignId('subcontractor_contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->foreignId('subcontractor_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('source_sales_order_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->string('confirmed_by_name')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['production_type', 'status']);
            $table->index('source_sales_order_id');
        });

        DB::connection('period')->statement(
            "ALTER TABLE production_orders ADD CONSTRAINT production_orders_status_valid
             CHECK (status IN ('draft','confirmed','in_progress','completed','cancelled'))"
        );
        DB::connection('period')->statement(
            "ALTER TABLE production_orders ADD CONSTRAINT production_orders_type_valid
             CHECK (production_type IN ('internal','subcontract'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE production_orders ADD CONSTRAINT production_orders_quantities_valid
             CHECK (planned_quantity > 0 AND completed_quantity >= 0 AND cancelled_quantity >= 0
                    AND completed_quantity + cancelled_quantity <= planned_quantity)'
        );
        DB::connection('period')->statement(
            "ALTER TABLE production_orders ADD CONSTRAINT production_orders_subcontract_shape_valid CHECK (
                (production_type = 'internal' AND subcontractor_contact_id IS NULL AND subcontractor_location_id IS NULL)
                OR
                (production_type = 'subcontract' AND subcontractor_contact_id IS NOT NULL AND subcontractor_location_id IS NOT NULL)
            )"
        );

        Schema::connection('period')->create('production_order_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('planned_quantity', 18, 3);
            $table->decimal('planned_base_quantity', 18, 3);
            $table->decimal('conversion_factor', 18, 6);
            $table->timestamps();

            $table->unique(['production_order_id', 'component_product_id'], 'production_order_component_unique');
        });

        DB::connection('period')->statement(
            'ALTER TABLE production_order_components ADD CONSTRAINT production_order_components_values_positive
             CHECK (planned_quantity > 0 AND planned_base_quantity > 0 AND conversion_factor > 0)'
        );

        Schema::connection('period')->create('production_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->date('completion_date');
            $table->decimal('completed_quantity', 18, 3);
            $table->decimal('material_cost_total', 18, 4);
            $table->decimal('subcontract_service_cost_total', 18, 4)->default(0);
            $table->decimal('production_cost_total', 18, 4);
            $table->decimal('production_unit_cost', 18, 4);
            $table->decimal('moving_average_before', 18, 4);
            $table->decimal('moving_average_after', 18, 4);
            $table->decimal('previous_production_cost', 18, 4);
            $table->foreignId('reversal_of_id')->nullable()->constrained('production_completions')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'completion_date']);
            $table->unique('reversal_of_id', 'production_completion_one_reversal_unique');
        });

        DB::connection('period')->statement(
            'ALTER TABLE production_completions ADD CONSTRAINT production_completions_values_valid CHECK (
                completed_quantity > 0
                AND material_cost_total >= 0
                AND subcontract_service_cost_total >= 0
                AND production_cost_total >= 0
                AND production_unit_cost >= 0
                AND moving_average_before >= 0
                AND moving_average_after >= 0
                AND previous_production_cost >= 0
            )'
        );
        DB::connection('period')->statement(
            'ALTER TABLE production_completions ADD CONSTRAINT production_completions_no_self_reversal
             CHECK (reversal_of_id IS NULL OR reversal_of_id <> id)'
        );

        Schema::connection('period')->create('production_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_completion_id')->constrained('production_completions')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('consumed_quantity', 18, 3);
            $table->decimal('fire_quantity', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_cost', 18, 4);
            $table->foreignId('stock_movement_id')->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique('stock_movement_id');
            $table->index(['production_completion_id', 'component_product_id']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE production_consumptions ADD CONSTRAINT production_consumptions_values_valid CHECK (
                consumed_quantity >= 0 AND fire_quantity >= 0
                AND consumed_quantity + fire_quantity > 0
                AND unit_cost >= 0 AND total_cost >= 0
            )'
        );

        Schema::connection('period')->create('production_outputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_completion_id')->constrained('production_completions')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->foreignId('stock_movement_id')->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique('stock_movement_id');
            $table->index(['production_completion_id', 'location_id']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE production_outputs ADD CONSTRAINT production_outputs_quantity_positive CHECK (quantity > 0)'
        );

        Schema::connection('period')->create('production_service_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('documents')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['production_order_id', 'purchase_invoice_id'], 'production_service_invoice_unique');
            $table->index('purchase_invoice_id');
        });

        Schema::connection('period')->create('production_service_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('purchase_invoice_line_id')->constrained('document_lines')->restrictOnDelete();
            $table->foreignId('production_completion_id')->constrained('production_completions')->restrictOnDelete();
            $table->decimal('quantity_basis', 18, 3);
            $table->decimal('allocated_amount_base', 18, 4);
            $table->decimal('applied_amount_base', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(
                ['purchase_invoice_line_id', 'production_completion_id'],
                'production_service_allocations_unique'
            );
            $table->index(['production_order_id', 'purchase_invoice_id']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE production_service_allocations ADD CONSTRAINT production_service_allocations_values_valid CHECK (
                quantity_basis > 0 AND allocated_amount_base >= 0 AND applied_amount_base >= 0
            )'
        );

        Schema::connection('period')->create('inventory_cost_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('import_file_id')->nullable()->constrained('import_files')->restrictOnDelete();
            $table->unsignedBigInteger('import_file_line_id')->nullable();
            $table->foreignId('production_completion_id')->nullable()->constrained('production_completions')->restrictOnDelete();
            $table->foreignId('production_service_allocation_id')->nullable()->constrained('production_service_allocations')->restrictOnDelete();
            $table->foreignId('adjustment_of_id')->nullable()->constrained('inventory_cost_adjustments')->restrictOnDelete();
            $table->date('adjustment_date');
            $table->decimal('quantity_basis', 18, 3);
            $table->decimal('amount_base', 18, 4);
            $table->decimal('unit_adjustment_base', 18, 4);
            $table->decimal('moving_average_before', 18, 4);
            $table->decimal('moving_average_after', 18, 4);
            $table->string('reason', 30);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'adjustment_date']);
            $table->index('production_completion_id');
            $table->unique('adjustment_of_id', 'inventory_cost_adjustment_one_reversal_unique');
        });

        DB::connection('period')->statement(
            "ALTER TABLE inventory_cost_adjustments ADD CONSTRAINT inventory_cost_adjustments_reason_valid
             CHECK (reason IN ('import_finalize','import_late_cost','subcontract_late_cost','reversal'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE inventory_cost_adjustments ADD CONSTRAINT inventory_cost_adjustments_values_valid CHECK (
                quantity_basis > 0 AND moving_average_before >= 0 AND moving_average_after >= 0
            )'
        );
        DB::connection('period')->statement(
            "ALTER TABLE inventory_cost_adjustments ADD CONSTRAINT inventory_cost_adjustments_provenance_valid CHECK (
                (reason = 'subcontract_late_cost' AND production_completion_id IS NOT NULL AND production_service_allocation_id IS NOT NULL)
                OR
                (reason = 'reversal' AND adjustment_of_id IS NOT NULL)
                OR
                (reason IN ('import_finalize','import_late_cost') AND import_file_id IS NOT NULL)
            )"
        );

        Schema::connection('period')->table('transfers', function (Blueprint $table): void {
            $table->foreignId('production_order_id')
                ->nullable()
                ->after('to_location_id')
                ->constrained('production_orders')
                ->restrictOnDelete();
            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::connection('period')->table('transfers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('production_order_id');
        });

        Schema::connection('period')->dropIfExists('inventory_cost_adjustments');
        Schema::connection('period')->dropIfExists('production_service_allocations');
        Schema::connection('period')->dropIfExists('production_service_invoices');
        Schema::connection('period')->dropIfExists('production_outputs');
        Schema::connection('period')->dropIfExists('production_consumptions');
        Schema::connection('period')->dropIfExists('production_completions');
        Schema::connection('period')->dropIfExists('production_order_components');
        Schema::connection('period')->dropIfExists('production_orders');
        Schema::connection('period')->dropIfExists('production_recipe_lines');
        DB::connection('period')->statement('DROP INDEX IF EXISTS production_recipes_one_active_per_product');
        Schema::connection('period')->dropIfExists('production_recipes');

        DB::connection('period')->statement(
            'ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_subcontractor_shape_valid'
        );
        DB::connection('period')->statement(
            'ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_kind_phase8_valid'
        );
        DB::connection('period')->statement(
            "ALTER TABLE locations ADD CONSTRAINT locations_kind_phase1_valid
             CHECK (kind IN ('warehouse','branch','vehicle'))"
        );
    }
};
