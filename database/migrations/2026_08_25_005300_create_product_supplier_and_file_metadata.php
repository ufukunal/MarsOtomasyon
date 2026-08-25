<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->unique(['company_id', 'id'], 'accounts_company_id_id_unique');
        });

        Schema::table('attachments', function (Blueprint $table): void {
            $table->unique(['company_id', 'id'], 'attachments_company_id_id_unique');
        });

        Schema::create('product_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('account_id');
            $table->timestampsTz();

            $table->unique(['company_id', 'product_id', 'account_id'], 'product_suppliers_company_product_account_unique');
            $table->index(['company_id', 'account_id'], 'product_suppliers_company_account_index');

            $table->foreign(['company_id', 'product_id'], 'product_suppliers_product_company_fk')
                ->references(['company_id', 'id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->foreign(['company_id', 'account_id'], 'product_suppliers_account_company_fk')
                ->references(['company_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
        });

        Schema::create('product_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('attachment_id');
            $table->string('kind', 24);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->unique('attachment_id', 'product_files_attachment_unique');
            $table->index(['company_id', 'product_id', 'kind', 'position'], 'product_files_product_kind_position_index');

            $table->foreign(['company_id', 'product_id'], 'product_files_product_company_fk')
                ->references(['company_id', 'id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->foreign(['company_id', 'attachment_id'], 'product_files_attachment_company_fk')
                ->references(['company_id', 'id'])
                ->on('attachments')
                ->restrictOnDelete();
        });

        DB::statement("ALTER TABLE product_files ADD CONSTRAINT product_files_kind_check CHECK (kind IN ('technical', 'media'))");
        DB::statement('ALTER TABLE product_files ADD CONSTRAINT product_files_position_check CHECK (position >= 0 AND position <= 32767)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_files');
        Schema::dropIfExists('product_suppliers');

        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropUnique('attachments_company_id_id_unique');
        });
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropUnique('accounts_company_id_id_unique');
        });
    }
};
