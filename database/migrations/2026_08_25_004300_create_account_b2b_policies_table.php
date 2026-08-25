<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_b2b_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id');
            $table->foreignId('account_id');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('allow_orders')->default(false);
            $table->boolean('show_stock')->default(false);
            $table->boolean('show_invoices')->default(false);
            $table->boolean('show_statement')->default(false);
            $table->boolean('allow_address_management')->default(false);
            $table->timestampsTz();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
            $table->unique(['company_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_b2b_policies');
    }
};
