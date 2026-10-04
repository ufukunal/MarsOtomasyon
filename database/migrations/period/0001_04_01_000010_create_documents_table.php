<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('documents', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 40);
            $table->string('number', 40)->nullable();
            $table->unsignedSmallInteger('revision_no')->default(0);
            $table->date('document_date');
            $table->date('due_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->char('currency', 3)->default('TRY');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->string('status', 30)->default('draft');
            $table->decimal('discount_rate', 7, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_base', 18, 4)->default(0);
            $table->decimal('vat_amount', 18, 4)->default(0);
            $table->decimal('rounding_difference', 18, 4)->default(0);
            $table->decimal('grand_total', 18, 4)->default(0);
            $table->jsonb('requirements_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->string('posted_by_name')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['document_type', 'number', 'revision_no'], 'documents_number_revision_unique');
            $table->index(['contact_id', 'document_date']);
            $table->index(['document_type', 'status', 'document_date']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE documents ADD CONSTRAINT documents_exchange_rate_positive CHECK (exchange_rate > 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE documents ADD CONSTRAINT documents_discount_rate_valid CHECK (discount_rate BETWEEN 0 AND 100)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE documents ADD CONSTRAINT documents_amounts_nonnegative CHECK (discount_amount >= 0 AND subtotal >= 0 AND tax_base >= 0 AND vat_amount >= 0 AND grand_total >= 0)'
        );
        DB::connection('period')->statement(
            "ALTER TABLE documents ADD CONSTRAINT documents_quote_revision_valid CHECK (document_type <> 'quote' OR revision_no >= 1)"
        );
        DB::connection('period')->statement(
            'ALTER TABLE documents ADD CONSTRAINT documents_total_invariant CHECK (abs(grand_total - (tax_base + vat_amount + rounding_difference)) < 0.0001)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('documents');
    }
};
