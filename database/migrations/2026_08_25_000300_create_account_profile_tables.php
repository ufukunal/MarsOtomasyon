<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! class_exists('CreateAccountProfileTables20260825000300', false)) {
    final class CreateAccountProfileTables20260825000300 extends Migration
    {
        public function up(): void
        {
            Schema::table('accounts', function (Blueprint $table): void {
                $table->unique(['company_id', 'id'], 'accounts_company_id_id_unique');
            });

            Schema::create('account_contacts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('account_id');
                $table->string('kind', 16);
                $table->string('label', 80)->nullable();
                $table->string('value', 200);
                $table->string('normalized_value', 200);
                $table->boolean('is_primary')->default(false);
                $table->timestampsTz();

                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
                $table->foreign(['company_id', 'account_id'])
                    ->references(['company_id', 'id'])
                    ->on('accounts')
                    ->restrictOnDelete();
                $table->unique(['company_id', 'account_id', 'kind', 'normalized_value'], 'account_contacts_unique_value');
                $table->index(['company_id', 'account_id']);
            });

            Schema::create('account_authorized_contacts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('account_id');
                $table->string('name', 160);
                $table->string('title', 120)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('email', 200)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->string('note', 500)->nullable();
                $table->timestampsTz();

                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
                $table->foreign(['company_id', 'account_id'])
                    ->references(['company_id', 'id'])
                    ->on('accounts')
                    ->restrictOnDelete();
                $table->index(['company_id', 'account_id']);
            });

            Schema::create('account_addresses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('account_id');
                $table->string('type', 16);
                $table->string('label', 80);
                $table->string('recipient_name', 200)->nullable();
                $table->string('line1', 240);
                $table->string('line2', 240)->nullable();
                $table->string('district', 120)->nullable();
                $table->string('city', 120);
                $table->string('postal_code', 20)->nullable();
                $table->char('country_code', 2)->default('TR');
                $table->boolean('is_default')->default(false);
                $table->timestampsTz();

                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
                $table->foreign(['company_id', 'account_id'])
                    ->references(['company_id', 'id'])
                    ->on('accounts')
                    ->restrictOnDelete();
                $table->index(['company_id', 'account_id', 'type']);
            });

            Schema::create('account_shipping_preferences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('account_id');
                $table->string('company_name', 200);
                $table->string('city', 120);
                $table->string('branch', 120)->nullable();
                $table->string('contact_name', 160)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('preference', 120)->nullable();
                $table->string('address', 500)->nullable();
                $table->string('note', 1000)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestampsTz();

                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
                $table->foreign(['company_id', 'account_id'])
                    ->references(['company_id', 'id'])
                    ->on('accounts')
                    ->restrictOnDelete();
                $table->index(['company_id', 'account_id']);
            });

            DB::statement("ALTER TABLE account_contacts ADD CONSTRAINT account_contacts_kind_check CHECK (kind IN ('phone', 'email'))");
            DB::statement('ALTER TABLE account_contacts ADD CONSTRAINT account_contacts_value_not_blank_check CHECK (char_length(btrim(value)) > 0 AND char_length(btrim(normalized_value)) > 0)');
            DB::statement('CREATE UNIQUE INDEX account_contacts_one_primary_per_kind ON account_contacts (company_id, account_id, kind) WHERE is_primary');

            DB::statement('ALTER TABLE account_authorized_contacts ADD CONSTRAINT account_authorized_contacts_name_not_blank_check CHECK (char_length(btrim(name)) > 0)');
            DB::statement("ALTER TABLE account_authorized_contacts ADD CONSTRAINT account_authorized_contacts_channel_check CHECK (phone IS NOT NULL OR email IS NOT NULL)");
            DB::statement('CREATE UNIQUE INDEX account_authorized_contacts_one_primary ON account_authorized_contacts (company_id, account_id) WHERE is_primary');

            DB::statement("ALTER TABLE account_addresses ADD CONSTRAINT account_addresses_type_check CHECK (type IN ('billing', 'shipping'))");
            DB::statement('ALTER TABLE account_addresses ADD CONSTRAINT account_addresses_label_not_blank_check CHECK (char_length(btrim(label)) > 0)');
            DB::statement('ALTER TABLE account_addresses ADD CONSTRAINT account_addresses_line1_not_blank_check CHECK (char_length(btrim(line1)) > 0)');
            DB::statement('ALTER TABLE account_addresses ADD CONSTRAINT account_addresses_city_not_blank_check CHECK (char_length(btrim(city)) > 0)');
            DB::statement("ALTER TABLE account_addresses ADD CONSTRAINT account_addresses_country_code_check CHECK (country_code ~ '^[A-Z]{2}$')");
            DB::statement('CREATE UNIQUE INDEX account_addresses_one_default_per_type ON account_addresses (company_id, account_id, type) WHERE is_default');

            DB::statement('ALTER TABLE account_shipping_preferences ADD CONSTRAINT account_shipping_preferences_company_not_blank_check CHECK (char_length(btrim(company_name)) > 0)');
            DB::statement('ALTER TABLE account_shipping_preferences ADD CONSTRAINT account_shipping_preferences_city_not_blank_check CHECK (char_length(btrim(city)) > 0)');
            DB::statement('CREATE UNIQUE INDEX account_shipping_preferences_one_default ON account_shipping_preferences (company_id, account_id) WHERE is_default');
        }

        public function down(): void
        {
            Schema::dropIfExists('account_shipping_preferences');
            Schema::dropIfExists('account_addresses');
            Schema::dropIfExists('account_authorized_contacts');
            Schema::dropIfExists('account_contacts');

            Schema::table('accounts', function (Blueprint $table): void {
                $table->dropUnique('accounts_company_id_id_unique');
            });
        }
    }
}

return new CreateAccountProfileTables20260825000300;
