<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_admin')->default(false)->after('status');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->timestampTz('opened_at')->nullable();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check');
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft', 'open', 'closed'))");

        Schema::create('integration_entity_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->string('entity_type', 64);
            $table->string('external_id', 192);
            $table->string('local_type', 64);
            $table->unsignedBigInteger('local_id');
            $table->char('last_payload_sha256', 64);
            $table->timestampTz('last_synced_at');
            $table->timestampsTz();

            $table->foreign(['company_id', 'connection_id'])
                ->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->unique(['company_id', 'connection_id', 'entity_type', 'external_id'], 'integration_entity_links_external_unique');
            $table->unique(['company_id', 'connection_id', 'local_type', 'local_id'], 'integration_entity_links_local_unique');
            $table->index(['company_id', 'local_type', 'local_id']);
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_mutation()
RETURNS trigger AS $$
DECLARE
    has_progress boolean;
    has_remaining boolean;
    business_changed boolean;
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'purchase orders cannot be deleted' USING ERRCODE = '55000';
    END IF;

    IF NEW.company_id IS DISTINCT FROM OLD.company_id
        OR NEW.number IS DISTINCT FROM OLD.number
        OR NEW.series_code IS DISTINCT FROM OLD.series_code
        OR NEW.sequence_value IS DISTINCT FROM OLD.sequence_value THEN
        RAISE EXCEPTION 'purchase order document identity is immutable' USING ERRCODE = '55000';
    END IF;

    business_changed := NEW.account_id IS DISTINCT FROM OLD.account_id
        OR NEW.order_date IS DISTINCT FROM OLD.order_date
        OR NEW.currency_code IS DISTINCT FROM OLD.currency_code
        OR NEW.document_discount_rate IS DISTINCT FROM OLD.document_discount_rate
        OR NEW.base_net_total IS DISTINCT FROM OLD.base_net_total
        OR NEW.line_discount_total IS DISTINCT FROM OLD.line_discount_total
        OR NEW.document_discount_total IS DISTINCT FROM OLD.document_discount_total
        OR NEW.net_total IS DISTINCT FROM OLD.net_total
        OR NEW.tax_total IS DISTINCT FROM OLD.tax_total
        OR NEW.gross_total IS DISTINCT FROM OLD.gross_total
        OR NEW.note IS DISTINCT FROM OLD.note;

    SELECT EXISTS (
        SELECT 1 FROM purchase_order_line_progress_effects
        WHERE company_id = OLD.company_id AND purchase_order_id = OLD.id
    ) INTO has_progress;

    IF NEW.status = OLD.status THEN
        IF (OLD.status <> 'draft' OR has_progress) AND business_changed THEN
            RAISE EXCEPTION 'purchase order business fields are immutable outside editable draft state' USING ERRCODE = '55000';
        END IF;
        RETURN NEW;
    END IF;

    IF business_changed THEN
        RAISE EXCEPTION 'purchase order lifecycle transition cannot mutate business fields' USING ERRCODE = '55000';
    END IF;

    IF OLD.status = 'draft' AND NEW.status = 'open' THEN
        IF has_progress THEN
            RAISE EXCEPTION 'draft purchase order with progress cannot be opened' USING ERRCODE = '55000';
        END IF;
        IF NEW.opened_at IS NULL OR NEW.opened_by_user_id IS NULL OR NEW.closed_at IS NOT NULL OR NEW.closed_by_user_id IS NOT NULL THEN
            RAISE EXCEPTION 'opening purchase order requires actor/time and clears close metadata' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF OLD.status = 'open' AND NEW.status = 'closed' THEN
        SELECT EXISTS (
            SELECT 1
            FROM purchase_order_line_progress p
            WHERE p.company_id = OLD.company_id
              AND p.purchase_order_id = OLD.id
              AND (CAST(p.receive_remaining_quantity AS numeric) > 0 OR CAST(p.invoice_remaining_quantity AS numeric) > 0)
        ) INTO has_remaining;
        IF has_remaining THEN
            RAISE EXCEPTION 'purchase order cannot close while receive or invoice capacity remains' USING ERRCODE = '23514';
        END IF;
        IF NEW.closed_at IS NULL OR NEW.closed_by_user_id IS NULL THEN
            RAISE EXCEPTION 'closing purchase order requires actor and time' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF OLD.status = 'closed' AND NEW.status = 'open' THEN
        SELECT EXISTS (
            SELECT 1
            FROM purchase_order_line_progress p
            WHERE p.company_id = OLD.company_id
              AND p.purchase_order_id = OLD.id
              AND (CAST(p.receive_remaining_quantity AS numeric) > 0 OR CAST(p.invoice_remaining_quantity AS numeric) > 0)
        ) INTO has_remaining;
        IF NOT has_remaining THEN
            RAISE EXCEPTION 'closed purchase order can reopen only after capacity is restored' USING ERRCODE = '23514';
        END IF;
        IF NEW.opened_at IS NULL OR NEW.closed_at IS NOT NULL OR NEW.closed_by_user_id IS NOT NULL THEN
            RAISE EXCEPTION 'reopened purchase order must clear close metadata' USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'invalid purchase order lifecycle transition' USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_goods_receipt_open_purchase_order()
RETURNS trigger AS $$
DECLARE
    po_status text;
BEGIN
    SELECT status INTO po_status
    FROM purchase_orders
    WHERE company_id = NEW.company_id AND id = NEW.purchase_order_id
    FOR SHARE;

    IF po_status IS NULL THEN
        RAISE EXCEPTION 'goods receipt purchase order not found' USING ERRCODE = '23503';
    END IF;
    IF po_status <> 'open' THEN
        RAISE EXCEPTION 'goods receipt requires open purchase order' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER goods_receipts_open_purchase_order_guard
BEFORE INSERT OR UPDATE OF company_id, purchase_order_id ON goods_receipts
FOR EACH ROW EXECUTE FUNCTION mars_guard_goods_receipt_open_purchase_order();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS goods_receipts_open_purchase_order_guard ON goods_receipts');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_goods_receipt_open_purchase_order()');
        Schema::dropIfExists('integration_entity_links');

        DB::statement('DROP TRIGGER IF EXISTS purchase_orders_mutation_guard ON purchase_orders');
        DB::table('purchase_orders')->whereIn('status', ['open', 'closed'])->update([
            'status' => 'draft',
            'opened_at' => null,
            'opened_by_user_id' => null,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'updated_at' => now(),
        ]);

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closed_by_user_id');
            $table->dropColumn('closed_at');
            $table->dropConstrainedForeignId('opened_by_user_id');
            $table->dropColumn('opened_at');
        });
        DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_status_check');
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status = 'draft')");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_purchase_order_mutation()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'purchase orders cannot be deleted' USING ERRCODE = '55000';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM purchase_order_line_progress_effects
        WHERE company_id = OLD.company_id
          AND purchase_order_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'purchase order is immutable after progress starts' USING ERRCODE = '55000';
    END IF;

    IF OLD.status <> 'draft' OR NEW.status <> 'draft' THEN
        RAISE EXCEPTION 'only draft purchase orders are mutable' USING ERRCODE = '55000';
    END IF;

    IF NEW.company_id IS DISTINCT FROM OLD.company_id
        OR NEW.number IS DISTINCT FROM OLD.number
        OR NEW.series_code IS DISTINCT FROM OLD.series_code
        OR NEW.sequence_value IS DISTINCT FROM OLD.sequence_value THEN
        RAISE EXCEPTION 'purchase order document identity is immutable' USING ERRCODE = '55000';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER purchase_orders_mutation_guard
BEFORE UPDATE OR DELETE ON purchase_orders
FOR EACH ROW EXECUTE FUNCTION mars_guard_purchase_order_mutation();
SQL);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_platform_admin');
        });
    }
};