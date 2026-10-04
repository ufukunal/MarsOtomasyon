<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('period')->statement('CREATE SEQUENCE IF NOT EXISTS contact_code_seq START 1');

        Schema::connection('period')->create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)
                ->default(DB::raw("'CR' || LPAD(nextval('contact_code_seq')::text, 7, '0')"))
                ->unique();
            $table->string('title');
            $table->string('type', 10)->default('legal');
            $table->string('tax_office')->nullable();
            $table->string('tax_number', 20)->nullable();
            $table->string('national_id', 11)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 60)->nullable();
            $table->string('district', 60)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('term_days')->nullable();
            $table->decimal('risk_limit', 18, 4)->default(0);
            $table->decimal('discount_rate', 7, 4)->default(0);
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->restrictOnDelete();
            $table->unsignedBigInteger('source_company_id')->nullable();
            $table->unsignedBigInteger('source_record_id')->nullable();
            $table->string('search_index')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index('title');
            $table->index('tax_number');
            $table->index('source_company_id');
        });

        DB::connection('period')->statement(
            "ALTER TABLE contacts ADD CONSTRAINT contacts_type_valid CHECK (type IN ('legal','real'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE contacts ADD CONSTRAINT contacts_risk_limit_nonnegative CHECK (risk_limit >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE contacts ADD CONSTRAINT contacts_discount_rate_valid CHECK (discount_rate BETWEEN 0 AND 100)'
        );
        DB::connection('period')->statement(
            'CREATE INDEX contacts_search_index_trgm ON contacts USING gin (search_index gin_trgm_ops)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('contacts');
        DB::connection('period')->statement('DROP SEQUENCE IF EXISTS contact_code_seq');
    }
};
