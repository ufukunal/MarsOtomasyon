<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('period_document_carries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_period_id');
            $table->unsignedBigInteger('source_document_id');
            $table->string('source_document_number', 40);
            $table->foreignId('target_document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->timestampsTz();

            $table->unique(
                ['source_period_id', 'source_document_id'],
                'period_document_carries_source_unique',
            );
            $table->unique('target_document_id', 'period_document_carries_target_unique');
            $table->index(['document_type', 'source_document_number']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE period_document_carries
             ADD CONSTRAINT period_document_carries_type_valid
             CHECK (document_type IN ('sales_order','purchase_order'))"
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('period_document_carries');
    }
};
