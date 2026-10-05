<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('report_filter_presets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('report_key', 120);
            $table->string('name', 120);
            $table->jsonb('filters');
            $table->jsonb('columns')->nullable();
            $table->jsonb('sort')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->index(['company_id', 'report_key', 'is_shared']);
        });

        DB::connection('master')->statement(
            'CREATE UNIQUE INDEX report_filter_presets_personal_unique
             ON report_filter_presets (company_id, user_id, report_key, name)
             WHERE user_id IS NOT NULL AND is_shared = false'
        );

        DB::connection('master')->statement(
            'CREATE UNIQUE INDEX report_filter_presets_shared_unique
             ON report_filter_presets (company_id, report_key, name)
             WHERE user_id IS NULL AND is_shared = true'
        );

        DB::connection('master')->statement(
            'ALTER TABLE report_filter_presets
             ADD CONSTRAINT report_filter_presets_scope_check
             CHECK ((is_shared = true AND user_id IS NULL) OR (is_shared = false AND user_id IS NOT NULL))'
        );
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('report_filter_presets');
    }
};
