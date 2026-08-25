<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('quote_id');
            $table->unsignedInteger('revision_number');
            $table->string('quote_number', 64);
            $table->unsignedBigInteger('account_id');
            $table->string('account_code', 64);
            $table->string('account_name', 200);
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->char('currency_code', 3);
            $table->decimal('document_discount_rate', 9, 6);
            $table->decimal('base_net_total', 20, 6);
            $table->decimal('line_discount_total', 20, 6);
            $table->decimal('document_discount_total', 20, 6);
            $table->decimal('net_total', 20, 6);
            $table->decimal('tax_total', 20, 6);
            $table->decimal('gross_total', 20, 6);
            $table->text('note')->nullable();
            $table->char('content_fingerprint', 64);
            $table->timestampTz('created_at');

            $table->unique(['company_id', 'id'], 'quote_revisions_company_id_id_unique');
            $table->unique(['company_id', 'quote_id', 'revision_number'], 'quote_revisions_quote_revision_unique');
            $table->unique(['company_id', 'quote_id', 'content_fingerprint'], 'quote_revisions_quote_fingerprint_unique');
            $table->foreign(['company_id', 'quote_id'])
                ->references(['company_id', 'id'])->on('quotes')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['company_id', 'quote_id', 'revision_number'], 'quote_revisions_history_index');
        });

        DB::statement('ALTER TABLE quote_revisions ADD CONSTRAINT quote_revisions_revision_number_check CHECK (revision_number > 0)');
        DB::statement('ALTER TABLE quote_revisions ADD CONSTRAINT quote_revisions_quote_number_not_blank_check CHECK (char_length(btrim(quote_number)) > 0)');
        DB::statement('ALTER TABLE quote_revisions ADD CONSTRAINT quote_revisions_account_snapshot_not_blank_check CHECK (char_length(btrim(account_code)) > 0 AND char_length(btrim(account_name)) > 0)');
        DB::statement("ALTER TABLE quote_revisions ADD CONSTRAINT quote_revisions_fingerprint_check CHECK (content_fingerprint ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE quote_revisions ADD CONSTRAINT quote_revisions_date_order_check CHECK (valid_until IS NULL OR quote_date <= valid_until)');
        DB::statement('ALTER TABLE quote_revisions ADD CONSTRAINT quote_revisions_document_discount_rate_check CHECK (document_discount_rate >= 0 AND document_discount_rate <= 100)');
        DB::statement('ALTER TABLE quote_revisions ADD CONSTRAINT quote_revisions_totals_nonnegative_check CHECK (base_net_total >= 0 AND line_discount_total >= 0 AND document_discount_total >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE quote_revisions ADD CONSTRAINT quote_revisions_total_reconciliation_check CHECK (base_net_total - line_discount_total - document_discount_total = net_total AND net_total + tax_total = gross_total)');

        Schema::create('quote_revision_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('revision_id');
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('product_id');
            $table->string('product_code', 64);
            $table->string('product_name', 200);
            $table->string('description', 200);
            $table->decimal('quantity', 20, 6);
            $table->string('price_basis', 8);
            $table->decimal('unit_price', 20, 6);
            $table->decimal('line_discount_rate', 9, 6);
            $table->unsignedBigInteger('tax_id');
            $table->string('tax_code', 32);
            $table->decimal('tax_rate', 9, 6);
            $table->unsignedBigInteger('tax_zero_reason_id')->nullable();
            $table->string('tax_zero_reason_code', 32)->nullable();
            $table->decimal('base_net', 20, 6);
            $table->decimal('line_discount_net', 20, 6);
            $table->decimal('document_discount_net', 20, 6);
            $table->decimal('net_total', 20, 6);
            $table->decimal('tax_total', 20, 6);
            $table->decimal('gross_total', 20, 6);
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'revision_id'])
                ->references(['company_id', 'id'])->on('quote_revisions')->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_id'])
                ->references(['company_id', 'id'])->on('taxes')->restrictOnDelete();
            $table->foreign(['company_id', 'tax_zero_reason_id'])
                ->references(['company_id', 'id'])->on('tax_zero_reasons')->restrictOnDelete();
            $table->unique(['company_id', 'revision_id', 'position'], 'quote_revision_lines_position_unique');
        });

        DB::statement("ALTER TABLE quote_revision_lines ADD CONSTRAINT quote_revision_lines_price_basis_check CHECK (price_basis IN ('net', 'gross'))");
        DB::statement('ALTER TABLE quote_revision_lines ADD CONSTRAINT quote_revision_lines_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE quote_revision_lines ADD CONSTRAINT quote_revision_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE quote_revision_lines ADD CONSTRAINT quote_revision_lines_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE quote_revision_lines ADD CONSTRAINT quote_revision_lines_discount_rate_check CHECK (line_discount_rate >= 0 AND line_discount_rate <= 100)');
        DB::statement('ALTER TABLE quote_revision_lines ADD CONSTRAINT quote_revision_lines_tax_rate_check CHECK (tax_rate >= 0 AND tax_rate <= 100)');
        DB::statement('ALTER TABLE quote_revision_lines ADD CONSTRAINT quote_revision_lines_snapshot_not_blank_check CHECK (char_length(btrim(product_code)) > 0 AND char_length(btrim(product_name)) > 0 AND char_length(btrim(description)) > 0 AND char_length(btrim(tax_code)) > 0)');
        DB::statement('ALTER TABLE quote_revision_lines ADD CONSTRAINT quote_revision_lines_amounts_nonnegative_check CHECK (base_net >= 0 AND line_discount_net >= 0 AND document_discount_net >= 0 AND net_total >= 0 AND tax_total >= 0 AND gross_total >= 0)');
        DB::statement('ALTER TABLE quote_revision_lines ADD CONSTRAINT quote_revision_lines_total_reconciliation_check CHECK (base_net - line_discount_net - document_discount_net = net_total AND net_total + tax_total = gross_total)');
        DB::statement('ALTER TABLE quote_revision_lines ADD CONSTRAINT quote_revision_lines_zero_reason_shape_check CHECK ((tax_rate = 0 AND tax_zero_reason_id IS NOT NULL AND tax_zero_reason_code IS NOT NULL) OR (tax_rate > 0 AND tax_zero_reason_id IS NULL AND tax_zero_reason_code IS NULL))');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_prevent_quote_revision_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'quote revisions are immutable' USING ERRCODE = '55000';
END;
$$
SQL);
        DB::statement('CREATE TRIGGER quote_revisions_immutable BEFORE UPDATE OR DELETE ON quote_revisions FOR EACH ROW EXECUTE FUNCTION mars_prevent_quote_revision_mutation()');
        DB::statement('CREATE TRIGGER quote_revision_lines_immutable BEFORE UPDATE OR DELETE ON quote_revision_lines FOR EACH ROW EXECUTE FUNCTION mars_prevent_quote_revision_mutation()');
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_revision_lines');
        Schema::dropIfExists('quote_revisions');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_quote_revision_mutation()');
    }
};
