<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insert([
            [
                'key' => 'treasury.view',
                'name' => 'Kasa / banka görüntüleme',
                'description' => 'Treasury hesap, hareket, tahsilat/ödeme ve ekstrelerini görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'treasury.manage',
                'name' => 'Kasa / banka yönetimi',
                'description' => 'Tahsilat, ödeme, POS, virman, masraf, manuel hareket ve kasa sayımı yönetimi yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'treasury.reconcile',
                'name' => 'Banka mutabakatı',
                'description' => 'Banka ekstresi içe aktarma ve treasury hareketleriyle eşleştirme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('treasury_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->string('code', 64);
            $table->string('name', 160);
            $table->char('currency_code', 3);
            $table->boolean('is_active')->default(true);
            $table->string('bank_name', 160)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('account_number', 80)->nullable();
            $table->string('pos_provider', 120)->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'treasury_accounts_company_id_id_unique');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['company_id', 'type', 'is_active'], 'treasury_accounts_company_type_active_index');
        });
        DB::statement('CREATE UNIQUE INDEX treasury_accounts_company_code_lower_unique ON treasury_accounts (company_id, lower(code))');
        DB::statement("ALTER TABLE treasury_accounts ADD CONSTRAINT treasury_accounts_type_check CHECK (type IN ('cash','bank','pos'))");
        DB::statement("ALTER TABLE treasury_accounts ADD CONSTRAINT treasury_accounts_code_check CHECK (code = btrim(code) AND code ~ '^[A-Za-z0-9]+(?:[._-][A-Za-z0-9]+)*$')");
        DB::statement("ALTER TABLE treasury_accounts ADD CONSTRAINT treasury_accounts_currency_check CHECK (currency_code ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE treasury_accounts ADD CONSTRAINT treasury_accounts_shape_check CHECK ((type = 'cash' AND bank_name IS NULL AND iban IS NULL AND account_number IS NULL AND pos_provider IS NULL) OR (type = 'bank' AND pos_provider IS NULL) OR (type = 'pos' AND iban IS NULL))");

        Schema::create('treasury_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->string('kind', 24);
            $table->unsignedBigInteger('treasury_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'treasury_payment_methods_company_id_id_unique');
            $table->foreign(['company_id', 'treasury_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX treasury_payment_methods_company_code_lower_unique ON treasury_payment_methods (company_id, lower(code))');
        DB::statement("ALTER TABLE treasury_payment_methods ADD CONSTRAINT treasury_payment_methods_kind_check CHECK (kind IN ('cash','bank','pos','virtual_pos','cheque','promissory_note','other'))");
        DB::statement("ALTER TABLE treasury_payment_methods ADD CONSTRAINT treasury_payment_methods_code_check CHECK (code = btrim(code) AND code ~ '^[A-Za-z0-9]+(?:[._-][A-Za-z0-9]+)*$')");

        Schema::create('treasury_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('treasury_account_id');
            $table->decimal('balance', 20, 6)->default(0);
            $table->timestampTz('updated_at');

            $table->unique(['company_id', 'treasury_account_id'], 'treasury_balances_account_unique');
            $table->foreign(['company_id', 'treasury_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
        });

        Schema::create('treasury_movements', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('treasury_account_id');
            $table->foreignId('posting_period_id')->constrained('posting_periods')->restrictOnDelete();
            $table->date('posting_date');
            $table->char('currency_code', 3);
            $table->decimal('signed_amount', 20, 6);
            $table->string('movement_type', 40);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->string('source_type', 100);
            $table->string('source_id', 255);
            $table->string('effect_type', 100);
            $table->char('effect_fingerprint', 64);
            $table->string('memo', 500)->nullable();
            $table->unsignedBigInteger('reversal_of_movement_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'treasury_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'payment_method_id'])
                ->references(['company_id', 'id'])->on('treasury_payment_methods')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('reversal_of_movement_id')->references('id')->on('treasury_movements')->restrictOnDelete();
            $table->unique('effect_fingerprint', 'treasury_movements_effect_fingerprint_unique');
            $table->unique(['company_id', 'source_type', 'source_id', 'effect_type'], 'treasury_movements_source_effect_unique');
            $table->unique('reversal_of_movement_id', 'treasury_movements_one_reversal_unique');
            $table->index(['company_id', 'treasury_account_id', 'posting_date', 'id'], 'treasury_movements_statement_index');
        });
        DB::statement('ALTER TABLE treasury_movements ADD CONSTRAINT treasury_movements_amount_check CHECK (signed_amount <> 0)');
        DB::statement("ALTER TABLE treasury_movements ADD CONSTRAINT treasury_movements_currency_check CHECK (currency_code ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE treasury_movements ADD CONSTRAINT treasury_movements_source_type_check CHECK (source_type ~ '^[a-z0-9]+([._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE treasury_movements ADD CONSTRAINT treasury_movements_effect_type_check CHECK (effect_type ~ '^treasury[.][a-z0-9]+([._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE treasury_movements ADD CONSTRAINT treasury_movements_source_id_check CHECK (char_length(source_id) > 0 AND source_id = btrim(source_id))');
        DB::statement("ALTER TABLE treasury_movements ADD CONSTRAINT treasury_movements_fingerprint_check CHECK (effect_fingerprint ~ '^[a-f0-9]{64}$')");
        DB::statement("ALTER TABLE treasury_movements ADD CONSTRAINT treasury_movements_type_check CHECK (movement_type IN ('collection','payment','cash_in','cash_out','bank_in','bank_out','expense','transfer_out','transfer_in','pos_pending','pos_settlement_out','pos_settlement_in','pos_reversal','pos_chargeback','cash_count_adjustment'))");

        Schema::create('treasury_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('treasury_account_id');
            $table->unsignedBigInteger('payment_method_id');
            $table->string('direction', 16);
            $table->string('payment_kind', 24);
            $table->string('status', 16)->default('draft');
            $table->string('pos_status', 16)->nullable();
            $table->date('payment_date');
            $table->char('currency_code', 3);
            $table->decimal('amount', 20, 6);
            $table->string('reference', 120)->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'treasury_payments_company_id_id_unique');
            $table->foreign(['company_id', 'account_id'])->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'treasury_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'payment_method_id'])
                ->references(['company_id', 'id'])->on('treasury_payment_methods')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['company_id', 'payment_date', 'status'], 'treasury_payments_company_date_status_index');
        });
        DB::statement("ALTER TABLE treasury_payments ADD CONSTRAINT treasury_payments_direction_check CHECK (direction IN ('collection','payment'))");
        DB::statement("ALTER TABLE treasury_payments ADD CONSTRAINT treasury_payments_kind_check CHECK (payment_kind IN ('cash','bank','pos','virtual_pos','cheque','promissory_note','other'))");
        DB::statement("ALTER TABLE treasury_payments ADD CONSTRAINT treasury_payments_status_check CHECK (status IN ('draft','finalized','reversed'))");
        DB::statement("ALTER TABLE treasury_payments ADD CONSTRAINT treasury_payments_pos_status_check CHECK (pos_status IS NULL OR pos_status IN ('pending','settled','reversed','chargeback'))");
        DB::statement('ALTER TABLE treasury_payments ADD CONSTRAINT treasury_payments_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE treasury_payments ADD CONSTRAINT treasury_payments_lifecycle_check CHECK ((status = 'draft' AND finalized_at IS NULL AND reversed_at IS NULL) OR (status = 'finalized' AND finalized_at IS NOT NULL AND reversed_at IS NULL) OR (status = 'reversed' AND finalized_at IS NOT NULL AND reversed_at IS NOT NULL))");
        DB::statement("ALTER TABLE treasury_payments ADD CONSTRAINT treasury_payments_pos_shape_check CHECK ((payment_kind IN ('pos','virtual_pos') AND direction = 'collection' AND pos_status IS NOT NULL) OR (payment_kind NOT IN ('pos','virtual_pos') AND pos_status IS NULL))");

        Schema::create('treasury_pos_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('treasury_payment_id');
            $table->unsignedBigInteger('source_pos_account_id');
            $table->unsignedBigInteger('bank_account_id');
            $table->date('settlement_date');
            $table->char('currency_code', 3);
            $table->decimal('gross_amount', 20, 6);
            $table->decimal('commission_amount', 20, 6)->default(0);
            $table->decimal('net_amount', 20, 6);
            $table->timestampTz('created_at');

            $table->unique(['company_id', 'treasury_payment_id'], 'treasury_pos_settlements_payment_unique');
            $table->foreign(['company_id', 'treasury_payment_id'])
                ->references(['company_id', 'id'])->on('treasury_payments')->restrictOnDelete();
            $table->foreign(['company_id', 'source_pos_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'bank_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE treasury_pos_settlements ADD CONSTRAINT treasury_pos_settlements_amount_check CHECK (gross_amount > 0 AND commission_amount >= 0 AND commission_amount < gross_amount AND gross_amount - commission_amount = net_amount)');

        Schema::create('treasury_manual_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('treasury_account_id');
            $table->string('operation', 16);
            $table->string('status', 16)->default('draft');
            $table->date('movement_date');
            $table->char('currency_code', 3);
            $table->decimal('amount', 20, 6);
            $table->text('note')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'treasury_manual_movements_company_id_id_unique');
            $table->foreign(['company_id', 'treasury_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE treasury_manual_movements ADD CONSTRAINT treasury_manual_movements_operation_check CHECK (operation IN ('cash_in','cash_out','bank_in','bank_out'))");
        DB::statement("ALTER TABLE treasury_manual_movements ADD CONSTRAINT treasury_manual_movements_status_check CHECK (status IN ('draft','finalized'))");
        DB::statement('ALTER TABLE treasury_manual_movements ADD CONSTRAINT treasury_manual_movements_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE treasury_manual_movements ADD CONSTRAINT treasury_manual_movements_lifecycle_check CHECK ((status = 'draft' AND finalized_at IS NULL) OR (status = 'finalized' AND finalized_at IS NOT NULL))");

        Schema::create('treasury_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('from_account_id');
            $table->unsignedBigInteger('to_account_id');
            $table->string('status', 16)->default('draft');
            $table->date('transfer_date');
            $table->char('currency_code', 3);
            $table->decimal('amount', 20, 6);
            $table->text('note')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'treasury_transfers_company_id_id_unique');
            $table->foreign(['company_id', 'from_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'to_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE treasury_transfers ADD CONSTRAINT treasury_transfers_status_check CHECK (status IN ('draft','finalized'))");
        DB::statement('ALTER TABLE treasury_transfers ADD CONSTRAINT treasury_transfers_amount_check CHECK (amount > 0 AND from_account_id <> to_account_id)');
        DB::statement("ALTER TABLE treasury_transfers ADD CONSTRAINT treasury_transfers_lifecycle_check CHECK ((status = 'draft' AND finalized_at IS NULL) OR (status = 'finalized' AND finalized_at IS NOT NULL))");

        Schema::create('treasury_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('treasury_account_id');
            $table->string('status', 16)->default('draft');
            $table->date('expense_date');
            $table->char('currency_code', 3);
            $table->decimal('amount', 20, 6);
            $table->string('category', 120);
            $table->text('note')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'treasury_expenses_company_id_id_unique');
            $table->foreign(['company_id', 'treasury_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE treasury_expenses ADD CONSTRAINT treasury_expenses_status_check CHECK (status IN ('draft','finalized'))");
        DB::statement('ALTER TABLE treasury_expenses ADD CONSTRAINT treasury_expenses_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE treasury_expenses ADD CONSTRAINT treasury_expenses_lifecycle_check CHECK ((status = 'draft' AND finalized_at IS NULL) OR (status = 'finalized' AND finalized_at IS NOT NULL))");

        Schema::create('treasury_cash_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('treasury_account_id');
            $table->string('status', 16)->default('draft');
            $table->date('count_date');
            $table->char('currency_code', 3);
            $table->decimal('ledger_balance', 20, 6)->nullable();
            $table->decimal('counted_total', 20, 6)->nullable();
            $table->decimal('variance', 20, 6)->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'treasury_cash_counts_company_id_id_unique');
            $table->foreign(['company_id', 'treasury_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE treasury_cash_counts ADD CONSTRAINT treasury_cash_counts_status_check CHECK (status IN ('draft','finalized'))");
        DB::statement("ALTER TABLE treasury_cash_counts ADD CONSTRAINT treasury_cash_counts_lifecycle_check CHECK ((status = 'draft' AND finalized_at IS NULL AND ledger_balance IS NULL AND counted_total IS NULL AND variance IS NULL) OR (status = 'finalized' AND finalized_at IS NOT NULL AND ledger_balance IS NOT NULL AND counted_total IS NOT NULL AND variance IS NOT NULL AND counted_total - ledger_balance = variance))");

        Schema::create('treasury_cash_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('treasury_cash_count_id');
            $table->decimal('denomination', 20, 6);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 20, 6);
            $table->timestampsTz();

            $table->foreign(['company_id', 'treasury_cash_count_id'])
                ->references(['company_id', 'id'])->on('treasury_cash_counts')->cascadeOnDelete();
            $table->unique(['company_id', 'treasury_cash_count_id', 'denomination'], 'treasury_cash_count_lines_denomination_unique');
        });
        DB::statement('ALTER TABLE treasury_cash_count_lines ADD CONSTRAINT treasury_cash_count_lines_values_check CHECK (denomination > 0 AND denomination * quantity = line_total)');

        Schema::create('bank_statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('treasury_account_id');
            $table->string('format', 16);
            $table->string('file_name', 255);
            $table->char('file_hash', 64);
            $table->unsignedInteger('line_count')->default(0);
            $table->timestampTz('created_at');

            $table->unique(['company_id', 'id'], 'bank_statement_imports_company_id_id_unique');
            $table->unique(['company_id', 'treasury_account_id', 'file_hash'], 'bank_statement_imports_file_unique');
            $table->foreign(['company_id', 'treasury_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE bank_statement_imports ADD CONSTRAINT bank_statement_imports_format_check CHECK (format IN ('csv','xlsx','mt940'))");
        DB::statement("ALTER TABLE bank_statement_imports ADD CONSTRAINT bank_statement_imports_hash_check CHECK (file_hash ~ '^[a-f0-9]{64}$')");

        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('bank_statement_import_id');
            $table->unsignedBigInteger('treasury_account_id');
            $table->string('external_key', 160);
            $table->date('booking_date');
            $table->date('value_date')->nullable();
            $table->char('currency_code', 3);
            $table->decimal('signed_amount', 20, 6);
            $table->string('reference', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('match_status', 16)->default('unmatched');
            $table->unsignedBigInteger('matched_treasury_movement_id')->nullable();
            $table->timestampTz('matched_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'bank_statement_import_id'])
                ->references(['company_id', 'id'])->on('bank_statement_imports')->cascadeOnDelete();
            $table->foreign(['company_id', 'treasury_account_id'])
                ->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign('matched_treasury_movement_id')->references('id')->on('treasury_movements')->restrictOnDelete();
            $table->unique(['company_id', 'treasury_account_id', 'external_key'], 'bank_statement_lines_external_unique');
            $table->unique('matched_treasury_movement_id', 'bank_statement_lines_movement_unique');
            $table->index(['company_id', 'treasury_account_id', 'match_status', 'booking_date'], 'bank_statement_lines_match_index');
        });
        DB::statement('ALTER TABLE bank_statement_lines ADD CONSTRAINT bank_statement_lines_amount_check CHECK (signed_amount <> 0)');
        DB::statement("ALTER TABLE bank_statement_lines ADD CONSTRAINT bank_statement_lines_status_check CHECK (match_status IN ('unmatched','matched','ignored'))");
        DB::statement("ALTER TABLE bank_statement_lines ADD CONSTRAINT bank_statement_lines_match_shape_check CHECK ((match_status = 'matched' AND matched_treasury_movement_id IS NOT NULL AND matched_at IS NOT NULL) OR (match_status <> 'matched' AND matched_treasury_movement_id IS NULL AND matched_at IS NULL))");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_treasury_account_currency_after_activity()
RETURNS trigger AS $$
BEGIN
    IF NEW.currency_code IS DISTINCT FROM OLD.currency_code
       AND EXISTS (
           SELECT 1 FROM treasury_movements
           WHERE company_id = OLD.company_id AND treasury_account_id = OLD.id
       ) THEN
        RAISE EXCEPTION 'Treasury account currency cannot change after ledger activity' USING ERRCODE = '55000';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_accounts_currency_guard
BEFORE UPDATE ON treasury_accounts
FOR EACH ROW EXECUTE FUNCTION mars_guard_treasury_account_currency_after_activity();

CREATE OR REPLACE FUNCTION mars_guard_treasury_balance_mutation()
RETURNS trigger AS $$
BEGIN
    IF current_setting('mars.treasury_projection', true) IS DISTINCT FROM '1' THEN
        RAISE EXCEPTION 'treasury_balances are projection-only' USING ERRCODE = '55000';
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_balances_projection_only_guard
BEFORE INSERT OR UPDATE OR DELETE ON treasury_balances
FOR EACH ROW EXECUTE FUNCTION mars_guard_treasury_balance_mutation();

CREATE OR REPLACE FUNCTION mars_guard_treasury_movement_insert()
RETURNS trigger AS $$
DECLARE
    account_currency char(3);
    account_active boolean;
    original_record treasury_movements%ROWTYPE;
BEGIN
    SELECT ta.currency_code, ta.is_active
      INTO account_currency, account_active
      FROM treasury_accounts AS ta
     WHERE ta.company_id = NEW.company_id
       AND ta.id = NEW.treasury_account_id
     FOR UPDATE;

    IF account_currency IS NULL THEN
        RAISE EXCEPTION 'Treasury movement target does not belong to company' USING ERRCODE = '23503';
    END IF;
    IF NEW.reversal_of_movement_id IS NULL AND account_active IS NOT TRUE THEN
        RAISE EXCEPTION 'New treasury effect requires active treasury account' USING ERRCODE = '23514';
    END IF;
    IF account_currency <> NEW.currency_code THEN
        RAISE EXCEPTION 'Treasury movement currency must equal treasury account currency' USING ERRCODE = '23514';
    END IF;
    IF NOT EXISTS (
        SELECT 1
          FROM posting_periods AS pp
         WHERE pp.id = NEW.posting_period_id
           AND pp.company_id = NEW.company_id
           AND pp.status = 'open'
           AND NEW.posting_date BETWEEN pp.starts_on AND pp.ends_on
    ) THEN
        RAISE EXCEPTION 'Treasury movement requires an open matching posting period' USING ERRCODE = '23514';
    END IF;

    IF NEW.reversal_of_movement_id IS NOT NULL THEN
        SELECT * INTO original_record
          FROM treasury_movements
         WHERE id = NEW.reversal_of_movement_id
         FOR SHARE;
        IF NOT FOUND THEN
            RAISE EXCEPTION 'Treasury reversal target does not exist' USING ERRCODE = '23503';
        END IF;
        IF original_record.reversal_of_movement_id IS NOT NULL THEN
            RAISE EXCEPTION 'Treasury reversal cannot itself be reversed' USING ERRCODE = '23514';
        END IF;
        IF original_record.company_id <> NEW.company_id
           OR original_record.treasury_account_id <> NEW.treasury_account_id
           OR original_record.currency_code <> NEW.currency_code
           OR original_record.signed_amount <> -NEW.signed_amount THEN
            RAISE EXCEPTION 'Treasury reversal must exactly negate original effect in the same account' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_movements_insert_guard
BEFORE INSERT ON treasury_movements
FOR EACH ROW EXECUTE FUNCTION mars_guard_treasury_movement_insert();

CREATE OR REPLACE FUNCTION mars_project_treasury_balance()
RETURNS trigger AS $$
BEGIN
    PERFORM set_config('mars.treasury_projection', '1', true);
    INSERT INTO treasury_balances (company_id, treasury_account_id, balance, updated_at)
    VALUES (NEW.company_id, NEW.treasury_account_id, NEW.signed_amount, NEW.created_at)
    ON CONFLICT (company_id, treasury_account_id)
    DO UPDATE SET
        balance = treasury_balances.balance + EXCLUDED.balance,
        updated_at = EXCLUDED.updated_at;
    PERFORM set_config('mars.treasury_projection', '0', true);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_movements_balance_projection
AFTER INSERT ON treasury_movements
FOR EACH ROW EXECUTE FUNCTION mars_project_treasury_balance();

CREATE OR REPLACE FUNCTION mars_prevent_treasury_movement_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'treasury_movements are immutable' USING ERRCODE = '55000';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_movements_immutable_guard
BEFORE UPDATE OR DELETE ON treasury_movements
FOR EACH ROW EXECUTE FUNCTION mars_prevent_treasury_movement_mutation();

CREATE OR REPLACE FUNCTION mars_guard_treasury_payment()
RETURNS trigger AS $$
DECLARE
    treasury_currency char(3);
    treasury_type text;
    treasury_active boolean;
    method_kind text;
    method_account bigint;
    commercial_type text;
    commercial_status text;
    commercial_currency char(3);
BEGIN
    IF TG_OP = 'INSERT' AND NEW.status <> 'draft' THEN
        RAISE EXCEPTION 'Treasury payment must be inserted as draft' USING ERRCODE = '23514';
    END IF;

    SELECT ta.currency_code, ta.type, ta.is_active
      INTO treasury_currency, treasury_type, treasury_active
      FROM treasury_accounts AS ta
     WHERE ta.company_id = NEW.company_id AND ta.id = NEW.treasury_account_id
     FOR SHARE;
    IF treasury_currency IS NULL OR treasury_active IS NOT TRUE OR treasury_currency <> NEW.currency_code THEN
        RAISE EXCEPTION 'Treasury payment requires active same-currency treasury account' USING ERRCODE = '23514';
    END IF;

    SELECT pm.kind, pm.treasury_account_id
      INTO method_kind, method_account
      FROM treasury_payment_methods AS pm
     WHERE pm.company_id = NEW.company_id AND pm.id = NEW.payment_method_id AND pm.is_active
     FOR SHARE;
    IF method_kind IS NULL OR method_kind <> NEW.payment_kind
       OR (method_account IS NOT NULL AND method_account <> NEW.treasury_account_id) THEN
        RAISE EXCEPTION 'Treasury payment method snapshot/account mismatch' USING ERRCODE = '23514';
    END IF;
    IF NEW.payment_kind IN ('pos','virtual_pos') AND treasury_type <> 'pos' THEN
        RAISE EXCEPTION 'POS payment requires POS treasury account' USING ERRCODE = '23514';
    END IF;

    SELECT a.type, a.status, a.book_currency_code
      INTO commercial_type, commercial_status, commercial_currency
      FROM accounts AS a
     WHERE a.company_id = NEW.company_id AND a.id = NEW.account_id
     FOR SHARE;
    IF commercial_status IS DISTINCT FROM 'active' OR commercial_currency IS DISTINCT FROM NEW.currency_code THEN
        RAISE EXCEPTION 'Treasury payment requires active same-currency commercial account' USING ERRCODE = '23514';
    END IF;
    IF NEW.direction = 'collection' AND commercial_type NOT IN ('customer','mixed') THEN
        RAISE EXCEPTION 'Collection requires customer or mixed account' USING ERRCODE = '23514';
    END IF;
    IF NEW.direction = 'payment' AND commercial_type NOT IN ('supplier','mixed') THEN
        RAISE EXCEPTION 'Payment requires supplier or mixed account' USING ERRCODE = '23514';
    END IF;

    IF TG_OP = 'UPDATE' THEN
        IF OLD.status = 'draft' AND NEW.status = 'draft' THEN
            RETURN NEW;
        END IF;
        IF OLD.status = 'draft' AND NEW.status = 'finalized' THEN
            IF NEW.finalized_at IS NULL
               OR (to_jsonb(NEW) - 'status' - 'finalized_at' - 'updated_at') IS DISTINCT FROM
                  (to_jsonb(OLD) - 'status' - 'finalized_at' - 'updated_at') THEN
                RAISE EXCEPTION 'Treasury payment finalization may only change lifecycle fields' USING ERRCODE = '23514';
            END IF;
            RETURN NEW;
        END IF;
        IF OLD.status = 'finalized' AND NEW.status = 'reversed' THEN
            IF NEW.reversed_at IS NULL
               OR (to_jsonb(NEW) - 'status' - 'pos_status' - 'reversed_at' - 'updated_at') IS DISTINCT FROM
                  (to_jsonb(OLD) - 'status' - 'pos_status' - 'reversed_at' - 'updated_at') THEN
                RAISE EXCEPTION 'Treasury payment reversal may only change reversal lifecycle fields' USING ERRCODE = '23514';
            END IF;
            RETURN NEW;
        END IF;
        IF OLD.status = 'finalized' AND NEW.status = 'finalized'
           AND OLD.pos_status = 'pending' AND NEW.pos_status = 'settled' THEN
            IF (to_jsonb(NEW) - 'pos_status' - 'updated_at') IS DISTINCT FROM
               (to_jsonb(OLD) - 'pos_status' - 'updated_at') THEN
                RAISE EXCEPTION 'POS settlement may only change POS lifecycle state' USING ERRCODE = '23514';
            END IF;
            RETURN NEW;
        END IF;
        IF OLD.status = 'finalized' AND NEW.status = 'finalized'
           AND OLD.pos_status = 'settled' AND NEW.pos_status = 'chargeback' THEN
            IF (to_jsonb(NEW) - 'pos_status' - 'updated_at') IS DISTINCT FROM
               (to_jsonb(OLD) - 'pos_status' - 'updated_at') THEN
                RAISE EXCEPTION 'POS chargeback may only change POS lifecycle state' USING ERRCODE = '23514';
            END IF;
            RETURN NEW;
        END IF;
        RAISE EXCEPTION 'invalid treasury payment lifecycle transition' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_payments_guard
BEFORE INSERT OR UPDATE ON treasury_payments
FOR EACH ROW EXECUTE FUNCTION mars_guard_treasury_payment();

CREATE OR REPLACE FUNCTION mars_guard_treasury_payment_commit()
RETURNS trigger AS $$
DECLARE
    treasury_amount numeric(20,6);
    account_amount numeric(20,6);
    treasury_effect text;
    account_effect text;
    original_treasury_id bigint;
    original_account_id bigint;
BEGIN
    IF OLD.status = 'draft' AND NEW.status = 'finalized' THEN
        treasury_amount := CASE WHEN NEW.direction = 'collection' THEN NEW.amount ELSE -NEW.amount END;
        account_amount := -treasury_amount;
        treasury_effect := CASE WHEN NEW.direction = 'collection' THEN 'treasury.collection' ELSE 'treasury.payment' END;
        account_effect := CASE WHEN NEW.direction = 'collection' THEN 'account.collection' ELSE 'account.payment' END;

        IF NOT EXISTS (
            SELECT 1 FROM treasury_movements AS tm
             WHERE tm.company_id = NEW.company_id
               AND tm.treasury_account_id = NEW.treasury_account_id
               AND tm.account_id = NEW.account_id
               AND tm.payment_method_id = NEW.payment_method_id
               AND tm.posting_date = NEW.payment_date
               AND tm.currency_code = NEW.currency_code
               AND tm.signed_amount = treasury_amount
               AND tm.source_type = 'treasury_payment'
               AND tm.source_id = NEW.id::text
               AND tm.effect_type = treasury_effect
               AND tm.reversal_of_movement_id IS NULL
        ) THEN
            RAISE EXCEPTION 'finalized treasury payment requires exact treasury effect' USING ERRCODE = '23514';
        END IF;
        IF NOT EXISTS (
            SELECT 1 FROM account_transactions AS atx
             WHERE atx.company_id = NEW.company_id
               AND atx.account_id = NEW.account_id
               AND atx.posting_date = NEW.payment_date
               AND atx.currency_code = NEW.currency_code
               AND atx.signed_amount = account_amount
               AND atx.source_type = 'treasury_payment'
               AND atx.source_id = NEW.id::text
               AND atx.effect_type = account_effect
               AND atx.reversal_of_transaction_id IS NULL
        ) THEN
            RAISE EXCEPTION 'finalized treasury payment requires exact account effect' USING ERRCODE = '23514';
        END IF;
    END IF;

    IF OLD.status = 'finalized' AND NEW.status = 'reversed' THEN
        SELECT tm.id INTO original_treasury_id
          FROM treasury_movements AS tm
         WHERE tm.company_id = NEW.company_id
           AND tm.source_type = 'treasury_payment'
           AND tm.source_id = NEW.id::text;
        SELECT atx.id INTO original_account_id
          FROM account_transactions AS atx
         WHERE atx.company_id = NEW.company_id
           AND atx.source_type = 'treasury_payment'
           AND atx.source_id = NEW.id::text;

        IF original_treasury_id IS NULL OR original_account_id IS NULL
           OR NOT EXISTS (
               SELECT 1 FROM treasury_movements AS reversal
                WHERE reversal.company_id = NEW.company_id
                  AND reversal.source_type = 'treasury_payment_reversal'
                  AND reversal.source_id = NEW.id::text
                  AND reversal.reversal_of_movement_id = original_treasury_id
           )
           OR NOT EXISTS (
               SELECT 1 FROM account_transactions AS reversal
                WHERE reversal.company_id = NEW.company_id
                  AND reversal.source_type = 'treasury_payment_reversal'
                  AND reversal.source_id = NEW.id::text
                  AND reversal.reversal_of_transaction_id = original_account_id
           ) THEN
            RAISE EXCEPTION 'reversed treasury payment requires exact account and treasury reversal effects' USING ERRCODE = '23514';
        END IF;
    END IF;

    IF OLD.status = 'finalized' AND NEW.status = 'finalized'
       AND OLD.pos_status = 'settled' AND NEW.pos_status = 'chargeback' THEN
        IF NOT EXISTS (
            SELECT 1
              FROM treasury_pos_settlements AS settlement
              JOIN treasury_movements AS tm
                ON tm.company_id = settlement.company_id
               AND tm.treasury_account_id = settlement.bank_account_id
               AND tm.source_type = 'treasury_pos_chargeback'
               AND tm.source_id = NEW.id::text
               AND tm.effect_type = 'treasury.pos_chargeback'
               AND tm.signed_amount = -settlement.gross_amount
             WHERE settlement.company_id = NEW.company_id
               AND settlement.treasury_payment_id = NEW.id
        ) OR NOT EXISTS (
            SELECT 1 FROM account_transactions AS atx
             WHERE atx.company_id = NEW.company_id
               AND atx.account_id = NEW.account_id
               AND atx.source_type = 'treasury_pos_chargeback'
               AND atx.source_id = NEW.id::text
               AND atx.effect_type = 'account.pos_chargeback'
               AND atx.signed_amount = NEW.amount
        ) THEN
            RAISE EXCEPTION 'POS chargeback requires exact bank and customer effects' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE CONSTRAINT TRIGGER treasury_payments_commit_guard
AFTER UPDATE ON treasury_payments
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_treasury_payment_commit();

CREATE OR REPLACE FUNCTION mars_guard_pos_settlement()
RETURNS trigger AS $$
DECLARE
    payment treasury_payments%ROWTYPE;
    source_type text;
    bank_type text;
BEGIN
    SELECT * INTO payment
      FROM treasury_payments
     WHERE company_id = NEW.company_id AND id = NEW.treasury_payment_id
     FOR SHARE;
    IF NOT FOUND OR payment.status <> 'finalized' OR payment.payment_kind NOT IN ('pos','virtual_pos')
       OR payment.treasury_account_id <> NEW.source_pos_account_id
       OR payment.currency_code <> NEW.currency_code
       OR payment.amount <> NEW.gross_amount THEN
        RAISE EXCEPTION 'POS settlement source payment mismatch' USING ERRCODE = '23514';
    END IF;
    SELECT type INTO source_type FROM treasury_accounts
     WHERE company_id = NEW.company_id AND id = NEW.source_pos_account_id;
    SELECT type INTO bank_type FROM treasury_accounts
     WHERE company_id = NEW.company_id AND id = NEW.bank_account_id;
    IF source_type IS DISTINCT FROM 'pos' OR bank_type IS DISTINCT FROM 'bank' THEN
        RAISE EXCEPTION 'POS settlement requires POS source and bank destination' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_pos_settlements_source_guard
BEFORE INSERT ON treasury_pos_settlements
FOR EACH ROW EXECUTE FUNCTION mars_guard_pos_settlement();

CREATE OR REPLACE FUNCTION mars_guard_pos_settlement_commit()
RETURNS trigger AS $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM treasury_movements AS tm
         WHERE tm.company_id = NEW.company_id
           AND tm.treasury_account_id = NEW.source_pos_account_id
           AND tm.posting_date = NEW.settlement_date
           AND tm.currency_code = NEW.currency_code
           AND tm.signed_amount = -NEW.gross_amount
           AND tm.source_type = 'treasury_pos_settlement'
           AND tm.source_id = NEW.id::text
           AND tm.effect_type = 'treasury.pos_settlement_out'
    ) OR NOT EXISTS (
        SELECT 1 FROM treasury_movements AS tm
         WHERE tm.company_id = NEW.company_id
           AND tm.treasury_account_id = NEW.bank_account_id
           AND tm.posting_date = NEW.settlement_date
           AND tm.currency_code = NEW.currency_code
           AND tm.signed_amount = NEW.net_amount
           AND tm.source_type = 'treasury_pos_settlement'
           AND tm.source_id = NEW.id::text
           AND tm.effect_type = 'treasury.pos_settlement_in'
    ) OR NOT EXISTS (
        SELECT 1 FROM treasury_payments AS payment
         WHERE payment.company_id = NEW.company_id
           AND payment.id = NEW.treasury_payment_id
           AND payment.pos_status = 'settled'
    ) THEN
        RAISE EXCEPTION 'POS settlement requires exact gross-out, net-in and settled lifecycle evidence' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE CONSTRAINT TRIGGER treasury_pos_settlements_commit_guard
AFTER INSERT ON treasury_pos_settlements
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_pos_settlement_commit();

CREATE OR REPLACE FUNCTION mars_prevent_pos_settlement_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'treasury_pos_settlements are immutable' USING ERRCODE = '55000';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_pos_settlements_immutable_guard
BEFORE UPDATE OR DELETE ON treasury_pos_settlements
FOR EACH ROW EXECUTE FUNCTION mars_prevent_pos_settlement_mutation();

CREATE OR REPLACE FUNCTION mars_guard_manual_movement()
RETURNS trigger AS $$
DECLARE
    account_type text;
    account_currency char(3);
BEGIN
    IF TG_OP = 'INSERT' AND NEW.status <> 'draft' THEN
        RAISE EXCEPTION 'Manual treasury movement must be inserted as draft' USING ERRCODE = '23514';
    END IF;
    SELECT ta.type, ta.currency_code INTO account_type, account_currency
      FROM treasury_accounts AS ta
     WHERE ta.company_id = NEW.company_id AND ta.id = NEW.treasury_account_id AND ta.is_active
     FOR SHARE;
    IF account_currency IS NULL OR account_currency <> NEW.currency_code THEN
        RAISE EXCEPTION 'Manual treasury movement requires active same-currency account' USING ERRCODE = '23514';
    END IF;
    IF (NEW.operation LIKE 'cash_%' AND account_type <> 'cash')
       OR (NEW.operation LIKE 'bank_%' AND account_type <> 'bank') THEN
        RAISE EXCEPTION 'Manual treasury movement operation/account type mismatch' USING ERRCODE = '23514';
    END IF;
    IF TG_OP = 'UPDATE' THEN
        IF OLD.status = 'draft' AND NEW.status = 'draft' THEN RETURN NEW; END IF;
        IF OLD.status = 'draft' AND NEW.status = 'finalized'
           AND NEW.finalized_at IS NOT NULL
           AND (to_jsonb(NEW) - 'status' - 'finalized_at' - 'updated_at') IS NOT DISTINCT FROM
               (to_jsonb(OLD) - 'status' - 'finalized_at' - 'updated_at') THEN
            RETURN NEW;
        END IF;
        RAISE EXCEPTION 'invalid manual treasury movement lifecycle transition' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_manual_movements_guard
BEFORE INSERT OR UPDATE ON treasury_manual_movements
FOR EACH ROW EXECUTE FUNCTION mars_guard_manual_movement();

CREATE OR REPLACE FUNCTION mars_guard_manual_movement_commit()
RETURNS trigger AS $$
DECLARE
    effect_amount numeric(20,6);
BEGIN
    IF OLD.status = 'draft' AND NEW.status = 'finalized' THEN
        effect_amount := CASE WHEN NEW.operation IN ('cash_in','bank_in') THEN NEW.amount ELSE -NEW.amount END;
        IF NOT EXISTS (
            SELECT 1 FROM treasury_movements AS tm
             WHERE tm.company_id = NEW.company_id
               AND tm.treasury_account_id = NEW.treasury_account_id
               AND tm.posting_date = NEW.movement_date
               AND tm.currency_code = NEW.currency_code
               AND tm.signed_amount = effect_amount
               AND tm.movement_type = NEW.operation
               AND tm.source_type = 'treasury_manual_movement'
               AND tm.source_id = NEW.id::text
               AND tm.effect_type = 'treasury.' || NEW.operation
        ) THEN
            RAISE EXCEPTION 'finalized manual treasury movement requires exact ledger effect' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE CONSTRAINT TRIGGER treasury_manual_movements_commit_guard
AFTER UPDATE ON treasury_manual_movements
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_manual_movement_commit();

CREATE OR REPLACE FUNCTION mars_guard_transfer()
RETURNS trigger AS $$
DECLARE
    from_currency char(3);
    to_currency char(3);
BEGIN
    IF TG_OP = 'INSERT' AND NEW.status <> 'draft' THEN
        RAISE EXCEPTION 'Treasury transfer must be inserted as draft' USING ERRCODE = '23514';
    END IF;
    SELECT currency_code INTO from_currency FROM treasury_accounts
     WHERE company_id = NEW.company_id AND id = NEW.from_account_id AND is_active FOR SHARE;
    SELECT currency_code INTO to_currency FROM treasury_accounts
     WHERE company_id = NEW.company_id AND id = NEW.to_account_id AND is_active FOR SHARE;
    IF from_currency IS NULL OR to_currency IS NULL OR from_currency <> to_currency OR from_currency <> NEW.currency_code THEN
        RAISE EXCEPTION 'M10 V1 treasury transfer requires active same-currency accounts' USING ERRCODE = '23514';
    END IF;
    IF TG_OP = 'UPDATE' THEN
        IF OLD.status = 'draft' AND NEW.status = 'draft' THEN RETURN NEW; END IF;
        IF OLD.status = 'draft' AND NEW.status = 'finalized'
           AND NEW.finalized_at IS NOT NULL
           AND (to_jsonb(NEW) - 'status' - 'finalized_at' - 'updated_at') IS NOT DISTINCT FROM
               (to_jsonb(OLD) - 'status' - 'finalized_at' - 'updated_at') THEN
            RETURN NEW;
        END IF;
        RAISE EXCEPTION 'invalid treasury transfer lifecycle transition' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_transfers_guard
BEFORE INSERT OR UPDATE ON treasury_transfers
FOR EACH ROW EXECUTE FUNCTION mars_guard_transfer();

CREATE OR REPLACE FUNCTION mars_guard_transfer_commit()
RETURNS trigger AS $$
BEGIN
    IF OLD.status = 'draft' AND NEW.status = 'finalized' THEN
        IF NOT EXISTS (
            SELECT 1 FROM treasury_movements
             WHERE company_id = NEW.company_id AND treasury_account_id = NEW.from_account_id
               AND signed_amount = -NEW.amount AND posting_date = NEW.transfer_date
               AND source_type = 'treasury_transfer' AND source_id = NEW.id::text
               AND effect_type = 'treasury.transfer_out'
        ) OR NOT EXISTS (
            SELECT 1 FROM treasury_movements
             WHERE company_id = NEW.company_id AND treasury_account_id = NEW.to_account_id
               AND signed_amount = NEW.amount AND posting_date = NEW.transfer_date
               AND source_type = 'treasury_transfer' AND source_id = NEW.id::text
               AND effect_type = 'treasury.transfer_in'
        ) THEN
            RAISE EXCEPTION 'finalized treasury transfer requires exact two-sided effects' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE CONSTRAINT TRIGGER treasury_transfers_commit_guard
AFTER UPDATE ON treasury_transfers
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_transfer_commit();

CREATE OR REPLACE FUNCTION mars_guard_expense()
RETURNS trigger AS $$
DECLARE
    account_currency char(3);
BEGIN
    IF TG_OP = 'INSERT' AND NEW.status <> 'draft' THEN
        RAISE EXCEPTION 'Treasury expense must be inserted as draft' USING ERRCODE = '23514';
    END IF;
    SELECT currency_code INTO account_currency FROM treasury_accounts
     WHERE company_id = NEW.company_id AND id = NEW.treasury_account_id AND is_active FOR SHARE;
    IF account_currency IS NULL OR account_currency <> NEW.currency_code THEN
        RAISE EXCEPTION 'Treasury expense requires active same-currency account' USING ERRCODE = '23514';
    END IF;
    IF TG_OP = 'UPDATE' THEN
        IF OLD.status = 'draft' AND NEW.status = 'draft' THEN RETURN NEW; END IF;
        IF OLD.status = 'draft' AND NEW.status = 'finalized'
           AND NEW.finalized_at IS NOT NULL
           AND (to_jsonb(NEW) - 'status' - 'finalized_at' - 'updated_at') IS NOT DISTINCT FROM
               (to_jsonb(OLD) - 'status' - 'finalized_at' - 'updated_at') THEN
            RETURN NEW;
        END IF;
        RAISE EXCEPTION 'invalid treasury expense lifecycle transition' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_expenses_guard
BEFORE INSERT OR UPDATE ON treasury_expenses
FOR EACH ROW EXECUTE FUNCTION mars_guard_expense();

CREATE OR REPLACE FUNCTION mars_guard_expense_commit()
RETURNS trigger AS $$
BEGIN
    IF OLD.status = 'draft' AND NEW.status = 'finalized'
       AND NOT EXISTS (
           SELECT 1 FROM treasury_movements
            WHERE company_id = NEW.company_id AND treasury_account_id = NEW.treasury_account_id
              AND signed_amount = -NEW.amount AND posting_date = NEW.expense_date
              AND source_type = 'treasury_expense' AND source_id = NEW.id::text
              AND effect_type = 'treasury.expense'
       ) THEN
        RAISE EXCEPTION 'finalized treasury expense requires exact treasury effect' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE CONSTRAINT TRIGGER treasury_expenses_commit_guard
AFTER UPDATE ON treasury_expenses
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_expense_commit();

CREATE OR REPLACE FUNCTION mars_guard_cash_count()
RETURNS trigger AS $$
DECLARE
    account_type text;
    account_currency char(3);
BEGIN
    IF TG_OP = 'INSERT' AND NEW.status <> 'draft' THEN
        RAISE EXCEPTION 'Cash count must be inserted as draft' USING ERRCODE = '23514';
    END IF;
    SELECT type, currency_code INTO account_type, account_currency FROM treasury_accounts
     WHERE company_id = NEW.company_id AND id = NEW.treasury_account_id AND is_active FOR SHARE;
    IF account_type IS DISTINCT FROM 'cash' OR account_currency IS DISTINCT FROM NEW.currency_code THEN
        RAISE EXCEPTION 'Cash count requires active same-currency cash account' USING ERRCODE = '23514';
    END IF;
    IF TG_OP = 'UPDATE' THEN
        IF OLD.status = 'draft' AND NEW.status = 'draft' THEN RETURN NEW; END IF;
        IF OLD.status = 'draft' AND NEW.status = 'finalized'
           AND NEW.finalized_at IS NOT NULL
           AND (to_jsonb(NEW) - 'status' - 'ledger_balance' - 'counted_total' - 'variance' - 'finalized_at' - 'updated_at') IS NOT DISTINCT FROM
               (to_jsonb(OLD) - 'status' - 'ledger_balance' - 'counted_total' - 'variance' - 'finalized_at' - 'updated_at') THEN
            RETURN NEW;
        END IF;
        RAISE EXCEPTION 'invalid cash count lifecycle transition' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_cash_counts_guard
BEFORE INSERT OR UPDATE ON treasury_cash_counts
FOR EACH ROW EXECUTE FUNCTION mars_guard_cash_count();

CREATE OR REPLACE FUNCTION mars_guard_cash_count_line()
RETURNS trigger AS $$
DECLARE
    parent_status text;
BEGIN
    IF TG_OP = 'DELETE' THEN
        SELECT status INTO parent_status FROM treasury_cash_counts
         WHERE company_id = OLD.company_id AND id = OLD.treasury_cash_count_id;
    ELSE
        SELECT status INTO parent_status FROM treasury_cash_counts
         WHERE company_id = NEW.company_id AND id = NEW.treasury_cash_count_id;
    END IF;
    IF parent_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'cash count lines require draft parent' USING ERRCODE = '55000';
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER treasury_cash_count_lines_guard
BEFORE INSERT OR UPDATE OR DELETE ON treasury_cash_count_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_cash_count_line();

CREATE OR REPLACE FUNCTION mars_guard_cash_count_commit()
RETURNS trigger AS $$
BEGIN
    IF OLD.status = 'draft' AND NEW.status = 'finalized' THEN
        IF NEW.counted_total IS DISTINCT FROM (
            SELECT COALESCE(SUM(line_total), 0)::numeric(20,6)
              FROM treasury_cash_count_lines
             WHERE company_id = NEW.company_id AND treasury_cash_count_id = NEW.id
        ) THEN
            RAISE EXCEPTION 'cash count total must equal denomination lines' USING ERRCODE = '23514';
        END IF;
        IF NEW.variance <> 0 AND NOT EXISTS (
            SELECT 1 FROM treasury_movements
             WHERE company_id = NEW.company_id AND treasury_account_id = NEW.treasury_account_id
               AND signed_amount = NEW.variance AND posting_date = NEW.count_date
               AND source_type = 'treasury_cash_count' AND source_id = NEW.id::text
               AND effect_type = 'treasury.cash_count_adjustment'
        ) THEN
            RAISE EXCEPTION 'cash count variance requires exact treasury adjustment' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE CONSTRAINT TRIGGER treasury_cash_counts_commit_guard
AFTER UPDATE ON treasury_cash_counts
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION mars_guard_cash_count_commit();

CREATE OR REPLACE FUNCTION mars_prevent_statement_import_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'bank_statement_imports are immutable' USING ERRCODE = '55000';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER bank_statement_imports_immutable_guard
BEFORE UPDATE OR DELETE ON bank_statement_imports
FOR EACH ROW EXECUTE FUNCTION mars_prevent_statement_import_mutation();

CREATE OR REPLACE FUNCTION mars_guard_statement_line()
RETURNS trigger AS $$
DECLARE
    movement treasury_movements%ROWTYPE;
    import_account bigint;
BEGIN
    IF TG_OP = 'INSERT' THEN
        SELECT treasury_account_id INTO import_account
          FROM bank_statement_imports
         WHERE company_id = NEW.company_id AND id = NEW.bank_statement_import_id;
        IF import_account IS NULL OR import_account <> NEW.treasury_account_id THEN
            RAISE EXCEPTION 'bank statement line/import account mismatch' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF NEW.external_key IS DISTINCT FROM OLD.external_key
       OR NEW.booking_date IS DISTINCT FROM OLD.booking_date
       OR NEW.value_date IS DISTINCT FROM OLD.value_date
       OR NEW.currency_code IS DISTINCT FROM OLD.currency_code
       OR NEW.signed_amount IS DISTINCT FROM OLD.signed_amount
       OR NEW.reference IS DISTINCT FROM OLD.reference
       OR NEW.description IS DISTINCT FROM OLD.description
       OR NEW.treasury_account_id IS DISTINCT FROM OLD.treasury_account_id
       OR NEW.bank_statement_import_id IS DISTINCT FROM OLD.bank_statement_import_id THEN
        RAISE EXCEPTION 'bank statement source fields are immutable' USING ERRCODE = '55000';
    END IF;

    IF NEW.match_status = 'matched'
       AND (OLD.match_status IS DISTINCT FROM 'matched'
            OR NEW.matched_treasury_movement_id IS DISTINCT FROM OLD.matched_treasury_movement_id) THEN
        SELECT * INTO movement FROM treasury_movements WHERE id = NEW.matched_treasury_movement_id;
        IF NOT FOUND
           OR movement.company_id <> NEW.company_id
           OR movement.treasury_account_id <> NEW.treasury_account_id
           OR movement.currency_code <> NEW.currency_code
           OR movement.signed_amount <> NEW.signed_amount THEN
            RAISE EXCEPTION 'bank statement match requires exact same-account currency and amount treasury movement' USING ERRCODE = '23514';
        END IF;
    END IF;
    IF OLD.match_status <> 'unmatched' AND NEW.match_status IS DISTINCT FROM OLD.match_status THEN
        RAISE EXCEPTION 'matched or ignored statement line is terminal' USING ERRCODE = '55000';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER bank_statement_lines_guard
BEFORE INSERT OR UPDATE ON bank_statement_lines
FOR EACH ROW EXECUTE FUNCTION mars_guard_statement_line();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS bank_statement_lines_guard ON bank_statement_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_statement_line()');
        DB::statement('DROP TRIGGER IF EXISTS bank_statement_imports_immutable_guard ON bank_statement_imports');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_statement_import_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_cash_counts_commit_guard ON treasury_cash_counts');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_cash_count_commit()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_cash_count_lines_guard ON treasury_cash_count_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_cash_count_line()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_cash_counts_guard ON treasury_cash_counts');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_cash_count()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_expenses_commit_guard ON treasury_expenses');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_expense_commit()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_expenses_guard ON treasury_expenses');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_expense()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_transfers_commit_guard ON treasury_transfers');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_transfer_commit()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_transfers_guard ON treasury_transfers');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_transfer()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_manual_movements_commit_guard ON treasury_manual_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_manual_movement_commit()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_manual_movements_guard ON treasury_manual_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_manual_movement()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_pos_settlements_immutable_guard ON treasury_pos_settlements');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_pos_settlement_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_pos_settlements_commit_guard ON treasury_pos_settlements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_pos_settlement_commit()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_pos_settlements_source_guard ON treasury_pos_settlements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_pos_settlement()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_payments_commit_guard ON treasury_payments');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_treasury_payment_commit()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_payments_guard ON treasury_payments');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_treasury_payment()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_movements_immutable_guard ON treasury_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_treasury_movement_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_movements_balance_projection ON treasury_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_project_treasury_balance()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_movements_insert_guard ON treasury_movements');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_treasury_movement_insert()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_balances_projection_only_guard ON treasury_balances');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_treasury_balance_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_accounts_currency_guard ON treasury_accounts');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_treasury_account_currency_after_activity()');

        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('treasury_cash_count_lines');
        Schema::dropIfExists('treasury_cash_counts');
        Schema::dropIfExists('treasury_expenses');
        Schema::dropIfExists('treasury_transfers');
        Schema::dropIfExists('treasury_manual_movements');
        Schema::dropIfExists('treasury_pos_settlements');
        Schema::dropIfExists('treasury_payments');
        Schema::dropIfExists('treasury_movements');
        Schema::dropIfExists('treasury_balances');
        Schema::dropIfExists('treasury_payment_methods');
        Schema::dropIfExists('treasury_accounts');

        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['treasury.view', 'treasury.manage', 'treasury.reconcile'])
            ->pluck('id')
            ->all();
        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }
        DB::table('permissions')
            ->whereIn('key', ['treasury.view', 'treasury.manage', 'treasury.reconcile'])
            ->delete();
    }
};
