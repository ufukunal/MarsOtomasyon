<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('contact_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('source_contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('target_contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('source_transaction_id')->unique()->constrained('contact_transactions')->restrictOnDelete();
            $table->foreignId('target_transaction_id')->unique()->constrained('contact_transactions')->restrictOnDelete();
            $table->string('balance_type', 6);
            $table->date('transfer_date');
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('TRY');
            $table->text('reason')->nullable();
            $table->boolean('overdraft_approved')->default(false);
            $table->string('status', 20)->default('posted');
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('contact_transfers')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
            $table->index(['source_contact_id', 'transfer_date']);
            $table->index(['target_contact_id', 'transfer_date']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE contact_transfers ADD CONSTRAINT contact_transfers_distinct_contacts CHECK (source_contact_id <> target_contact_id)"
        );
        DB::connection('period')->statement(
            "ALTER TABLE contact_transfers ADD CONSTRAINT contact_transfers_balance_type_valid CHECK (balance_type IN ('debit','credit'))"
        );
        DB::connection('period')->statement(
            "ALTER TABLE contact_transfers ADD CONSTRAINT contact_transfers_amount_positive CHECK (amount > 0)"
        );
        DB::connection('period')->statement(
            "ALTER TABLE contact_transfers ADD CONSTRAINT contact_transfers_try_only CHECK (currency = 'TRY')"
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('contact_transfers');
    }
};
