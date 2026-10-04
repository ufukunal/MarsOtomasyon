<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->char('currency', 3)->default('TRY');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::connection('period')->create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('iban', 34)->nullable();
            $table->char('currency', 3)->default('TRY');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::connection('period')->create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->date('movement_date');
            $table->string('direction', 3);
            $table->decimal('amount', 18, 4);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
            $table->index(['cash_account_id', 'movement_date']);
            $table->unique('document_id');
        });

        Schema::connection('period')->create('bank_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->date('movement_date');
            $table->string('direction', 3);
            $table->decimal('amount', 18, 4);
            $table->string('reference', 80)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
            $table->index(['bank_account_id', 'movement_date']);
            $table->unique('document_id');
        });

        foreach (['cash_movements', 'bank_movements'] as $table) {
            DB::connection('period')->statement(
                "ALTER TABLE {$table} ADD CONSTRAINT {$table}_direction_valid CHECK (direction IN ('in','out'))"
            );
            DB::connection('period')->statement(
                "ALTER TABLE {$table} ADD CONSTRAINT {$table}_amount_positive CHECK (amount > 0)"
            );
        }
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('bank_movements');
        Schema::connection('period')->dropIfExists('cash_movements');
        Schema::connection('period')->dropIfExists('bank_accounts');
        Schema::connection('period')->dropIfExists('cash_accounts');
    }
};
