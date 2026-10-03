<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('company_copy_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_company_id')->constrained('companies');
            $table->foreignId('target_company_id')->constrained('companies');
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                ['source_company_id', 'target_company_id', 'type'],
                'ccp_unique',
            );
        });

        DB::connection('master')->statement(<<<'SQL'
            ALTER TABLE company_copy_permissions
            ADD CONSTRAINT company_copy_permissions_source_target_different
            CHECK (source_company_id <> target_company_id)
        SQL);
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('company_copy_permissions');
    }
};
