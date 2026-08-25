<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX products_search_vector_gin ON products USING GIN (to_tsvector('simple', coalesce(code, '') || ' ' || coalesce(name, '')))");
        DB::statement('CREATE INDEX products_name_trgm_gin ON products USING GIN (lower(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX products_code_trgm_gin ON products USING GIN (lower(code) gin_trgm_ops)');
        DB::statement('CREATE INDEX barcodes_value_trgm_gin ON barcodes USING GIN (lower(barcode) gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS barcodes_value_trgm_gin');
        DB::statement('DROP INDEX IF EXISTS products_code_trgm_gin');
        DB::statement('DROP INDEX IF EXISTS products_name_trgm_gin');
        DB::statement('DROP INDEX IF EXISTS products_search_vector_gin');
    }
};
