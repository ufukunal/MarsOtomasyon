<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_users', function (Blueprint $table): void {
            $table->string('role', 16)->default('buyer');
            $table->jsonb('permissions')->default('["orders.place","prices.view","stock.view","orders.history"]');
            $table->unsignedInteger('auth_version')->default(1);
            $table->timestampTz('password_changed_at')->nullable();
        });

        DB::statement("ALTER TABLE b2b_users ADD CONSTRAINT b2b_users_role_check CHECK (role IN ('admin', 'buyer', 'viewer'))");
        DB::statement('ALTER TABLE b2b_users ADD CONSTRAINT b2b_users_auth_version_positive_check CHECK (auth_version > 0)');

        Schema::table('account_b2b_policies', function (Blueprint $table): void {
            $table->boolean('show_price')->default(false);
            $table->boolean('show_balance')->default(false);
            $table->unsignedBigInteger('default_warehouse_id')->nullable();
            $table->string('risk_behavior', 16)->default('block');
            $table->foreign('default_warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE account_b2b_policies ADD CONSTRAINT account_b2b_policies_risk_behavior_check CHECK (risk_behavior IN ('block', 'warn'))");

        Schema::table('account_addresses', function (Blueprint $table): void {
            $table->char('public_id', 26)->nullable()->unique();
        });

        DB::table('account_addresses')
            ->whereNull('public_id')
            ->orderBy('id')
            ->select('id')
            ->chunkById(250, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('account_addresses')
                        ->where('id', $row->id)
                        ->update(['public_id' => (string) Str::ulid()]);
                }
            });

        DB::statement('ALTER TABLE account_addresses ALTER COLUMN public_id SET NOT NULL');
        DB::statement("ALTER TABLE account_addresses ADD CONSTRAINT account_addresses_public_id_shape_check CHECK (public_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')");
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_account_address_public_id_change() RETURNS trigger AS $$
BEGIN
    IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
        RAISE EXCEPTION 'account_addresses.public_id is immutable';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
        DB::statement('CREATE TRIGGER account_addresses_public_id_immutable BEFORE UPDATE ON account_addresses FOR EACH ROW EXECUTE FUNCTION prevent_account_address_public_id_change()');

        Schema::create('b2b_password_reset_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('b2b_user_id')->constrained('b2b_users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampsTz();
            $table->index(['company_id', 'b2b_user_id', 'expires_at']);
        });

        Schema::create('b2b_order_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('b2b_user_id')->constrained('b2b_users')->restrictOnDelete();
            $table->string('idempotency_key', 128);
            $table->char('payload_hash', 64);
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['company_id', 'b2b_user_id', 'idempotency_key'], 'b2b_order_submissions_scope_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_order_submissions');
        Schema::dropIfExists('b2b_password_reset_tokens');

        DB::statement('DROP TRIGGER IF EXISTS account_addresses_public_id_immutable ON account_addresses');
        DB::statement('DROP FUNCTION IF EXISTS prevent_account_address_public_id_change()');
        Schema::table('account_addresses', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });

        DB::statement('ALTER TABLE account_b2b_policies DROP CONSTRAINT IF EXISTS account_b2b_policies_risk_behavior_check');
        Schema::table('account_b2b_policies', function (Blueprint $table): void {
            $table->dropForeign(['default_warehouse_id']);
            $table->dropColumn(['show_price', 'show_balance', 'default_warehouse_id', 'risk_behavior']);
        });

        DB::statement('ALTER TABLE b2b_users DROP CONSTRAINT IF EXISTS b2b_users_role_check');
        DB::statement('ALTER TABLE b2b_users DROP CONSTRAINT IF EXISTS b2b_users_auth_version_positive_check');
        Schema::table('b2b_users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'permissions', 'auth_version', 'password_changed_at']);
        });
    }
};
