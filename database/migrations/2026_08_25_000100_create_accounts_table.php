<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! class_exists('CreateAccountsTable20260825000100', false)) {
    final class CreateAccountsTable20260825000100 extends Migration
    {
        public function up(): void
        {
            Schema::create('accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->string('code', 64);
                $table->string('type', 24);
                $table->string('status', 16)->default('active');
                $table->string('legal_name', 200);
                $table->string('trade_name', 200)->nullable();
                $table->string('tax_identity_type', 16)->default('none');
                $table->string('tax_number', 32)->nullable();
                $table->string('tax_office', 120)->nullable();
                $table->char('book_currency_code', 3);
                $table->unsignedSmallInteger('due_days')->default(0);
                $table->decimal('discount_rate', 9, 6)->default(0);
                $table->decimal('risk_limit', 20, 6)->default(0);
                $table->timestampsTz();

                $table->foreign('book_currency_code')->references('code')->on('currencies')->restrictOnDelete();
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'type']);
            });

            DB::statement('CREATE UNIQUE INDEX accounts_company_code_lower_unique ON accounts (company_id, lower(code))');
            DB::statement('CREATE UNIQUE INDEX accounts_company_tax_identity_unique ON accounts (company_id, tax_number) WHERE tax_number IS NOT NULL');
            DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_type_check CHECK (type IN ('customer', 'supplier', 'mixed', 'clearing'))");
            DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_status_check CHECK (status IN ('active', 'inactive'))");
            DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_tax_identity_type_check CHECK (tax_identity_type IN ('none', 'vkn', 'tckn', 'foreign'))");
            DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_tax_identity_presence_check CHECK ((tax_identity_type = 'none' AND tax_number IS NULL) OR (tax_identity_type <> 'none' AND tax_number IS NOT NULL))");
            DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_tax_number_shape_check CHECK ((tax_identity_type = 'none') OR (tax_identity_type = 'vkn' AND tax_number ~ '^\\d{10}$') OR (tax_identity_type = 'tckn' AND tax_number ~ '^\\d{11}$') OR (tax_identity_type = 'foreign' AND tax_number ~ '^[A-Z0-9][A-Z0-9 ._/-]{0,31}$'))");
            DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_code_not_blank_check CHECK (char_length(btrim(code)) > 0)');
            DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_legal_name_not_blank_check CHECK (char_length(btrim(legal_name)) > 0)');
            DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_due_days_check CHECK (due_days >= 0 AND due_days <= 3650)');
            DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_discount_rate_check CHECK (discount_rate >= 0 AND discount_rate <= 100)');
            DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_risk_limit_check CHECK (risk_limit >= 0)');
            DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_currency_check CHECK (book_currency_code ~ '^[A-Z]{3}$')");
        }

        public function down(): void
        {
            Schema::dropIfExists('accounts');
        }
    }
}

return new CreateAccountsTable20260825000100;
