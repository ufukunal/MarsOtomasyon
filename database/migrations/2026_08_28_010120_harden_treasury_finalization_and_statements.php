<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_enforce_treasury_payment_effect_evidence()
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
            SELECT 1
              FROM treasury_movements AS tm
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
            SELECT 1
              FROM account_transactions AS atx
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
        SELECT tm.id
          INTO original_treasury_id
          FROM treasury_movements AS tm
         WHERE tm.company_id = NEW.company_id
           AND tm.source_type = 'treasury_payment'
           AND tm.source_id = NEW.id::text;

        SELECT atx.id
          INTO original_account_id
          FROM account_transactions AS atx
         WHERE atx.company_id = NEW.company_id
           AND atx.source_type = 'treasury_payment'
           AND atx.source_id = NEW.id::text;

        IF original_treasury_id IS NULL
           OR original_account_id IS NULL
           OR NOT EXISTS (
               SELECT 1
                 FROM treasury_movements AS reversal
                WHERE reversal.company_id = NEW.company_id
                  AND reversal.source_type = 'treasury_payment_reversal'
                  AND reversal.source_id = NEW.id::text
                  AND reversal.reversal_of_movement_id = original_treasury_id
           )
           OR NOT EXISTS (
               SELECT 1
                 FROM account_transactions AS reversal
                WHERE reversal.company_id = NEW.company_id
                  AND reversal.source_type = 'treasury_payment_reversal'
                  AND reversal.source_id = NEW.id::text
                  AND reversal.reversal_of_transaction_id = original_account_id
           ) THEN
            RAISE EXCEPTION 'reversed treasury payment requires exact account and treasury reversal effects' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER treasury_payments_immediate_effect_guard
BEFORE UPDATE OF status ON treasury_payments
FOR EACH ROW
WHEN (OLD.status IS DISTINCT FROM NEW.status)
EXECUTE FUNCTION mars_enforce_treasury_payment_effect_evidence();

CREATE OR REPLACE FUNCTION mars_harden_statement_line_authority()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'bank statement lines are immutable evidence' USING ERRCODE = '55000';
    END IF;

    IF OLD.match_status <> 'unmatched'
       AND (
           NEW.match_status IS DISTINCT FROM OLD.match_status
           OR NEW.matched_treasury_movement_id IS DISTINCT FROM OLD.matched_treasury_movement_id
           OR NEW.matched_at IS DISTINCT FROM OLD.matched_at
       ) THEN
        RAISE EXCEPTION 'matched or ignored statement line reconciliation metadata is terminal' USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER bank_statement_lines_authority_guard
BEFORE UPDATE OR DELETE ON bank_statement_lines
FOR EACH ROW EXECUTE FUNCTION mars_harden_statement_line_authority();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS bank_statement_lines_authority_guard ON bank_statement_lines');
        DB::statement('DROP FUNCTION IF EXISTS mars_harden_statement_line_authority()');
        DB::statement('DROP TRIGGER IF EXISTS treasury_payments_immediate_effect_guard ON treasury_payments');
        DB::statement('DROP FUNCTION IF EXISTS mars_enforce_treasury_payment_effect_evidence()');
    }
};
