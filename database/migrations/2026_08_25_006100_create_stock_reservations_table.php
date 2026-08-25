<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id');
            $table->decimal('quantity', 20, 6);
            $table->string('status', 24)->default('active');

            $table->string('reserve_source_type', 64);
            $table->string('reserve_source_id', 255);
            $table->string('reserve_effect_type', 64);

            $table->string('release_source_type', 64)->nullable();
            $table->string('release_source_id', 255)->nullable();
            $table->string('release_effect_type', 64)->nullable();

            $table->string('consume_source_type', 64)->nullable();
            $table->string('consume_source_id', 255)->nullable();
            $table->string('consume_effect_type', 64)->nullable();

            $table->timestampTz('reserved_at');
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['company_id', 'product_id', 'warehouse_id', 'location_id'])
                ->references(['company_id', 'product_id', 'warehouse_id', 'location_id'])
                ->on('stock_balances')
                ->restrictOnDelete();

            $table->unique(
                ['company_id', 'reserve_source_type', 'reserve_source_id', 'reserve_effect_type'],
                'stock_reservations_reserve_source_effect_unique',
            );
            $table->index(
                ['company_id', 'product_id', 'warehouse_id', 'location_id', 'status'],
                'stock_reservations_scope_status_index',
            );
        });

        DB::statement('ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_quantity_positive_check CHECK (quantity > 0)');
        DB::statement("ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_status_check CHECK (status IN ('active', 'released', 'consumed'))");
        DB::statement(<<<'SQL'
            ALTER TABLE stock_reservations
            ADD CONSTRAINT stock_reservations_lifecycle_shape_check
            CHECK (
                (
                    status = 'active'
                    AND released_at IS NULL
                    AND consumed_at IS NULL
                    AND release_source_type IS NULL
                    AND release_source_id IS NULL
                    AND release_effect_type IS NULL
                    AND consume_source_type IS NULL
                    AND consume_source_id IS NULL
                    AND consume_effect_type IS NULL
                )
                OR
                (
                    status = 'released'
                    AND released_at IS NOT NULL
                    AND consumed_at IS NULL
                    AND release_source_type IS NOT NULL
                    AND release_source_id IS NOT NULL
                    AND release_effect_type IS NOT NULL
                    AND consume_source_type IS NULL
                    AND consume_source_id IS NULL
                    AND consume_effect_type IS NULL
                )
                OR
                (
                    status = 'consumed'
                    AND consumed_at IS NOT NULL
                    AND released_at IS NULL
                    AND consume_source_type IS NOT NULL
                    AND consume_source_id IS NOT NULL
                    AND consume_effect_type IS NOT NULL
                    AND release_source_type IS NULL
                    AND release_source_id IS NULL
                    AND release_effect_type IS NULL
                )
            )
            SQL);

        foreach (['reserve', 'release', 'consume'] as $phase) {
            DB::statement("ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_{$phase}_source_type_canonical_check CHECK ({$phase}_source_type IS NULL OR {$phase}_source_type ~ '^[a-z0-9]+([._-][a-z0-9]+)*$')");
            DB::statement("ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_{$phase}_effect_type_canonical_check CHECK ({$phase}_effect_type IS NULL OR {$phase}_effect_type ~ '^[a-z0-9]+([._-][a-z0-9]+)*$')");
            DB::statement("ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_{$phase}_source_id_not_blank_check CHECK ({$phase}_source_id IS NULL OR (char_length(btrim({$phase}_source_id)) > 0 AND {$phase}_source_id = btrim({$phase}_source_id)))");
        }

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX stock_reservations_release_source_effect_unique
            ON stock_reservations (company_id, release_source_type, release_source_id, release_effect_type)
            WHERE release_source_type IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX stock_reservations_consume_source_effect_unique
            ON stock_reservations (company_id, consume_source_type, consume_source_id, consume_effect_type)
            WHERE consume_source_type IS NOT NULL
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION mars_enforce_stock_reservation_projection()
            RETURNS trigger AS $$
            DECLARE
                affected_balance_id bigint;
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    IF NEW.status <> 'active' THEN
                        RAISE EXCEPTION 'stock reservation must start active';
                    END IF;

                    UPDATE stock_balances
                    SET reserved_quantity = reserved_quantity + NEW.quantity,
                        updated_at = NEW.updated_at
                    WHERE company_id = NEW.company_id
                      AND product_id = NEW.product_id
                      AND warehouse_id = NEW.warehouse_id
                      AND location_id = NEW.location_id
                      AND available_quantity >= NEW.quantity
                    RETURNING id INTO affected_balance_id;

                    IF affected_balance_id IS NULL THEN
                        RAISE EXCEPTION 'stock reservation exceeds available quantity';
                    END IF;

                    RETURN NEW;
                END IF;

                IF TG_OP = 'UPDATE' THEN
                    IF OLD.company_id IS DISTINCT FROM NEW.company_id
                        OR OLD.product_id IS DISTINCT FROM NEW.product_id
                        OR OLD.warehouse_id IS DISTINCT FROM NEW.warehouse_id
                        OR OLD.location_id IS DISTINCT FROM NEW.location_id
                        OR OLD.quantity IS DISTINCT FROM NEW.quantity
                        OR OLD.reserve_source_type IS DISTINCT FROM NEW.reserve_source_type
                        OR OLD.reserve_source_id IS DISTINCT FROM NEW.reserve_source_id
                        OR OLD.reserve_effect_type IS DISTINCT FROM NEW.reserve_effect_type
                        OR OLD.reserved_at IS DISTINCT FROM NEW.reserved_at
                        OR OLD.created_at IS DISTINCT FROM NEW.created_at
                    THEN
                        RAISE EXCEPTION 'stock reservation identity and quantity are immutable';
                    END IF;

                    IF OLD.status <> 'active' OR NEW.status NOT IN ('released', 'consumed') THEN
                        RAISE EXCEPTION 'stock reservation terminal transition is invalid';
                    END IF;

                    UPDATE stock_balances
                    SET reserved_quantity = reserved_quantity - OLD.quantity,
                        updated_at = NEW.updated_at
                    WHERE company_id = OLD.company_id
                      AND product_id = OLD.product_id
                      AND warehouse_id = OLD.warehouse_id
                      AND location_id = OLD.location_id
                      AND reserved_quantity >= OLD.quantity
                    RETURNING id INTO affected_balance_id;

                    IF affected_balance_id IS NULL THEN
                        RAISE EXCEPTION 'stock reservation projection is inconsistent';
                    END IF;

                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'stock reservations cannot be deleted';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER stock_reservations_projection_trigger
            BEFORE INSERT OR UPDATE OR DELETE ON stock_reservations
            FOR EACH ROW EXECUTE FUNCTION mars_enforce_stock_reservation_projection();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS stock_reservations_projection_trigger ON stock_reservations');
        DB::statement('DROP FUNCTION IF EXISTS mars_enforce_stock_reservation_projection()');
        Schema::dropIfExists('stock_reservations');
    }
};
