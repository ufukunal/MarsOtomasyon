<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION lock_account_row_before_transaction_insert()
RETURNS trigger AS $$
BEGIN
    PERFORM 1
      FROM accounts
     WHERE accounts.company_id = NEW.company_id
       AND accounts.id = NEW.account_id
     FOR SHARE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Account transaction target does not belong to company'
            USING ERRCODE = '23503';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER account_transactions_account_lock_guard
BEFORE INSERT ON account_transactions
FOR EACH ROW
EXECUTE FUNCTION lock_account_row_before_transaction_insert();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS account_transactions_account_lock_guard ON account_transactions');
        DB::unprepared('DROP FUNCTION IF EXISTS lock_account_row_before_transaction_insert()');
    }
};
