<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_account_book_currency_change_after_transactions()
RETURNS trigger AS $$
BEGIN
    IF NEW.book_currency_code IS DISTINCT FROM OLD.book_currency_code
       AND EXISTS (
           SELECT 1
             FROM account_transactions
            WHERE account_transactions.company_id = OLD.company_id
              AND account_transactions.account_id = OLD.id
       ) THEN
        RAISE EXCEPTION 'Account book currency cannot change after ledger activity'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER accounts_book_currency_ledger_guard
BEFORE UPDATE OF book_currency_code ON accounts
FOR EACH ROW
EXECUTE FUNCTION prevent_account_book_currency_change_after_transactions();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS accounts_book_currency_ledger_guard ON accounts');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_account_book_currency_change_after_transactions()');
    }
};
