<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! class_exists('CreateAccountBankNotesAndFileTarget20260825000400', false)) {
    final class CreateAccountBankNotesAndFileTarget20260825000400 extends Migration
    {
        public function up(): void
        {
            Schema::create('account_bank_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('account_id');
                $table->string('bank_name', 160);
                $table->string('branch_name', 120)->nullable();
                $table->string('account_holder', 200)->nullable();
                $table->string('iban', 34)->nullable();
                $table->string('account_number', 64)->nullable();
                $table->string('swift_code', 11)->nullable();
                $table->char('currency_code', 3);
                $table->boolean('is_default')->default(false);
                $table->string('note', 500)->nullable();
                $table->timestampsTz();

                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
                $table->foreign(['company_id', 'account_id'])
                    ->references(['company_id', 'id'])
                    ->on('accounts')
                    ->restrictOnDelete();
                $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
                $table->index(['company_id', 'account_id']);
            });

            Schema::create('account_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('account_id');
                $table->text('body');
                $table->boolean('is_pinned')->default(false);
                $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestampsTz();

                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
                $table->foreign(['company_id', 'account_id'])
                    ->references(['company_id', 'id'])
                    ->on('accounts')
                    ->restrictOnDelete();
                $table->index(['company_id', 'account_id', 'is_pinned']);
            });

            DB::statement('ALTER TABLE account_bank_accounts ADD CONSTRAINT account_bank_accounts_bank_name_not_blank_check CHECK (char_length(btrim(bank_name)) > 0)');
            DB::statement("ALTER TABLE account_bank_accounts ADD CONSTRAINT account_bank_accounts_currency_check CHECK (currency_code ~ '^[A-Z]{3}$')");
            DB::statement("ALTER TABLE account_bank_accounts ADD CONSTRAINT account_bank_accounts_iban_check CHECK (iban IS NULL OR iban ~ '^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$')");
            DB::statement("ALTER TABLE account_bank_accounts ADD CONSTRAINT account_bank_accounts_swift_check CHECK (swift_code IS NULL OR swift_code ~ '^[A-Z0-9]{8}([A-Z0-9]{3})?$')");
            DB::statement('ALTER TABLE account_bank_accounts ADD CONSTRAINT account_bank_accounts_identity_check CHECK (iban IS NOT NULL OR account_number IS NOT NULL)');
            DB::statement('CREATE UNIQUE INDEX account_bank_accounts_one_default ON account_bank_accounts (company_id, account_id) WHERE is_default');
            DB::statement('CREATE UNIQUE INDEX account_bank_accounts_unique_iban ON account_bank_accounts (company_id, account_id, iban) WHERE iban IS NOT NULL');

            DB::statement('ALTER TABLE account_notes ADD CONSTRAINT account_notes_body_not_blank_check CHECK (char_length(btrim(body)) > 0)');

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION enforce_account_attachment_target()
RETURNS trigger AS $$
BEGIN
    IF NEW.attachable_type = 'account' AND NOT EXISTS (
        SELECT 1
        FROM accounts
        WHERE accounts.company_id = NEW.company_id
          AND accounts.id = NEW.attachable_id
    ) THEN
        RAISE EXCEPTION 'Account attachment target does not belong to company'
            USING ERRCODE = '23503';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER attachments_account_target_guard
AFTER INSERT OR UPDATE OF company_id, attachable_type, attachable_id ON attachments
DEFERRABLE INITIALLY IMMEDIATE
FOR EACH ROW
EXECUTE FUNCTION enforce_account_attachment_target();
SQL);
        }

        public function down(): void
        {
            DB::unprepared('DROP TRIGGER IF EXISTS attachments_account_target_guard ON attachments');
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_account_attachment_target()');

            Schema::dropIfExists('account_notes');
            Schema::dropIfExists('account_bank_accounts');
        }
    }
}

return new CreateAccountBankNotesAndFileTarget20260825000400;
