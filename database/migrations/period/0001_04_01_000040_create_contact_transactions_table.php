<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('contact_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->string('transaction_type', 30);
            $table->string('direction', 6);
            $table->date('transaction_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('TRY');
            $table->foreignId('reversal_of_id')->nullable()->constrained('contact_transactions')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->unique('document_id');
            $table->index(['contact_id', 'transaction_date']);
            $table->index(['contact_id', 'due_date']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE contact_transactions ADD CONSTRAINT contact_transactions_direction_valid CHECK (direction IN ('debit','credit'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE contact_transactions ADD CONSTRAINT contact_transactions_amount_positive CHECK (amount > 0)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('contact_transactions');
    }
};
