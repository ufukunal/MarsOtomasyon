<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('period')->statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::connection('period')->statement(<<<'SQL'
            ALTER TABLE price_list_items
            ADD CONSTRAINT price_list_items_no_overlap
            EXCLUDE USING gist (
                price_list_id WITH =,
                product_id WITH =,
                daterange(
                    COALESCE(valid_from, '-infinity'::date),
                    COALESCE(valid_to, 'infinity'::date),
                    '[]'
                ) WITH &&
            )
        SQL);
    }

    public function down(): void
    {
        DB::connection('period')->statement(
            'ALTER TABLE price_list_items DROP CONSTRAINT IF EXISTS price_list_items_no_overlap'
        );
    }
};
