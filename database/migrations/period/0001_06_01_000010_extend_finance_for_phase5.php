<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->table('cash_movements', function (Blueprint $table): void {
            $table->string('movement_type', 40)->default('legacy');
            $table->string('reference', 100)->nullable();
            $table->string('group_key', 64)->nullable();
            $table->foreignId('reversal_of_id')->nullable()
                ->constrained('cash_movements')->restrictOnDelete();
            $table->jsonb('metadata')->nullable();

            $table->index(['group_key', 'movement_type']);
        });

        Schema::connection('period')->table('bank_movements', function (Blueprint $table): void {
            $table->string('movement_type', 40)->default('legacy');
            $table->string('origin', 20)->default('book');
            $table->string('group_key', 64)->nullable();
            $table->foreignId('reversal_of_id')->nullable()
                ->constrained('bank_movements')->restrictOnDelete();
            $table->string('statement_fingerprint', 64)->nullable();
            $table->text('statement_description')->nullable();
            $table->decimal('statement_balance', 18, 4)->nullable();
            $table->foreignId('reconciled_movement_id')->nullable()
                ->constrained('bank_movements')->restrictOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedBigInteger('reconciled_by')->nullable();
            $table->string('reconciled_by_name')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->unique(['bank_account_id', 'statement_fingerprint'], 'bank_statement_fingerprint_unique');
            $table->index(['origin', 'movement_date']);
            $table->index(['group_key', 'movement_type']);
        });

        Schema::connection('period')->create('security_payrolls', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('action', 30);
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->date('payroll_date');
            $table->char('currency', 3)->default('TRY');
            $table->decimal('total_amount', 18, 4);
            $table->jsonb('security_ids');
            $table->string('status', 30)->default('posted');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['action', 'payroll_date']);
            $table->index(['contact_id', 'payroll_date']);
        });

        Schema::connection('period')->create('securities', function (Blueprint $table): void {
            $table->id();
            $table->string('direction', 10);
            $table->string('kind', 10);
            $table->string('instrument_no', 80);
            $table->string('fingerprint', 64)->unique();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->foreignId('endorsed_to_contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('last_payroll_id')->nullable()->constrained('security_payrolls')->restrictOnDelete();
            $table->string('bank_name')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date');
            $table->char('currency', 3)->default('TRY');
            $table->decimal('amount', 18, 4);
            $table->string('status', 30);
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['direction', 'kind', 'status', 'due_date']);
            $table->index(['contact_id', 'status']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE bank_movements ADD CONSTRAINT bank_movements_origin_valid CHECK (origin IN ('book', 'statement'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE security_payrolls ADD CONSTRAINT security_payrolls_total_positive CHECK (total_amount > 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE securities ADD CONSTRAINT securities_amount_positive CHECK (amount > 0)'
        );
        DB::connection('period')->statement(
            "ALTER TABLE securities ADD CONSTRAINT securities_direction_valid CHECK (direction IN ('incoming', 'outgoing'))"
        );
        DB::connection('period')->statement(
            "ALTER TABLE securities ADD CONSTRAINT securities_kind_valid CHECK (kind IN ('check', 'note'))"
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('securities');
        Schema::connection('period')->dropIfExists('security_payrolls');

        Schema::connection('period')->table('bank_movements', function (Blueprint $table): void {
            $table->dropUnique('bank_statement_fingerprint_unique');
            $table->dropForeign(['reconciled_movement_id']);
            $table->dropForeign(['reversal_of_id']);
            $table->dropColumn([
                'movement_type', 'origin', 'group_key', 'reversal_of_id',
                'statement_fingerprint', 'statement_description', 'statement_balance',
                'reconciled_movement_id', 'reconciled_at', 'reconciled_by',
                'reconciled_by_name', 'imported_at', 'metadata',
            ]);
        });

        Schema::connection('period')->table('cash_movements', function (Blueprint $table): void {
            $table->dropForeign(['reversal_of_id']);
            $table->dropColumn([
                'movement_type', 'reference', 'group_key', 'reversal_of_id', 'metadata',
            ]);
        });
    }
};
