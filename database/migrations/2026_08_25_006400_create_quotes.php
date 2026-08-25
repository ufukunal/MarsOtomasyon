<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_zero_reasons', function (Blueprint $table): void {
            $table->unique(['company_id', 'id'], 'tax_zero_reasons_company_id_id_unique');
        });

        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->string('number', 64);
            $table->string('series_code', 64);
            $table->unsignedBigInteger('sequence_value');
            $table->string('status', 16)->default('draft');
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->char('currency_code', 3);
            $table->decimal('document_discount_rate', 9, 6)->default(0);
            $table->decimal('base_net_total', 20, 6)->default(0);
            $table->decimal('line_discount_total', 20, 6)->default(0);
            $table->decimal('document_discount_total', 20, 6)->default(0);
            $table->decimal('net_total', 20, 6)->default(0);
            $table->decimal('tax_total', 20, 6)->default(0);
            $table->decimal('gross_total', 20, 6)->default(0);
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'quotes_company_id_id_unique');
            $table->unique(['company_id', 'number'], 'quotes_company_number_unique');
            $table->unique(['company_id', 'series_code', 'sequence_value'], 'quotes_company_series_sequence_unique');
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['company_id', 'status', 'quote_date'], 'quotes_company_status_date_index');
            $table->index(['company_id', 'account_id'], 'quotes_company_account_index');
        });

        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_status_check CHECK (status IN ('draft', 'cancelled'))");
        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_series_code_canonical_check CHECK (series_code = lower(btrim(series_code)) AND series_code ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE quotes ADD CONSTRAINT quotes_date_order_check CHECK (valid_until IS NULL OR quote_date <= valid_until)');
        DB::statement('ALTER TABLE quotes ADD CONSTRAINT quotes_document_discount_rate_check CHECK (document_discount_rate >= 0 AND document_discount_rate <= 100)');
        DB::statement('ALTER TABLE quotes ADD CONSTRAINT quotes_totals_nonnegative_check CHECK (base_net_total >= 0 AND line_discount_total >= 0 AND document_discount_total >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE quotes ADD CONSTRAINT quotes_total_reconciliation_check CHECK (base_net_total - line_discount_total - document_discount_total = net_total AND net_total + tax_total = gross_total)');

        Schema::create('quote_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('quote_id');
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('product_id');
            $table->string('product_code', 64);
            $table->string('description', 200);
            $table->decimal('quantity', 20, 6);
            $table->string('price_basis', 8);
            $table->decimal('unit_price', 20, 6);
            $table->decimal('line_discount_rate', 9, 6)->default(0);
            $table->unsignedBigInteger('tax_id');
            $table->decimal('tax_rate', 9, 6);
            $table->unsignedBigInteger('tax_zero_reason_id')->nullable();
            $table->string('tax_zero_reason_code', 32)->nullable();
            $table->decimal('base_net', 20, 6);
            $table->decimal('line_discount_net', 20, 6);
            $table->decimal('document_discount_net', 20, 6);
            $table->decimal('net_total', 20, 6);
            $table->decimal('tax_total', 20, 6);
            $table->decimal('gross_total', 20, 6);
            $table->timestampsTz();

            $table->foreign(['company_id', 'quote_id'])
                ->references(['company_id', 'id'])->on('quotes')->cascadeOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_id'])
                ->references(['company_id', 'id'])->on('taxes')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_zero_reason_id'])
                ->references(['company_id', 'id'])->on('tax_zero_reasons')->restrictOnDelete();
            $table->unique(['company_id', 'quote_id', 'position'], 'quote_lines_quote_position_unique');
            $table->index(['company_id', 'product_id'], 'quote_lines_company_product_index');
        });

        DB::statement("ALTER TABLE quote_lines ADD CONSTRAINT quote_lines_price_basis_check CHECK (price_basis IN ('net', 'gross'))");
        DB::statement('ALTER TABLE quote_lines ADD CONSTRAINT quote_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE quote_lines ADD CONSTRAINT quote_lines_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE quote_lines ADD CONSTRAINT quote_lines_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE quote_lines ADD CONSTRAINT quote_lines_discount_rate_check CHECK (line_discount_rate >= 0 AND line_discount_rate <= 100)');
        DB::statement('ALTER TABLE quote_lines ADD CONSTRAINT quote_lines_tax_rate_check CHECK (tax_rate >= 0 AND tax_rate <= 100)');
        DB::statement('ALTER TABLE quote_lines ADD CONSTRAINT quote_lines_amounts_nonnegative_check CHECK (base_net >= 0 AND line_discount_net >= 0 AND document_discount_net >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE quote_lines ADD CONSTRAINT quote_lines_total_reconciliation_check CHECK (base_net - line_discount_net - document_discount_net = net_total AND net_total + tax_total = gross_total)');
        DB::statement("ALTER TABLE quote_lines ADD CONSTRAINT quote_lines_zero_reason_shape_check CHECK ((tax_rate = 0 AND tax_zero_reason_id IS NOT NULL AND tax_zero_reason_code IS NOT NULL) OR (tax_rate > 0 AND tax_zero_reason_id IS NULL AND tax_zero_reason_code IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
        Schema::dropIfExists('quotes');

        Schema::table('tax_zero_reasons', function (Blueprint $table): void {
            $table->dropUnique('tax_zero_reasons_company_id_id_unique');
        });
    }
};
