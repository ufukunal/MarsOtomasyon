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
            ['key' => 'instruments.view', 'name' => 'Çek / senet görüntüleme', 'description' => 'Aktif şirketteki çek/senet portföyü, vade, holder ve lifecycle geçmişini görüntüleme yetkisi.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'instruments.manage', 'name' => 'Çek / senet yönetimi', 'description' => 'Çek/senet teslim alma/verme, bankaya gönderme, ciro, tahsil/ödeme ve ters kayıt lifecycle yönetimi yetkisi.', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('instruments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->string('direction', 16);
            $table->string('kind', 24);
            $table->string('status', 24)->default('draft');
            $table->string('document_no', 120);
            $table->decimal('amount', 20, 6);
            $table->char('currency_code', 3);
            $table->date('issue_date')->nullable();
            $table->date('delivery_date');
            $table->date('due_date');
            $table->string('bank_name', 160)->nullable();
            $table->string('branch_name', 120)->nullable();
            $table->string('drawer_or_maker', 200)->nullable();
            $table->string('current_holder_type', 16)->nullable();
            $table->unsignedBigInteger('current_holder_account_id')->nullable();
            $table->unsignedBigInteger('current_treasury_account_id')->nullable();
            $table->unsignedBigInteger('endorsed_to_account_id')->nullable();
            $table->unsignedBigInteger('settlement_treasury_account_id')->nullable();
            $table->unsignedBigInteger('delivery_account_transaction_id')->nullable();
            $table->unsignedBigInteger('endorsement_account_transaction_id')->nullable();
            $table->unsignedBigInteger('settlement_treasury_movement_id')->nullable();
            $table->unsignedBigInteger('delivery_reversal_account_transaction_id')->nullable();
            $table->unsignedBigInteger('endorsement_reversal_account_transaction_id')->nullable();
            $table->timestampTz('registered_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'instruments_company_id_id_unique');
            $table->foreign(['company_id', 'account_id'])->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'current_holder_account_id'], 'instruments_holder_account_fk')->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'endorsed_to_account_id'], 'instruments_endorsed_account_fk')->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'current_treasury_account_id'], 'instruments_current_treasury_fk')->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'settlement_treasury_account_id'], 'instruments_settlement_treasury_fk')->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('delivery_account_transaction_id')->references('id')->on('account_transactions')->restrictOnDelete();
            $table->foreign('endorsement_account_transaction_id')->references('id')->on('account_transactions')->restrictOnDelete();
            $table->foreign('settlement_treasury_movement_id')->references('id')->on('treasury_movements')->restrictOnDelete();
            $table->foreign('delivery_reversal_account_transaction_id')->references('id')->on('account_transactions')->restrictOnDelete();
            $table->foreign('endorsement_reversal_account_transaction_id')->references('id')->on('account_transactions')->restrictOnDelete();
            $table->index(['company_id', 'status', 'due_date'], 'instruments_company_status_due_index');
            $table->index(['company_id', 'account_id', 'due_date'], 'instruments_company_account_due_index');
            $table->index(['company_id', 'document_no'], 'instruments_company_document_index');
        });

        DB::statement("ALTER TABLE instruments ADD CONSTRAINT instruments_direction_check CHECK (direction IN ('received','issued'))");
        DB::statement("ALTER TABLE instruments ADD CONSTRAINT instruments_kind_check CHECK (kind IN ('cheque','promissory_note'))");
        DB::statement("ALTER TABLE instruments ADD CONSTRAINT instruments_status_check CHECK (status IN ('draft','portfolio','bank_collection','endorsed','collected','issued','settled','dishonored','unpaid','returned','cancelled'))");
        DB::statement('ALTER TABLE instruments ADD CONSTRAINT instruments_document_no_check CHECK (document_no = btrim(document_no) AND char_length(document_no) > 0)');
        DB::statement('ALTER TABLE instruments ADD CONSTRAINT instruments_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE instruments ADD CONSTRAINT instruments_currency_check CHECK (currency_code ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE instruments ADD CONSTRAINT instruments_date_check CHECK (issue_date IS NULL OR due_date >= issue_date)');
        DB::statement("ALTER TABLE instruments ADD CONSTRAINT instruments_direction_status_check CHECK (status = 'draft' OR (direction = 'received' AND status IN ('portfolio','bank_collection','endorsed','collected','dishonored','returned','cancelled')) OR (direction = 'issued' AND status IN ('issued','settled','unpaid','cancelled')))");
        DB::statement("ALTER TABLE instruments ADD CONSTRAINT instruments_registration_shape_check CHECK ((status = 'draft' AND delivery_account_transaction_id IS NULL AND registered_at IS NULL) OR (status <> 'draft' AND delivery_account_transaction_id IS NOT NULL AND registered_at IS NOT NULL))");
        DB::statement(<<<'SQL'
ALTER TABLE instruments ADD CONSTRAINT instruments_holder_shape_check CHECK (
    (status = 'draft' AND current_holder_type IS NULL AND current_holder_account_id IS NULL AND current_treasury_account_id IS NULL)
    OR (status = 'portfolio' AND current_holder_type = 'company' AND current_holder_account_id IS NULL AND current_treasury_account_id IS NULL)
    OR (status = 'bank_collection' AND current_holder_type = 'bank' AND current_holder_account_id IS NULL AND current_treasury_account_id IS NOT NULL)
    OR (status = 'endorsed' AND current_holder_type = 'account' AND current_holder_account_id IS NOT NULL AND current_holder_account_id = endorsed_to_account_id AND current_treasury_account_id IS NULL)
    OR (status IN ('collected','settled') AND current_holder_type = 'settled' AND current_holder_account_id IS NULL AND current_treasury_account_id IS NULL)
    OR (status = 'dishonored' AND current_holder_type = 'company' AND current_holder_account_id IS NULL AND current_treasury_account_id IS NULL)
    OR (status IN ('issued','unpaid') AND current_holder_type = 'account' AND current_holder_account_id = account_id AND current_treasury_account_id IS NULL)
    OR (status = 'returned' AND current_holder_type = 'account' AND current_holder_account_id = account_id AND current_treasury_account_id IS NULL)
    OR (status = 'cancelled' AND current_holder_type = 'none' AND current_holder_account_id IS NULL AND current_treasury_account_id IS NULL)
)
SQL);
        DB::statement("ALTER TABLE instruments ADD CONSTRAINT instruments_settlement_shape_check CHECK ((status IN ('collected','settled') AND settlement_treasury_movement_id IS NOT NULL AND settlement_treasury_account_id IS NOT NULL AND settled_at IS NOT NULL AND reversed_at IS NULL) OR (status NOT IN ('collected','settled') AND settlement_treasury_movement_id IS NULL AND settled_at IS NULL))");
        DB::statement("ALTER TABLE instruments ADD CONSTRAINT instruments_reversal_shape_check CHECK ((status IN ('dishonored','unpaid','returned','cancelled') AND delivery_reversal_account_transaction_id IS NOT NULL AND reversed_at IS NOT NULL AND settled_at IS NULL) OR (status NOT IN ('dishonored','unpaid','returned','cancelled') AND delivery_reversal_account_transaction_id IS NULL AND endorsement_reversal_account_transaction_id IS NULL AND reversed_at IS NULL))");
        DB::statement('ALTER TABLE instruments ADD CONSTRAINT instruments_endorsement_shape_check CHECK ((endorsement_account_transaction_id IS NULL AND endorsed_to_account_id IS NULL AND endorsement_reversal_account_transaction_id IS NULL) OR (endorsement_account_transaction_id IS NOT NULL AND endorsed_to_account_id IS NOT NULL))');

        Schema::create('instrument_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('instrument_id');
            $table->string('event_type', 40);
            $table->date('event_date');
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->unsignedBigInteger('counterparty_account_id')->nullable();
            $table->unsignedBigInteger('treasury_account_id')->nullable();
            $table->unsignedBigInteger('account_transaction_id')->nullable();
            $table->unsignedBigInteger('treasury_movement_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->foreign(['company_id', 'instrument_id'])->references(['company_id', 'id'])->on('instruments')->restrictOnDelete();
            $table->foreign(['company_id', 'counterparty_account_id'], 'instrument_events_counterparty_fk')->references(['company_id', 'id'])->on('accounts')->restrictOnDelete();
            $table->foreign(['company_id', 'treasury_account_id'], 'instrument_events_treasury_fk')->references(['company_id', 'id'])->on('treasury_accounts')->restrictOnDelete();
            $table->foreign('account_transaction_id')->references('id')->on('account_transactions')->restrictOnDelete();
            $table->foreign('treasury_movement_id')->references('id')->on('treasury_movements')->restrictOnDelete();
            $table->index(['company_id', 'instrument_id', 'id'], 'instrument_events_history_index');
            $table->index(['company_id', 'event_date', 'event_type'], 'instrument_events_date_type_index');
        });
        DB::statement("ALTER TABLE instrument_events ADD CONSTRAINT instrument_events_type_check CHECK (event_type IN ('registered','sent_to_bank','recalled_from_bank','endorsed','settled','dishonored','returned','cancelled'))");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_instrument_lifecycle()
RETURNS trigger AS $$
BEGIN
    IF OLD.status <> 'draft' AND (
        NEW.company_id IS DISTINCT FROM OLD.company_id OR NEW.account_id IS DISTINCT FROM OLD.account_id
        OR NEW.direction IS DISTINCT FROM OLD.direction OR NEW.kind IS DISTINCT FROM OLD.kind
        OR NEW.document_no IS DISTINCT FROM OLD.document_no OR NEW.amount IS DISTINCT FROM OLD.amount
        OR NEW.currency_code IS DISTINCT FROM OLD.currency_code OR NEW.issue_date IS DISTINCT FROM OLD.issue_date
        OR NEW.delivery_date IS DISTINCT FROM OLD.delivery_date OR NEW.due_date IS DISTINCT FROM OLD.due_date
        OR NEW.bank_name IS DISTINCT FROM OLD.bank_name OR NEW.branch_name IS DISTINCT FROM OLD.branch_name
        OR NEW.drawer_or_maker IS DISTINCT FROM OLD.drawer_or_maker OR NEW.note IS DISTINCT FROM OLD.note
    ) THEN
        RAISE EXCEPTION 'registered instrument commercial identity is immutable' USING ERRCODE = '55000';
    END IF;

    IF OLD.delivery_account_transaction_id IS NOT NULL AND NEW.delivery_account_transaction_id IS DISTINCT FROM OLD.delivery_account_transaction_id THEN RAISE EXCEPTION 'instrument delivery posting link is immutable' USING ERRCODE = '55000'; END IF;
    IF OLD.endorsement_account_transaction_id IS NOT NULL AND NEW.endorsement_account_transaction_id IS DISTINCT FROM OLD.endorsement_account_transaction_id THEN RAISE EXCEPTION 'instrument endorsement posting link is immutable' USING ERRCODE = '55000'; END IF;
    IF OLD.settlement_treasury_movement_id IS NOT NULL AND NEW.settlement_treasury_movement_id IS DISTINCT FROM OLD.settlement_treasury_movement_id THEN RAISE EXCEPTION 'instrument settlement posting link is immutable' USING ERRCODE = '55000'; END IF;
    IF OLD.delivery_reversal_account_transaction_id IS NOT NULL AND NEW.delivery_reversal_account_transaction_id IS DISTINCT FROM OLD.delivery_reversal_account_transaction_id THEN RAISE EXCEPTION 'instrument delivery reversal link is immutable' USING ERRCODE = '55000'; END IF;
    IF OLD.endorsement_reversal_account_transaction_id IS NOT NULL AND NEW.endorsement_reversal_account_transaction_id IS DISTINCT FROM OLD.endorsement_reversal_account_transaction_id THEN RAISE EXCEPTION 'instrument endorsement reversal link is immutable' USING ERRCODE = '55000'; END IF;

    IF NEW.status IS DISTINCT FROM OLD.status AND NOT (
        (OLD.status = 'draft' AND ((NEW.direction = 'received' AND NEW.status = 'portfolio') OR (NEW.direction = 'issued' AND NEW.status = 'issued')))
        OR (OLD.status = 'portfolio' AND NEW.status IN ('bank_collection','endorsed','dishonored','returned','cancelled'))
        OR (OLD.status = 'bank_collection' AND NEW.status IN ('portfolio','collected','dishonored','cancelled'))
        OR (OLD.status = 'endorsed' AND NEW.status IN ('dishonored','cancelled'))
        OR (OLD.status = 'issued' AND NEW.status IN ('settled','unpaid','cancelled'))
    ) THEN
        RAISE EXCEPTION 'illegal instrument lifecycle transition: % -> %', OLD.status, NEW.status USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER instruments_lifecycle_guard BEFORE UPDATE ON instruments FOR EACH ROW EXECUTE FUNCTION mars_guard_instrument_lifecycle();

CREATE OR REPLACE FUNCTION mars_guard_instrument_ledger_links()
RETURNS trigger AS $$
DECLARE
    delivery account_transactions%ROWTYPE;
    endorsement account_transactions%ROWTYPE;
    settlement treasury_movements%ROWTYPE;
    expected_delivery numeric(20,6);
    expected_settlement numeric(20,6);
BEGIN
    IF NEW.delivery_account_transaction_id IS NOT NULL THEN
        SELECT * INTO delivery FROM account_transactions WHERE id = NEW.delivery_account_transaction_id;
        expected_delivery := CASE WHEN NEW.direction = 'received' THEN -NEW.amount ELSE NEW.amount END;
        IF NOT FOUND OR delivery.company_id IS DISTINCT FROM NEW.company_id OR delivery.account_id IS DISTINCT FROM NEW.account_id
           OR delivery.currency_code IS DISTINCT FROM NEW.currency_code OR delivery.signed_amount IS DISTINCT FROM expected_delivery
           OR delivery.posting_date IS DISTINCT FROM NEW.delivery_date OR delivery.source_type IS DISTINCT FROM 'instrument'
           OR delivery.source_id IS DISTINCT FROM NEW.id::text OR delivery.effect_type IS DISTINCT FROM 'account.instrument_delivery' THEN
            RAISE EXCEPTION 'instrument delivery ledger link mismatch' USING ERRCODE = '23514';
        END IF;
    END IF;

    IF NEW.endorsement_account_transaction_id IS NOT NULL THEN
        SELECT * INTO endorsement FROM account_transactions WHERE id = NEW.endorsement_account_transaction_id;
        IF NOT FOUND OR NEW.direction <> 'received' OR NEW.endorsed_to_account_id IS NULL
           OR endorsement.company_id IS DISTINCT FROM NEW.company_id OR endorsement.account_id IS DISTINCT FROM NEW.endorsed_to_account_id
           OR endorsement.currency_code IS DISTINCT FROM NEW.currency_code OR endorsement.signed_amount IS DISTINCT FROM NEW.amount
           OR endorsement.source_type IS DISTINCT FROM 'instrument' OR endorsement.source_id IS DISTINCT FROM NEW.id::text
           OR endorsement.effect_type IS DISTINCT FROM 'account.instrument_endorsement' THEN
            RAISE EXCEPTION 'instrument endorsement ledger link mismatch' USING ERRCODE = '23514';
        END IF;
    END IF;

    IF NEW.settlement_treasury_movement_id IS NOT NULL THEN
        SELECT * INTO settlement FROM treasury_movements WHERE id = NEW.settlement_treasury_movement_id;
        expected_settlement := CASE WHEN NEW.direction = 'received' THEN NEW.amount ELSE -NEW.amount END;
        IF NOT FOUND OR NEW.settlement_treasury_account_id IS NULL OR settlement.company_id IS DISTINCT FROM NEW.company_id
           OR settlement.treasury_account_id IS DISTINCT FROM NEW.settlement_treasury_account_id
           OR settlement.currency_code IS DISTINCT FROM NEW.currency_code OR settlement.signed_amount IS DISTINCT FROM expected_settlement
           OR settlement.source_type IS DISTINCT FROM 'instrument' OR settlement.source_id IS DISTINCT FROM NEW.id::text
           OR settlement.effect_type IS DISTINCT FROM 'treasury.instrument_settlement' THEN
            RAISE EXCEPTION 'instrument settlement ledger link mismatch' USING ERRCODE = '23514';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER instruments_ledger_link_guard BEFORE INSERT OR UPDATE ON instruments FOR EACH ROW EXECUTE FUNCTION mars_guard_instrument_ledger_links();

CREATE OR REPLACE FUNCTION mars_prevent_instrument_event_mutation()
RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'instrument_events are immutable' USING ERRCODE = '55000'; END; $$ LANGUAGE plpgsql;
CREATE TRIGGER instrument_events_immutable_guard BEFORE UPDATE OR DELETE ON instrument_events FOR EACH ROW EXECUTE FUNCTION mars_prevent_instrument_event_mutation();

CREATE OR REPLACE FUNCTION mars_guard_instrument_attachment_target()
RETURNS trigger AS $$
BEGIN
    IF NEW.attachable_type = 'instrument' AND NOT EXISTS (SELECT 1 FROM instruments WHERE instruments.company_id = NEW.company_id AND instruments.id = NEW.attachable_id) THEN
        RAISE EXCEPTION 'Instrument attachment target does not belong to company' USING ERRCODE = '23503';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE CONSTRAINT TRIGGER attachments_instrument_target_guard AFTER INSERT OR UPDATE OF company_id, attachable_type, attachable_id ON attachments DEFERRABLE INITIALLY IMMEDIATE FOR EACH ROW EXECUTE FUNCTION mars_guard_instrument_attachment_target();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS attachments_instrument_target_guard ON attachments');
        DB::unprepared('DROP FUNCTION IF EXISTS mars_guard_instrument_attachment_target()');
        DB::unprepared('DROP TRIGGER IF EXISTS instrument_events_immutable_guard ON instrument_events');
        DB::unprepared('DROP FUNCTION IF EXISTS mars_prevent_instrument_event_mutation()');
        DB::unprepared('DROP TRIGGER IF EXISTS instruments_ledger_link_guard ON instruments');
        DB::unprepared('DROP FUNCTION IF EXISTS mars_guard_instrument_ledger_links()');
        DB::unprepared('DROP TRIGGER IF EXISTS instruments_lifecycle_guard ON instruments');
        DB::unprepared('DROP FUNCTION IF EXISTS mars_guard_instrument_lifecycle()');
        Schema::dropIfExists('instrument_events');
        Schema::dropIfExists('instruments');
        $permissionIds = DB::table('permissions')->whereIn('key', ['instruments.view', 'instruments.manage'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
