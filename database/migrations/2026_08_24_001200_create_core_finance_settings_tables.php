<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->char('code', 3)->primary();
            $table->string('name', 80);
            $table->unsignedSmallInteger('minor_unit')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::table('currencies')->insert([
            ['code' => 'TRY', 'name' => 'Türk Lirası', 'minor_unit' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'USD', 'name' => 'ABD Doları', 'minor_unit' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EUR', 'name' => 'Euro', 'minor_unit' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'GBP', 'name' => 'İngiliz Sterlini', 'minor_unit' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CNY', 'name' => 'Çin Yuanı', 'minor_unit' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'JPY', 'name' => 'Japon Yeni', 'minor_unit' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CHF', 'name' => 'İsviçre Frangı', 'minor_unit' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'AED', 'name' => 'BAE Dirhemi', 'minor_unit' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('taxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 120);
            $table->decimal('rate', 9, 6);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX taxes_company_code_lower_unique ON taxes (company_id, lower(code))');
        DB::statement('ALTER TABLE taxes ADD CONSTRAINT taxes_rate_check CHECK (rate >= 0 AND rate <= 100)');

        Schema::create('tax_zero_reasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX tax_zero_reasons_company_code_lower_unique ON tax_zero_reasons (company_id, lower(code))');

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->date('rate_date');
            $table->char('from_currency_code', 3);
            $table->char('to_currency_code', 3);
            $table->decimal('rate', 20, 10);
            $table->string('source', 32)->default('manual');
            $table->timestampsTz();

            $table->foreign('from_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('to_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['company_id', 'rate_date', 'from_currency_code', 'to_currency_code']);
        });

        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_positive_rate_check CHECK (rate > 0)');
        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_distinct_currency_check CHECK (from_currency_code <> to_currency_code)');

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('posting_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 120);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('open');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX posting_periods_company_code_lower_unique ON posting_periods (company_id, lower(code))');
        DB::statement("ALTER TABLE posting_periods ADD CONSTRAINT posting_periods_status_check CHECK (status IN ('open', 'closed'))");
        DB::statement('ALTER TABLE posting_periods ADD CONSTRAINT posting_periods_date_order_check CHECK (starts_on <= ends_on)');
        DB::statement("ALTER TABLE posting_periods ADD CONSTRAINT posting_periods_no_overlap EXCLUDE USING gist (company_id WITH =, daterange(starts_on, ends_on, '[]') WITH &&)");
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_periods');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('tax_zero_reasons');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('currencies');
    }
};
