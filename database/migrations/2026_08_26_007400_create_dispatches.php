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
            [
                'key' => 'dispatches.view',
                'name' => 'İrsaliye görüntüleme',
                'description' => 'Aktif şirkette irsaliye ve sevkiyat taslaklarını listeleme ve detay görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'dispatches.manage',
                'name' => 'İrsaliye yönetimi',
                'description' => 'Aktif şirkette satış siparişinden taslak irsaliye oluşturma yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->unique(['company_id', 'account_id', 'id'], 'sales_orders_company_account_id_unique');
        });
        Schema::table('account_addresses', function (Blueprint $table): void {
            $table->unique(['company_id', 'account_id', 'id'], 'account_addresses_company_account_id_unique');
        });

        Schema::create('dispatches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('source_address_id');
            $table->string('number', 64);
            $table->string('series_code', 64);
            $table->unsignedBigInteger('sequence_value');
            $table->string('status', 16)->default('draft');
            $table->date('dispatch_date');
            $table->string('recipient_name', 200)->nullable();
            $table->string('address_line1', 240);
            $table->string('address_line2', 240)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120);
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2);
            $table->string('carrier_name', 200)->nullable();
            $table->string('carrier_service', 120)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'dispatches_company_id_id_unique');
            $table->unique(['company_id', 'id', 'sales_order_id'], 'dispatches_company_id_order_unique');
            $table->unique(['company_id', 'number'], 'dispatches_company_number_unique');
            $table->unique(['company_id', 'series_code', 'sequence_value'], 'dispatches_company_series_sequence_unique');
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id', 'sales_order_id'], 'dispatches_order_account_fk')
                ->references(['company_id', 'account_id', 'id'])->on('sales_orders')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id', 'source_address_id'], 'dispatches_source_address_fk')
                ->references(['company_id', 'account_id', 'id'])->on('account_addresses')->restrictOnDelete();
            $table->index(['company_id', 'status', 'dispatch_date'], 'dispatches_company_status_date_index');
            $table->index(['company_id', 'sales_order_id'], 'dispatches_company_order_index');
        });

        DB::statement("ALTER TABLE dispatches ADD CONSTRAINT dispatches_status_check CHECK (status = 'draft')");
        DB::statement("ALTER TABLE dispatches ADD CONSTRAINT dispatches_series_code_canonical_check CHECK (series_code = lower(btrim(series_code)) AND series_code ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE dispatches ADD CONSTRAINT dispatches_country_code_check CHECK (country_code = upper(country_code) AND country_code ~ '^[A-Z]{2}$')");
        DB::statement('ALTER TABLE dispatches ADD CONSTRAINT dispatches_address_line1_not_blank_check CHECK (char_length(btrim(address_line1)) > 0)');
        DB::statement('ALTER TABLE dispatches ADD CONSTRAINT dispatches_city_not_blank_check CHECK (char_length(btrim(city)) > 0)');

        Schema::create('dispatch_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('dispatch_id');
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('sales_order_line_id');
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->string('product_code', 64);
            $table->string('product_name', 200);
            $table->string('description', 200)->nullable();
            $table->decimal('quantity', 20, 6);
            $table->timestampsTz();

            $table->foreign(['company_id', 'dispatch_id', 'sales_order_id'], 'dispatch_lines_dispatch_order_fk')
                ->references(['company_id', 'id', 'sales_order_id'])->on('dispatches')->restrictOnDelete();
            $table->foreign(['company_id', 'sales_order_id', 'sales_order_line_id'], 'dispatch_lines_source_order_line_fk')
                ->references(['company_id', 'sales_order_id', 'id'])->on('sales_order_lines')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id'])
                ->references(['company_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['company_id', 'warehouse_id', 'location_id'], 'dispatch_lines_location_fk')
                ->references(['company_id', 'warehouse_id', 'id'])->on('warehouse_locations')->restrictOnDelete();
            $table->unique(['company_id', 'dispatch_id', 'position'], 'dispatch_lines_position_unique');
            $table->unique(['company_id', 'dispatch_id', 'sales_order_line_id'], 'dispatch_lines_source_line_unique');
            $table->index(['company_id', 'sales_order_id', 'sales_order_line_id'], 'dispatch_lines_order_line_index');
        });

        DB::statement('ALTER TABLE dispatch_lines ADD CONSTRAINT dispatch_lines_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE dispatch_lines ADD CONSTRAINT dispatch_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE dispatch_lines ADD CONSTRAINT dispatch_lines_product_code_not_blank_check CHECK (char_length(btrim(product_code)) > 0)');
        DB::statement('ALTER TABLE dispatch_lines ADD CONSTRAINT dispatch_lines_product_name_not_blank_check CHECK (char_length(btrim(product_name)) > 0)');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_dispatch_source_order_mutation()
RETURNS trigger AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM dispatches
        WHERE company_id = OLD.company_id
          AND sales_order_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'sales order with dispatch lineage is immutable' USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
        DB::statement('CREATE TRIGGER dispatch_source_order_mutation_guard BEFORE UPDATE ON sales_orders FOR EACH ROW EXECUTE FUNCTION mars_guard_dispatch_source_order_mutation()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_dispatch_source_order_line_mutation()
RETURNS trigger AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM dispatch_lines
        WHERE company_id = OLD.company_id
          AND sales_order_id = OLD.sales_order_id
          AND sales_order_line_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'sales order line with dispatch lineage is immutable' USING ERRCODE = '55000';
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;
SQL);
        DB::statement('CREATE TRIGGER dispatch_source_order_line_mutation_guard BEFORE UPDATE OR DELETE ON sales_order_lines FOR EACH ROW EXECUTE FUNCTION mars_guard_dispatch_source_order_line_mutation()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS dispatch_source_order_line_mutation_guard ON sales_order_lines');
        DB::statement('DROP TRIGGER IF EXISTS dispatch_source_order_mutation_guard ON sales_orders');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_dispatch_source_order_line_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_dispatch_source_order_mutation()');

        Schema::dropIfExists('dispatch_lines');
        Schema::dropIfExists('dispatches');

        Schema::table('account_addresses', function (Blueprint $table): void {
            $table->dropUnique('account_addresses_company_account_id_unique');
        });
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropUnique('sales_orders_company_account_id_unique');
        });

        $permissionIds = array_map(
            'intval',
            DB::table('permissions')->whereIn('key', ['dispatches.view', 'dispatches.manage'])->pluck('id')->all(),
        );
        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }
        DB::table('permissions')->whereIn('key', ['dispatches.view', 'dispatches.manage'])->delete();
    }
};
