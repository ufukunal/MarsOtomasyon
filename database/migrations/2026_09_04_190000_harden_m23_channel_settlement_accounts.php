<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_validate_channel_settlement_clearing_account()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.clearing_account_id IS NULL OR NOT EXISTS (
        SELECT 1
        FROM accounts a
        WHERE a.id = NEW.clearing_account_id
          AND a.company_id = NEW.company_id
          AND a.status = 'active'
          AND a.type = 'clearing'
    ) THEN
        RAISE EXCEPTION 'Channel settlement requires an active clearing account from the same company.'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER channel_settlement_clearing_account_guard
BEFORE INSERT OR UPDATE OF company_id, clearing_account_id
ON channel_settlement_evidence
FOR EACH ROW
EXECUTE FUNCTION mars_validate_channel_settlement_clearing_account();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS channel_settlement_clearing_account_guard ON channel_settlement_evidence;
DROP FUNCTION IF EXISTS mars_validate_channel_settlement_clearing_account();
SQL);
    }
};
