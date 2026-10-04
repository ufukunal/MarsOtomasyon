<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('import_files', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('supplier_contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('receiving_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('country', 100)->nullable();
            $table->string('incoterm', 20)->nullable();
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->timestamp('exchange_rate_locked_at')->nullable();
            $table->date('exchange_rate_date')->nullable();
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
            $table->date('received_at')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->string('closed_by_name')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'eta']);
            $table->index(['supplier_contact_id', 'status']);
        });

        Schema::connection('period')->create('containers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_file_id')->constrained('import_files')->restrictOnDelete();
            $table->string('container_no', 80);
            $table->string('container_type', 30)->nullable();
            $table->string('seal_no', 80)->nullable();
            $table->decimal('gross_weight_kg', 18, 3)->nullable();
            $table->decimal('volume_cbm', 18, 4)->nullable();
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
            $table->string('status', 30)->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['import_file_id', 'container_no']);
            $table->unique(['id', 'import_file_id'], 'containers_id_import_file_unique');
            $table->index(['status', 'eta']);
        });

        Schema::connection('period')->create('packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_file_id')->constrained('import_files')->restrictOnDelete();
            $table->foreignId('container_id')->constrained('containers')->restrictOnDelete();
            $table->string('carton_no', 100);
            $table->string('component_name')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('weight_kg', 18, 4)->nullable();
            $table->decimal('volume_cbm', 18, 6)->nullable();
            $table->decimal('goods_value_try', 18, 4)->nullable();
            $table->decimal('allocated_cost_try', 18, 4)->nullable();
            $table->decimal('landed_unit_cost_try', 18, 4)->nullable();
            $table->string('status', 30)->default('unmatched');
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(['container_id', 'import_file_id'], 'packages_container_file_fk')
                ->references(['id', 'import_file_id'])
                ->on('containers')
                ->restrictOnDelete();
            $table->index(['import_file_id', 'product_id']);
            $table->index(['container_id', 'carton_no']);
            $table->index(['status', 'product_id']);
        });

        Schema::connection('period')->create('import_cost_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_file_id')->constrained('import_files')->restrictOnDelete();
            $table->string('name', 120);
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->decimal('amount_try', 18, 4)->nullable();
            $table->string('allocation_basis', 20)->default('value');
            $table->timestamp('allocated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['import_file_id', 'allocation_basis']);
        });

        Schema::connection('period')->create('import_cost_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_cost_item_id')->constrained('import_cost_items')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('container_id')->constrained('containers')->restrictOnDelete();
            $table->decimal('basis_value', 24, 8);
            $table->decimal('allocation_ratio', 18, 10);
            $table->decimal('allocated_amount_try', 18, 4);
            $table->timestamps();

            $table->unique(['import_cost_item_id', 'package_id'], 'import_cost_package_unique');
            $table->index(['product_id', 'container_id']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE import_files ADD CONSTRAINT import_files_status_valid CHECK (status IN ('draft','in_transit','customs','received','closed'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE import_files ADD CONSTRAINT import_files_exchange_rate_positive CHECK (exchange_rate IS NULL OR exchange_rate > 0)'
        );
        DB::connection('period')->statement(
            "ALTER TABLE containers ADD CONSTRAINT containers_status_valid CHECK (status IN ('planned','in_transit','customs','received','closed'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE containers ADD CONSTRAINT containers_dimensions_non_negative CHECK ((gross_weight_kg IS NULL OR gross_weight_kg >= 0) AND (volume_cbm IS NULL OR volume_cbm >= 0))'
        );
        DB::connection('period')->statement(
            'ALTER TABLE packages ADD CONSTRAINT packages_quantity_positive CHECK (quantity > 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE packages ADD CONSTRAINT packages_unit_price_non_negative CHECK (unit_price >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE packages ADD CONSTRAINT packages_dimensions_non_negative CHECK ((weight_kg IS NULL OR weight_kg >= 0) AND (volume_cbm IS NULL OR volume_cbm >= 0))'
        );
        DB::connection('period')->statement(
            'ALTER TABLE packages ADD CONSTRAINT packages_costs_non_negative CHECK ((goods_value_try IS NULL OR goods_value_try >= 0) AND (allocated_cost_try IS NULL OR allocated_cost_try >= 0) AND (landed_unit_cost_try IS NULL OR landed_unit_cost_try >= 0))'
        );
        DB::connection('period')->statement(
            "ALTER TABLE packages ADD CONSTRAINT packages_status_valid CHECK (status IN ('unmatched','matched','received'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE import_cost_items ADD CONSTRAINT import_cost_items_amount_non_negative CHECK (amount >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE import_cost_items ADD CONSTRAINT import_cost_items_exchange_rate_positive CHECK (exchange_rate IS NULL OR exchange_rate > 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE import_cost_items ADD CONSTRAINT import_cost_items_amount_try_non_negative CHECK (amount_try IS NULL OR amount_try >= 0)'
        );
        DB::connection('period')->statement(
            "ALTER TABLE import_cost_items ADD CONSTRAINT import_cost_items_basis_valid CHECK (allocation_basis IN ('value','weight','volume'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE import_cost_allocations ADD CONSTRAINT import_allocations_basis_non_negative CHECK (basis_value >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE import_cost_allocations ADD CONSTRAINT import_allocations_ratio_valid CHECK (allocation_ratio >= 0 AND allocation_ratio <= 1)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE import_cost_allocations ADD CONSTRAINT import_allocations_amount_non_negative CHECK (allocated_amount_try >= 0)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('import_cost_allocations');
        Schema::connection('period')->dropIfExists('import_cost_items');
        Schema::connection('period')->dropIfExists('packages');
        Schema::connection('period')->dropIfExists('containers');
        Schema::connection('period')->dropIfExists('import_files');
    }
};
