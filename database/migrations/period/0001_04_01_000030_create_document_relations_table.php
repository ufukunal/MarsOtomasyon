<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('document_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('target_document_id')->constrained('documents')->restrictOnDelete();
            $table->string('relation_type', 40);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_document_id', 'target_document_id', 'relation_type'],
                'document_relations_unique',
            );
        });

        DB::connection('period')->statement(
            'ALTER TABLE document_relations ADD CONSTRAINT document_relations_source_target_different CHECK (source_document_id <> target_document_id)'
        );
        DB::connection('period')->statement(
            "CREATE UNIQUE INDEX document_relations_one_reversal_per_target ON document_relations (target_document_id) WHERE relation_type = 'reversal_of'"
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('document_relations');
    }
};
