<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Custom quote goods are physical products on a document, NOT stock catalogue items.
        DB::connection('period')->statement('CREATE SEQUENCE IF NOT EXISTS quote_custom_code_seq');
        DB::connection('period')->statement('ALTER TABLE document_lines DROP CONSTRAINT document_lines_kind_valid');
        DB::connection('period')->statement(
            "ALTER TABLE document_lines ADD CONSTRAINT document_lines_kind_valid
             CHECK (line_kind IN ('stock','service','custom'))"
        );
        DB::connection('period')->statement('ALTER TABLE document_lines DROP CONSTRAINT document_lines_stock_shape_valid');
        DB::connection('period')->statement(
            "ALTER TABLE document_lines ADD CONSTRAINT document_lines_stock_shape_valid CHECK (
                (line_kind = 'stock' AND product_id IS NOT NULL AND unit_id IS NOT NULL
                    AND conversion_factor > 0 AND base_quantity > 0)
                OR
                (line_kind = 'service' AND conversion_factor IS NULL
                    AND base_quantity IS NULL AND location_id IS NULL)
                OR
                (line_kind = 'custom' AND product_id IS NULL AND unit_id IS NOT NULL
                    AND conversion_factor IS NULL AND base_quantity IS NULL AND location_id IS NULL
                    AND reserve_stock = false AND configuration->>'custom_code' ~ '^OZ-[0-9]{4}-[0-9]{6}$')
            )"
        );
    }

    public function down(): void
    {
        if (DB::connection('period')->table('document_lines')->where('line_kind', 'custom')->exists()) {
            throw new RuntimeException('Özel üretim satırları varken migration geri alınamaz.');
        }
        DB::connection('period')->statement('ALTER TABLE document_lines DROP CONSTRAINT document_lines_stock_shape_valid');
        DB::connection('period')->statement(
            "ALTER TABLE document_lines ADD CONSTRAINT document_lines_stock_shape_valid CHECK (
                (line_kind = 'stock' AND product_id IS NOT NULL AND unit_id IS NOT NULL AND conversion_factor > 0 AND base_quantity > 0)
                OR
                (line_kind = 'service' AND conversion_factor IS NULL AND base_quantity IS NULL AND location_id IS NULL)
            )"
        );
        DB::connection('period')->statement('ALTER TABLE document_lines DROP CONSTRAINT document_lines_kind_valid');
        DB::connection('period')->statement(
            "ALTER TABLE document_lines ADD CONSTRAINT document_lines_kind_valid CHECK (line_kind IN ('stock','service'))"
        );
        DB::connection('period')->statement('DROP SEQUENCE IF EXISTS quote_custom_code_seq');
    }
};
