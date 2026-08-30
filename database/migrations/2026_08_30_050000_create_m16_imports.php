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
        DB::table('permissions')->insert([
            ['key' => 'imports.view', 'name' => 'İthalat görüntüleme', 'description' => 'İthalat dosyası, konteyner, malzeme konumu ve landed-cost analizini görüntüleme yetkisi.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'imports.manage', 'name' => 'İthalat yönetimi', 'description' => 'İthalat dosyası, konteyner, stok handoff ve landed-cost işlemlerini yönetme yetkisi.', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('import_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('supplier_account_id')->nullable();
            $table->string('number', 64);
            $table->string('status', 24)->default('draft');
            $table->char('currency_code', 3);
            $table->string('supplier_reference', 100)->nullable();
            $table->string('origin_country', 100)->nullable();
            $table->string('loading_port', 150)->nullable();
            $table->string('destination_port', 150)->nullable();
            $table->date('departure_date')->nullable();
            $table->date('expected_arrival_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'import_files_company_id_id_unique');
            $table->unique(['company_id', 'number'], 'import_files_company_number_unique');
            $table->foreign(['company_id', 'supplier_account_id'])->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->index(['company_id', 'status', 'expected_arrival_date'], 'import_files_status_eta_index');
        });

        Schema::create('import_containers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('import_file_id');
            $table->string('container_no', 32);
            $table->string('seal_no', 64)->nullable();
            $table->string('container_type', 32)->nullable();
            $table->decimal('max_weight_kg', 20, 6)->nullable();
            $table->decimal('max_volume_m3', 20, 6)->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'import_containers_company_id_id_unique');
            $table->unique(['company_id', 'import_file_id', 'container_no'], 'import_containers_number_unique');
            $table->foreign(['company_id', 'import_file_id'])->references(['company_id', 'id'])->on('import_files')->cascadeOnDelete();
        });

        Schema::create('import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('import_file_id');
            $table->unsignedBigInteger('import_container_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->string('package_reference', 100)->nullable();
            $table->string('component_reference', 100)->nullable();
            $table->decimal('quantity', 20, 6);
            $table->unsignedInteger('package_count')->default(0);
            $table->decimal('gross_weight_kg', 20, 6)->default(0);
            $table->decimal('net_weight_kg', 20, 6)->default(0);
            $table->decimal('volume_m3', 20, 6)->default(0);
            $table->text('material_location')->nullable();
            $table->boolean('subcontract_collection')->default(false);
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'import_items_company_id_id_unique');
            $table->foreign(['company_id', 'import_file_id'])->references(['company_id', 'id'])->on('import_files')->cascadeOnDelete();
            $table->foreign(['company_id', 'import_container_id'])->references(['company_id', 'id'])->on('import_containers')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->index(['company_id', 'import_file_id', 'product_id'], 'import_items_file_product_index');
        });

        Schema::create('import_receipt_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('import_file_id');
            $table->unsignedBigInteger('import_item_id');
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('goods_receipt_line_id');
            $table->decimal('linked_quantity', 20, 6);
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'import_receipt_links_company_id_id_unique');
            $table->unique(['company_id', 'goods_receipt_id', 'goods_receipt_line_id'], 'import_receipt_links_receipt_line_unique');
            $table->foreign(['company_id', 'import_file_id'])->references(['company_id', 'id'])->on('import_files')->restrictOnDelete();
            $table->foreign(['company_id', 'import_item_id'])->references(['company_id', 'id'])->on('import_items')->restrictOnDelete();
            $table->foreign(['company_id', 'goods_receipt_id', 'goods_receipt_line_id'], 'import_receipt_links_goods_line_fk')->references(['company_id', 'goods_receipt_id', 'id'])->on('goods_receipt_lines')->restrictOnDelete();
        });

        Schema::create('import_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('import_file_id');
            $table->string('expense_code', 64);
            $table->string('description', 200);
            $table->decimal('amount', 20, 6);
            $table->char('currency_code', 3);
            $table->string('status', 16)->default('provisional');
            $table->string('allocation_basis', 16)->default('line_value');
            $table->timestampTz('finalized_at')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'import_expenses_company_id_id_unique');
            $table->unique(['company_id', 'import_file_id', 'expense_code'], 'import_expenses_code_unique');
            $table->foreign(['company_id', 'import_file_id'])->references(['company_id', 'id'])->on('import_files')->restrictOnDelete();
        });

        Schema::create('import_landed_cost_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('import_file_id');
            $table->string('operation_key', 64);
            $table->string('allocation_basis', 16);
            $table->decimal('expense_total', 20, 6);
            $table->char('currency_code', 3);
            $table->timestampTz('posted_at');
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'import_cost_batches_company_id_id_unique');
            $table->unique(['company_id', 'import_file_id', 'operation_key'], 'import_cost_batches_operation_unique');
            $table->foreign(['company_id', 'import_file_id'])->references(['company_id', 'id'])->on('import_files')->restrictOnDelete();
        });

        Schema::create('import_landed_cost_batch_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('landed_cost_batch_id');
            $table->unsignedBigInteger('import_expense_id');
            $table->decimal('amount_snapshot', 20, 6);
            $table->timestampsTz();

            $table->unique(['company_id', 'import_expense_id'], 'import_cost_batch_expense_once_unique');
            $table->foreign(['company_id', 'landed_cost_batch_id'])->references(['company_id', 'id'])->on('import_landed_cost_batches')->restrictOnDelete();
            $table->foreign(['company_id', 'import_expense_id'])->references(['company_id', 'id'])->on('import_expenses')->restrictOnDelete();
        });

        Schema::create('import_landed_cost_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('landed_cost_batch_id');
            $table->unsignedBigInteger('import_receipt_link_id');
            $table->unsignedBigInteger('goods_receipt_cost_adjustment_id');
            $table->decimal('allocation_weight', 20, 6);
            $table->decimal('allocated_amount', 20, 6);
            $table->timestampsTz();

            $table->unique(['company_id', 'landed_cost_batch_id', 'import_receipt_link_id'], 'import_cost_alloc_link_unique');
            $table->foreign(['company_id', 'landed_cost_batch_id'])->references(['company_id', 'id'])->on('import_landed_cost_batches')->restrictOnDelete();
            $table->foreign(['company_id', 'import_receipt_link_id'])->references(['company_id', 'id'])->on('import_receipt_links')->restrictOnDelete();
            $table->foreign('goods_receipt_cost_adjustment_id')->references('id')->on('goods_receipt_cost_adjustments')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE import_files ADD CONSTRAINT import_files_status_check CHECK (status IN ('draft','in_transit','arrived','receiving','completed','cancelled'))");
        DB::statement("ALTER TABLE import_files ADD CONSTRAINT import_files_currency_check CHECK (currency_code = upper(currency_code) AND currency_code ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE import_files ADD CONSTRAINT import_files_dates_check CHECK (expected_arrival_date IS NULL OR departure_date IS NULL OR expected_arrival_date >= departure_date)');
        DB::statement("ALTER TABLE import_files ADD CONSTRAINT import_files_completed_check CHECK ((status = 'completed' AND completed_at IS NOT NULL) OR (status <> 'completed' AND completed_at IS NULL))");
        DB::statement('ALTER TABLE import_containers ADD CONSTRAINT import_containers_capacity_check CHECK ((max_weight_kg IS NULL OR max_weight_kg > 0) AND (max_volume_m3 IS NULL OR max_volume_m3 > 0))');
        DB::statement('ALTER TABLE import_items ADD CONSTRAINT import_items_quantity_check CHECK (quantity > 0 AND gross_weight_kg >= 0 AND net_weight_kg >= 0 AND volume_m3 >= 0 AND gross_weight_kg >= net_weight_kg)');
        DB::statement('ALTER TABLE import_receipt_links ADD CONSTRAINT import_receipt_links_quantity_check CHECK (linked_quantity > 0)');
        DB::statement("ALTER TABLE import_expenses ADD CONSTRAINT import_expenses_status_check CHECK (status IN ('provisional','final'))");
        DB::statement("ALTER TABLE import_expenses ADD CONSTRAINT import_expenses_basis_check CHECK (allocation_basis IN ('line_value','quantity'))");
        DB::statement("ALTER TABLE import_expenses ADD CONSTRAINT import_expenses_currency_check CHECK (currency_code = upper(currency_code) AND currency_code ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE import_expenses ADD CONSTRAINT import_expenses_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE import_expenses ADD CONSTRAINT import_expenses_finalized_check CHECK ((status = 'final' AND finalized_at IS NOT NULL) OR (status = 'provisional' AND finalized_at IS NULL))");
        DB::statement("ALTER TABLE import_landed_cost_batches ADD CONSTRAINT import_cost_batches_basis_check CHECK (allocation_basis IN ('line_value','quantity'))");
        DB::statement('ALTER TABLE import_landed_cost_batches ADD CONSTRAINT import_cost_batches_total_check CHECK (expense_total > 0)');
        DB::statement('ALTER TABLE import_landed_cost_batch_expenses ADD CONSTRAINT import_cost_batch_expense_amount_check CHECK (amount_snapshot > 0)');
        DB::statement('ALTER TABLE import_landed_cost_allocations ADD CONSTRAINT import_cost_alloc_amount_check CHECK (allocation_weight > 0 AND allocated_amount > 0)');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_import_item_container()
RETURNS trigger AS $$
DECLARE
    container_file_id bigint;
BEGIN
    IF NEW.import_container_id IS NULL THEN
        RETURN NEW;
    END IF;
    SELECT import_file_id INTO container_file_id
    FROM import_containers
    WHERE company_id = NEW.company_id AND id = NEW.import_container_id
    FOR SHARE;
    IF container_file_id IS DISTINCT FROM NEW.import_file_id THEN
        RAISE EXCEPTION 'import item container must belong to same import file' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER import_items_container_guard
BEFORE INSERT OR UPDATE OF company_id, import_file_id, import_container_id ON import_items
FOR EACH ROW EXECUTE FUNCTION mars_guard_import_item_container();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_import_receipt_link()
RETURNS trigger AS $$
DECLARE
    item_file_id bigint;
    item_product_id bigint;
    item_quantity numeric(20,6);
    receipt_product_id bigint;
    receipt_accepted numeric(20,6);
    receipt_status text;
    linked_total numeric(20,6);
BEGIN
    SELECT import_file_id, product_id, quantity INTO item_file_id, item_product_id, item_quantity
    FROM import_items
    WHERE company_id = NEW.company_id AND id = NEW.import_item_id
    FOR UPDATE;
    IF item_file_id IS NULL OR item_file_id IS DISTINCT FROM NEW.import_file_id THEN
        RAISE EXCEPTION 'import receipt link item/file mismatch' USING ERRCODE = '23514';
    END IF;

    SELECT line.product_id, line.accepted_quantity, receipt.status
      INTO receipt_product_id, receipt_accepted, receipt_status
    FROM goods_receipt_lines line
    JOIN goods_receipts receipt ON receipt.company_id = line.company_id AND receipt.id = line.goods_receipt_id
    WHERE line.company_id = NEW.company_id
      AND line.goods_receipt_id = NEW.goods_receipt_id
      AND line.id = NEW.goods_receipt_line_id
    FOR SHARE OF line, receipt;

    IF receipt_product_id IS NULL OR receipt_status <> 'finalized' THEN
        RAISE EXCEPTION 'import receipt link requires finalized goods receipt line' USING ERRCODE = '23514';
    END IF;
    IF receipt_product_id IS DISTINCT FROM item_product_id OR NEW.linked_quantity IS DISTINCT FROM receipt_accepted THEN
        RAISE EXCEPTION 'import receipt link product/quantity must match finalized receipt line' USING ERRCODE = '23514';
    END IF;

    SELECT COALESCE(SUM(linked_quantity), 0) INTO linked_total
    FROM import_receipt_links
    WHERE company_id = NEW.company_id AND import_item_id = NEW.import_item_id AND id <> COALESCE(NEW.id, 0);
    IF linked_total + NEW.linked_quantity > item_quantity THEN
        RAISE EXCEPTION 'import receipt links cannot exceed import item quantity' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER import_receipt_links_scope_guard
BEFORE INSERT OR UPDATE ON import_receipt_links
FOR EACH ROW EXECUTE FUNCTION mars_guard_import_receipt_link();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_import_append_only()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION '% is append-only', TG_TABLE_NAME USING ERRCODE = '55000';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER import_receipt_links_append_only BEFORE UPDATE OR DELETE ON import_receipt_links FOR EACH ROW EXECUTE FUNCTION mars_guard_import_append_only();
CREATE TRIGGER import_cost_batches_append_only BEFORE UPDATE OR DELETE ON import_landed_cost_batches FOR EACH ROW EXECUTE FUNCTION mars_guard_import_append_only();
CREATE TRIGGER import_cost_batch_expenses_append_only BEFORE UPDATE OR DELETE ON import_landed_cost_batch_expenses FOR EACH ROW EXECUTE FUNCTION mars_guard_import_append_only();
CREATE TRIGGER import_cost_allocations_append_only BEFORE UPDATE OR DELETE ON import_landed_cost_allocations FOR EACH ROW EXECUTE FUNCTION mars_guard_import_append_only();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_import_expense_mutation()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' AND OLD.status = 'final' THEN
        RAISE EXCEPTION 'final import expense is immutable' USING ERRCODE = '55000';
    END IF;
    IF TG_OP = 'UPDATE' AND OLD.status = 'final' THEN
        RAISE EXCEPTION 'final import expense is immutable' USING ERRCODE = '55000';
    END IF;
    IF TG_OP = 'UPDATE' AND OLD.status = 'provisional' AND NEW.status = 'final' THEN
        IF NEW.company_id IS DISTINCT FROM OLD.company_id
           OR NEW.import_file_id IS DISTINCT FROM OLD.import_file_id
           OR NEW.expense_code IS DISTINCT FROM OLD.expense_code
           OR NEW.description IS DISTINCT FROM OLD.description
           OR NEW.amount IS DISTINCT FROM OLD.amount
           OR NEW.currency_code IS DISTINCT FROM OLD.currency_code
           OR NEW.allocation_basis IS DISTINCT FROM OLD.allocation_basis
           OR NEW.note IS DISTINCT FROM OLD.note
           OR NEW.finalized_at IS NULL THEN
            RAISE EXCEPTION 'expense finalization may only change lifecycle fields' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER import_expenses_mutation_guard
BEFORE UPDATE OR DELETE ON import_expenses
FOR EACH ROW EXECUTE FUNCTION mars_guard_import_expense_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('import_landed_cost_allocations');
        Schema::dropIfExists('import_landed_cost_batch_expenses');
        Schema::dropIfExists('import_landed_cost_batches');
        Schema::dropIfExists('import_expenses');
        Schema::dropIfExists('import_receipt_links');
        Schema::dropIfExists('import_items');
        Schema::dropIfExists('import_containers');
        Schema::dropIfExists('import_files');
        DB::unprepared('DROP FUNCTION IF EXISTS mars_guard_import_expense_mutation() CASCADE; DROP FUNCTION IF EXISTS mars_guard_import_append_only() CASCADE; DROP FUNCTION IF EXISTS mars_guard_import_receipt_link() CASCADE; DROP FUNCTION IF EXISTS mars_guard_import_item_container() CASCADE;');
        $permissionIds = DB::table('permissions')->whereIn('key', ['imports.view', 'imports.manage'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
