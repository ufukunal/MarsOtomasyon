<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class M26LabelPostgresSmokeTest extends TestCase
{
    public function test_m26_label_tables_are_present_after_fresh_migration(): void
    {
        foreach (['label_templates', 'printer_profiles', 'label_prints'] as $table) {
            self::assertTrue(Schema::hasTable($table), "Expected PostgreSQL table [{$table}] to exist.");
        }
    }

    public function test_m26_label_print_snapshot_and_reprint_columns_are_present(): void
    {
        foreach ([
            'company_id',
            'label_template_id',
            'printer_profile_id',
            'target_type',
            'target_id',
            'barcode_id',
            'format',
            'payload_snapshot',
            'template_snapshot',
            'printer_snapshot',
            'output_base64',
            'content_hash',
            'reprint_of_id',
            'created_by_user_id',
        ] as $column) {
            self::assertTrue(
                Schema::hasColumn('label_prints', $column),
                "Expected PostgreSQL column [label_prints.{$column}] to exist.",
            );
        }
    }
}
