<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->table('import_files', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_period_id')->nullable()->after('number');
            $table->unsignedBigInteger('source_import_file_id')->nullable()->after('source_period_id');
            $table->string('source_number', 40)->nullable()->after('source_import_file_id');
            $table->unique(
                ['source_period_id', 'source_import_file_id'],
                'import_files_source_period_file_unique',
            );
            $table->index('source_number', 'import_files_source_number_index');
        });

        DB::connection('period')->statement(
            'ALTER TABLE import_files ADD CONSTRAINT import_files_carry_provenance_complete CHECK ('
            .'(source_period_id IS NULL AND source_import_file_id IS NULL AND source_number IS NULL) OR '
            .'(source_period_id IS NOT NULL AND source_period_id > 0 AND source_import_file_id IS NOT NULL '
            ."AND source_import_file_id > 0 AND source_number IS NOT NULL AND btrim(source_number) <> '')"
            .')'
        );
    }

    public function down(): void
    {
        DB::connection('period')->statement(
            'ALTER TABLE import_files DROP CONSTRAINT IF EXISTS import_files_carry_provenance_complete'
        );

        Schema::connection('period')->table('import_files', function (Blueprint $table): void {
            $table->dropUnique('import_files_source_period_file_unique');
            $table->dropIndex('import_files_source_number_index');
            $table->dropColumn(['source_period_id', 'source_import_file_id', 'source_number']);
        });
    }
};
