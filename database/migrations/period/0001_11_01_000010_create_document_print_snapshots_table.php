<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('document_print_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('template_key', 120);
            $table->unsignedInteger('template_revision_no');
            $table->timestampTz('rendered_at')->nullable();
            $table->unsignedBigInteger('rendered_by')->nullable();
            $table->char('output_hash', 64)->nullable();
            $table->timestampsTz();

            $table->unique('document_id', 'document_print_snapshots_document_unique');
            $table->index(['template_key', 'template_revision_no']);
            $table->index('output_hash');
        });
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('document_print_snapshots');
    }
};
