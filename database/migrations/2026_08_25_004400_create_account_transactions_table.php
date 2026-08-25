<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transactions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id');
            $table->foreignId('account_id');
            $table->foreignId('posting_period_id')->constrained('posting_periods')->restrictOnDelete();
            $table->date('posting_date');
            $table->char('currency_code', 3);
            $table->decimal('signed_amount', 20, 6);
            $table->string('source_type', 100);
            $table->string('source_id', 255);
            $table->string('effect_type', 100);
            $table->char('effect_fingerprint', 64);
            $table->string('memo', 500)->nullable();
            $table->unsignedBigInteger('reversal_of_transaction_id')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('reversal_of_transaction_id')
                ->references('id')
                ->on('account_transactions')
                ->restrictOnDelete();

            $table->unique('effect_fingerprint', 'account_transactions_effect_fingerprint_unique');
            $table->unique(
                ['company_id', 'source_type', 'source_id', 'effect_type'],
                'account_transactions_source_effect_unique',
            );
            $table->unique('reversal_of_transaction_id', 'account_transactions_one_reversal_unique');
            $table->index(['company_id', 'account_id', 'posting_date', 'id'], 'account_transactions_statement_index');
        });

        DB::statement('ALTER TABLE account_transactions ADD CONSTRAINT account_transactions_amount_nonzero_check CHECK (signed_amount <> 0)');
        DB::statement('ALTER TABLE account_transactions ADD CONSTRAINT account_transactions_currency_shape_check CHECK (currency_code ~ \'^[A-Z]{3}$\')');
        DB::statement('ALTER TABLE account_transactions ADD CONSTRAINT account_transactions_source_type_check CHECK (source_type ~ \'^[a-z0-9]+([._-][a-z0-9]+)*$\')');
        DB::statement('ALTER TABLE account_transactions ADD CONSTRAINT account_transactions_effect_type_check CHECK (effect_type ~ \'^[a-z0-9]+([._-][a-z0-9]+)*$\')');
        DB::statement('ALTER TABLE account_transactions ADD CONSTRAINT account_transactions_source_id_check CHECK (char_length(source_id) > 0 AND source_id = btrim(source_id))');
        DB::statement('ALTER TABLE account_transactions ADD CONSTRAINT account_transactions_effect_fingerprint_check CHECK (effect_fingerprint ~ \'^[a-f0-9]{64}$\')');
        DB::statement('ALTER TABLE account_transactions ADD CONSTRAINT account_transactions_account_effect_check CHECK (effect_type LIKE \'account.%\')');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION enforce_account_transaction_insert()
RETURNS trigger AS $$
DECLARE
    account_currency char(3);
    original_record account_transactions%ROWTYPE;
BEGIN
    SELECT accounts.book_currency_code
      INTO account_currency
      FROM accounts
     WHERE accounts.company_id = NEW.company_id
       AND accounts.id = NEW.account_id;

    IF account_currency IS NULL THEN
        RAISE EXCEPTION 'Account transaction target does not belong to company'
            USING ERRCODE = '23503';
    END IF;

    IF NEW.currency_code <> account_currency THEN
        RAISE EXCEPTION 'Account transaction currency must equal account book currency'
            USING ERRCODE = '23514';
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM posting_periods
         WHERE posting_periods.id = NEW.posting_period_id
           AND posting_periods.company_id = NEW.company_id
           AND posting_periods.status = 'open'
           AND NEW.posting_date BETWEEN posting_periods.starts_on AND posting_periods.ends_on
    ) THEN
        RAISE EXCEPTION 'Account transaction requires an open matching posting period'
            USING ERRCODE = '23514';
    END IF;

    IF NEW.reversal_of_transaction_id IS NOT NULL THEN
        SELECT *
          INTO original_record
          FROM account_transactions
         WHERE id = NEW.reversal_of_transaction_id;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'Account transaction reversal target does not exist'
                USING ERRCODE = '23503';
        END IF;

        IF original_record.company_id <> NEW.company_id
           OR original_record.account_id <> NEW.account_id
           OR original_record.currency_code <> NEW.currency_code
           OR original_record.signed_amount <> -NEW.signed_amount THEN
            RAISE EXCEPTION 'Account transaction reversal must exactly negate the same account effect'
                USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER account_transactions_insert_guard
BEFORE INSERT ON account_transactions
FOR EACH ROW
EXECUTE FUNCTION enforce_account_transaction_insert();

CREATE OR REPLACE FUNCTION prevent_account_transaction_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'account_transactions are immutable'
        USING ERRCODE = '55000';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER account_transactions_immutable_guard
BEFORE UPDATE OR DELETE ON account_transactions
FOR EACH ROW
EXECUTE FUNCTION prevent_account_transaction_mutation();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS account_transactions_immutable_guard ON account_transactions');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_account_transaction_mutation()');
        DB::unprepared('DROP TRIGGER IF EXISTS account_transactions_insert_guard ON account_transactions');
        DB::unprepared('DROP FUNCTION IF EXISTS enforce_account_transaction_insert()');
        Schema::dropIfExists('account_transactions');
    }
};
