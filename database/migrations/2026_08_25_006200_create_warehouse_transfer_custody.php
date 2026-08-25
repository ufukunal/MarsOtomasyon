<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('source_warehouse_id');
            $table->unsignedBigInteger('source_location_id');
            $table->unsignedBigInteger('destination_warehouse_id');
            $table->unsignedBigInteger('destination_location_id');
            $table->string('status', 24)->default('in_transit');
            $table->string('issue_source_type', 64);
            $table->string('issue_source_id', 255);
            $table->string('issue_effect_type', 64);
            $table->timestampTz('issued_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'warehouse_transfers_company_id_id_unique');
            $table->unique(
                ['company_id', 'issue_source_type', 'issue_source_id', 'issue_effect_type'],
                'warehouse_transfers_issue_source_effect_unique',
            );
            $table->foreign(['company_id', 'source_warehouse_id', 'source_location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])
                ->on('warehouse_locations')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'destination_warehouse_id', 'destination_location_id'])
                ->references(['company_id', 'warehouse_id', 'id'])
                ->on('warehouse_locations')
                ->restrictOnDelete();
            $table->index(['company_id', 'status', 'issued_at'], 'warehouse_transfers_company_status_index');
        });

        DB::statement("ALTER TABLE warehouse_transfers ADD CONSTRAINT warehouse_transfers_status_check CHECK (status IN ('in_transit', 'partially_received', 'received'))");
        DB::statement('ALTER TABLE warehouse_transfers ADD CONSTRAINT warehouse_transfers_distinct_scope_check CHECK (source_warehouse_id <> destination_warehouse_id OR source_location_id <> destination_location_id)');
        DB::statement("ALTER TABLE warehouse_transfers ADD CONSTRAINT warehouse_transfers_issue_source_type_canonical_check CHECK (issue_source_type ~ '^[a-z0-9]+([._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE warehouse_transfers ADD CONSTRAINT warehouse_transfers_issue_effect_type_canonical_check CHECK (issue_effect_type ~ '^[a-z0-9]+([._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE warehouse_transfers ADD CONSTRAINT warehouse_transfers_issue_source_id_not_blank_check CHECK (char_length(btrim(issue_source_id)) > 0 AND issue_source_id = btrim(issue_source_id))');
        DB::statement("ALTER TABLE warehouse_transfers ADD CONSTRAINT warehouse_transfers_completion_shape_check CHECK ((status = 'received' AND completed_at IS NOT NULL) OR (status <> 'received' AND completed_at IS NULL))");

        Schema::create('warehouse_transfer_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedInteger('line_number');
            $table->unsignedBigInteger('product_id');
            $table->decimal('issued_quantity', 20, 6);
            $table->decimal('unit_cost', 20, 6);
            $table->decimal('issued_value', 20, 6);
            $table->decimal('received_quantity', 20, 6)->default(0);
            $table->decimal('received_value', 20, 6)->default(0);
            $table->unsignedBigInteger('issue_movement_id');
            $table->timestampsTz();

            $table->unique(['company_id', 'transfer_id', 'id'], 'warehouse_transfer_lines_company_transfer_id_unique');
            $table->unique(['company_id', 'transfer_id', 'line_number'], 'warehouse_transfer_lines_number_unique');
            $table->unique('issue_movement_id', 'warehouse_transfer_lines_issue_movement_unique');
            $table->foreign(['company_id', 'transfer_id'])
                ->references(['company_id', 'id'])
                ->on('warehouse_transfers')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])
                ->on('products')
                ->restrictOnDelete();
            $table->foreign('issue_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
            $table->index(['company_id', 'product_id'], 'warehouse_transfer_lines_company_product_index');
        });

        DB::statement('ALTER TABLE warehouse_transfer_lines ADD COLUMN in_transit_quantity numeric(20,6) GENERATED ALWAYS AS (issued_quantity - received_quantity) STORED');
        DB::statement('ALTER TABLE warehouse_transfer_lines ADD COLUMN in_transit_value numeric(20,6) GENERATED ALWAYS AS (issued_value - received_value) STORED');
        DB::statement('ALTER TABLE warehouse_transfer_lines ADD CONSTRAINT warehouse_transfer_lines_issue_positive_check CHECK (issued_quantity > 0 AND unit_cost > 0 AND issued_value > 0)');
        DB::statement('ALTER TABLE warehouse_transfer_lines ADD CONSTRAINT warehouse_transfer_lines_receipt_bounds_check CHECK (received_quantity >= 0 AND received_quantity <= issued_quantity AND received_value >= 0 AND received_value <= issued_value)');

        Schema::create('warehouse_transfer_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedBigInteger('line_id');
            $table->string('source_type', 64);
            $table->string('source_id', 255);
            $table->string('effect_type', 64);
            $table->decimal('quantity', 20, 6);
            $table->decimal('carrying_value', 20, 6);
            $table->unsignedBigInteger('receipt_movement_id');
            $table->timestampTz('received_at');
            $table->timestampTz('created_at');

            $table->unique(
                ['company_id', 'source_type', 'source_id', 'effect_type'],
                'warehouse_transfer_receipts_source_effect_unique',
            );
            $table->unique('receipt_movement_id', 'warehouse_transfer_receipts_movement_unique');
            $table->foreign(['company_id', 'transfer_id'])
                ->references(['company_id', 'id'])
                ->on('warehouse_transfers')
                ->restrictOnDelete();
            $table->foreign(['company_id', 'transfer_id', 'line_id'])
                ->references(['company_id', 'transfer_id', 'id'])
                ->on('warehouse_transfer_lines')
                ->restrictOnDelete();
            $table->foreign('receipt_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
            $table->index(['company_id', 'transfer_id', 'line_id', 'received_at'], 'warehouse_transfer_receipts_line_index');
        });

        DB::statement('ALTER TABLE warehouse_transfer_receipts ADD CONSTRAINT warehouse_transfer_receipts_positive_check CHECK (quantity > 0 AND carrying_value > 0)');
        DB::statement("ALTER TABLE warehouse_transfer_receipts ADD CONSTRAINT warehouse_transfer_receipts_source_type_canonical_check CHECK (source_type ~ '^[a-z0-9]+([._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE warehouse_transfer_receipts ADD CONSTRAINT warehouse_transfer_receipts_effect_type_canonical_check CHECK (effect_type ~ '^[a-z0-9]+([._-][a-z0-9]+)*$')");
        DB::statement('ALTER TABLE warehouse_transfer_receipts ADD CONSTRAINT warehouse_transfer_receipts_source_id_not_blank_check CHECK (char_length(btrim(source_id)) > 0 AND source_id = btrim(source_id))');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION mars_guard_warehouse_transfer_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'warehouse transfers cannot be deleted after issue';
                END IF;

                IF pg_trigger_depth() <= 1 THEN
                    RAISE EXCEPTION 'warehouse transfer lifecycle projection is system managed';
                END IF;

                IF OLD.company_id IS DISTINCT FROM NEW.company_id
                    OR OLD.source_warehouse_id IS DISTINCT FROM NEW.source_warehouse_id
                    OR OLD.source_location_id IS DISTINCT FROM NEW.source_location_id
                    OR OLD.destination_warehouse_id IS DISTINCT FROM NEW.destination_warehouse_id
                    OR OLD.destination_location_id IS DISTINCT FROM NEW.destination_location_id
                    OR OLD.issue_source_type IS DISTINCT FROM NEW.issue_source_type
                    OR OLD.issue_source_id IS DISTINCT FROM NEW.issue_source_id
                    OR OLD.issue_effect_type IS DISTINCT FROM NEW.issue_effect_type
                    OR OLD.issued_at IS DISTINCT FROM NEW.issued_at
                    OR OLD.created_at IS DISTINCT FROM NEW.created_at
                THEN
                    RAISE EXCEPTION 'warehouse transfer identity and route are immutable';
                END IF;

                IF OLD.status = 'received' OR
                    (OLD.status = 'partially_received' AND NEW.status = 'in_transit') OR
                    NEW.status = 'in_transit'
                THEN
                    RAISE EXCEPTION 'warehouse transfer lifecycle transition is invalid';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER warehouse_transfers_mutation_guard
            BEFORE UPDATE OR DELETE ON warehouse_transfers
            FOR EACH ROW EXECUTE FUNCTION mars_guard_warehouse_transfer_mutation();

            CREATE OR REPLACE FUNCTION mars_guard_warehouse_transfer_line()
            RETURNS trigger AS $$
            DECLARE
                transfer_row warehouse_transfers%ROWTYPE;
                movement_row stock_movements%ROWTYPE;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'warehouse transfer lines cannot be deleted after issue';
                END IF;

                IF TG_OP = 'INSERT' THEN
                    SELECT * INTO transfer_row
                    FROM warehouse_transfers
                    WHERE id = NEW.transfer_id AND company_id = NEW.company_id;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'warehouse transfer line target does not belong to company';
                    END IF;

                    SELECT * INTO movement_row
                    FROM stock_movements
                    WHERE id = NEW.issue_movement_id;

                    IF NOT FOUND
                        OR movement_row.company_id <> NEW.company_id
                        OR movement_row.product_id <> NEW.product_id
                        OR movement_row.warehouse_id <> transfer_row.source_warehouse_id
                        OR movement_row.location_id <> transfer_row.source_location_id
                        OR movement_row.movement_type <> 'transfer_out'
                        OR movement_row.quantity_delta <> -NEW.issued_quantity
                        OR movement_row.unit_cost <> NEW.unit_cost
                        OR movement_row.value_delta <> -NEW.issued_value
                    THEN
                        RAISE EXCEPTION 'warehouse transfer issue line must match its transfer_out stock movement exactly';
                    END IF;

                    RETURN NEW;
                END IF;

                IF OLD.company_id IS DISTINCT FROM NEW.company_id
                    OR OLD.transfer_id IS DISTINCT FROM NEW.transfer_id
                    OR OLD.line_number IS DISTINCT FROM NEW.line_number
                    OR OLD.product_id IS DISTINCT FROM NEW.product_id
                    OR OLD.issued_quantity IS DISTINCT FROM NEW.issued_quantity
                    OR OLD.unit_cost IS DISTINCT FROM NEW.unit_cost
                    OR OLD.issued_value IS DISTINCT FROM NEW.issued_value
                    OR OLD.issue_movement_id IS DISTINCT FROM NEW.issue_movement_id
                    OR OLD.created_at IS DISTINCT FROM NEW.created_at
                THEN
                    RAISE EXCEPTION 'warehouse transfer issue line is immutable';
                END IF;

                IF pg_trigger_depth() <= 1 AND
                    (OLD.received_quantity IS DISTINCT FROM NEW.received_quantity
                     OR OLD.received_value IS DISTINCT FROM NEW.received_value)
                THEN
                    RAISE EXCEPTION 'warehouse transfer receipt projection is system managed';
                END IF;

                IF NEW.received_quantity < OLD.received_quantity
                    OR NEW.received_value < OLD.received_value
                THEN
                    RAISE EXCEPTION 'warehouse transfer receipt projection cannot move backwards';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER warehouse_transfer_lines_guard
            BEFORE INSERT OR UPDATE OR DELETE ON warehouse_transfer_lines
            FOR EACH ROW EXECUTE FUNCTION mars_guard_warehouse_transfer_line();

            CREATE OR REPLACE FUNCTION mars_validate_warehouse_transfer_receipt()
            RETURNS trigger AS $$
            DECLARE
                transfer_row warehouse_transfers%ROWTYPE;
                line_row warehouse_transfer_lines%ROWTYPE;
                movement_row stock_movements%ROWTYPE;
                remaining_quantity numeric(20,6);
                expected_value numeric(20,6);
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'warehouse transfer receipts are append-only';
                END IF;

                SELECT * INTO transfer_row
                FROM warehouse_transfers
                WHERE id = NEW.transfer_id AND company_id = NEW.company_id
                FOR UPDATE;

                IF NOT FOUND OR transfer_row.status NOT IN ('in_transit', 'partially_received') THEN
                    RAISE EXCEPTION 'warehouse transfer is not open for receipt';
                END IF;

                SELECT * INTO line_row
                FROM warehouse_transfer_lines
                WHERE id = NEW.line_id
                  AND transfer_id = NEW.transfer_id
                  AND company_id = NEW.company_id
                FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'warehouse transfer receipt line does not belong to transfer';
                END IF;

                remaining_quantity := line_row.issued_quantity - line_row.received_quantity;
                IF NEW.quantity > remaining_quantity THEN
                    RAISE EXCEPTION 'warehouse transfer receipt exceeds in-transit quantity';
                END IF;

                IF NEW.quantity = remaining_quantity THEN
                    expected_value := line_row.issued_value - line_row.received_value;
                ELSE
                    expected_value := CAST(NEW.quantity * line_row.unit_cost AS numeric(20,6));
                END IF;

                IF NEW.carrying_value <> expected_value THEN
                    RAISE EXCEPTION 'warehouse transfer receipt carrying value does not reconcile';
                END IF;

                SELECT * INTO movement_row
                FROM stock_movements
                WHERE id = NEW.receipt_movement_id;

                IF NOT FOUND
                    OR movement_row.company_id <> NEW.company_id
                    OR movement_row.product_id <> line_row.product_id
                    OR movement_row.warehouse_id <> transfer_row.destination_warehouse_id
                    OR movement_row.location_id <> transfer_row.destination_location_id
                    OR movement_row.movement_type <> 'transfer_in'
                    OR movement_row.quantity_delta <> NEW.quantity
                    OR movement_row.unit_cost <> line_row.unit_cost
                    OR movement_row.value_delta <> NEW.carrying_value
                    OR movement_row.source_type <> NEW.source_type
                    OR movement_row.source_id <> NEW.source_id
                    OR movement_row.effect_type <> NEW.effect_type
                THEN
                    RAISE EXCEPTION 'warehouse transfer receipt must match its transfer_in stock movement exactly';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER warehouse_transfer_receipts_validation
            BEFORE INSERT OR UPDATE OR DELETE ON warehouse_transfer_receipts
            FOR EACH ROW EXECUTE FUNCTION mars_validate_warehouse_transfer_receipt();

            CREATE OR REPLACE FUNCTION mars_apply_warehouse_transfer_receipt()
            RETURNS trigger AS $$
            DECLARE
                has_remaining boolean;
            BEGIN
                UPDATE warehouse_transfer_lines
                SET received_quantity = received_quantity + NEW.quantity,
                    received_value = received_value + NEW.carrying_value,
                    updated_at = NEW.received_at
                WHERE id = NEW.line_id
                  AND transfer_id = NEW.transfer_id
                  AND company_id = NEW.company_id;

                SELECT EXISTS (
                    SELECT 1
                    FROM warehouse_transfer_lines
                    WHERE transfer_id = NEW.transfer_id
                      AND company_id = NEW.company_id
                      AND in_transit_quantity > 0
                ) INTO has_remaining;

                UPDATE warehouse_transfers
                SET status = CASE WHEN has_remaining THEN 'partially_received' ELSE 'received' END,
                    completed_at = CASE WHEN has_remaining THEN NULL ELSE NEW.received_at END,
                    updated_at = NEW.received_at
                WHERE id = NEW.transfer_id
                  AND company_id = NEW.company_id;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER warehouse_transfer_receipts_projection
            AFTER INSERT ON warehouse_transfer_receipts
            FOR EACH ROW EXECUTE FUNCTION mars_apply_warehouse_transfer_receipt();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS warehouse_transfer_receipts_projection ON warehouse_transfer_receipts');
        DB::statement('DROP TRIGGER IF EXISTS warehouse_transfer_receipts_validation ON warehouse_transfer_receipts');
        DB::statement('DROP TRIGGER IF EXISTS warehouse_transfer_lines_guard ON warehouse_transfer_lines');
        DB::statement('DROP TRIGGER IF EXISTS warehouse_transfers_mutation_guard ON warehouse_transfers');
        DB::statement('DROP FUNCTION IF EXISTS mars_apply_warehouse_transfer_receipt()');
        DB::statement('DROP FUNCTION IF EXISTS mars_validate_warehouse_transfer_receipt()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_warehouse_transfer_line()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_warehouse_transfer_mutation()');
        Schema::dropIfExists('warehouse_transfer_receipts');
        Schema::dropIfExists('warehouse_transfer_lines');
        Schema::dropIfExists('warehouse_transfers');
    }
};
